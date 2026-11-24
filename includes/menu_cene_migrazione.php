<?php
require_once __DIR__ . '/menu_cene_master.php';

function menu_migration_plan(int $familyId): array {
    if ($familyId <= 0) throw new InvalidArgumentException('Famiglia non valida');
    $master = food_config_read($familyId, 'master');
    if ($master === null) {
        if ((int)($_SESSION['id_famiglia_gestione'] ?? 0) !== $familyId) {
            throw new RuntimeException('Famiglia non corrispondente alla sessione');
        }
        $master = ['version'=>1,'anno_scolastico'=>'2026-2027',
            'ciclo'=>['settimane'=>4,'data_riferimento'=>'2026-09-14','settimana_riferimento'=>2],
            'stagioni'=>menu_cene_master()];
    }
    food_config_validate('master', $master);
    $recipes = food_config_recipes($familyId)['ricette'];
    $year = $master['anno_scolastico'] ?? '';
    if (mb_strlen($year) > 9) throw new InvalidArgumentException('Anno scolastico troppo lungo');
    $dishes = [];
    $slots = [];
    $recipeMap = [];
    foreach ($recipes as $recipe) {
        $key = food_config_norm($recipe['nome']);
        $recipeMap[$key] = $recipe['id'];
        $dishes[$key] = trim($recipe['nome']);
    }
    foreach (['Estivo','Invernale'] as $season) {
        foreach (range(1,4) as $week) {
            foreach (['Lunedì','Martedì','Mercoledì','Giovedì','Venerdì'] as $day) {
                foreach (['cena'=>'principale','contorno'=>'contorno'] as $field=>$type) {
                    $order = 0;
                    $value = $master['stagioni'][$season][$week][$day][$field];
                    foreach (preg_split('/\\R|\\s*\\+\\s*|\\s*;\\s*/u', $value) as $part) {
                        $name = trim($part);
                        if ($name === '') continue;
                        $key = food_config_norm($name);
                        if (!isset($dishes[$key])) $dishes[$key] = $name;
                        $slots[] = ['season'=>$season,'week'=>$week,'day'=>$day,
                            'type'=>$type,'order'=>$order++,'key'=>$key];
                    }
                    if ($field === 'cena' && $order === 0) throw new InvalidArgumentException('Cena vuota');
                }
            }
        }
    }
    return ['master'=>$master,'recipes'=>$recipes,'dishes'=>$dishes,'slots'=>$slots,'recipeMap'=>$recipeMap];
}

function menu_migration_query(mysqli $conn, string $sql, string $types, array $params): mysqli_stmt {
    $stmt = $conn->prepare($sql);
    if (!$stmt || !$stmt->bind_param($types, ...$params) || !$stmt->execute()) {
        throw new RuntimeException('Errore durante la migrazione SQL');
    }
    return $stmt;
}

function menu_migration_apply(mysqli $conn, int $familyId, string $expectedHash): array {
    if ($familyId <= 0 || (int)($_SESSION['id_famiglia_gestione'] ?? 0) !== $familyId) {
        throw new InvalidArgumentException('Famiglia non valida');
    }
    $conn->set_charset('utf8mb4');
    $conn->begin_transaction();
    try {
        $stmt = menu_migration_query($conn, 'SELECT id_famiglia FROM famiglie WHERE id_famiglia=? FOR UPDATE','i',[$familyId]);
        $found = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$found) throw new RuntimeException('Famiglia non trovata');
        foreach (['menu_cene_master','piatti','ricette'] as $table) {
            $stmt = menu_migration_query($conn, "SELECT COUNT(*) AS n FROM $table WHERE id_famiglia=?", 'i', [$familyId]);
            $count = (int)$stmt->get_result()->fetch_assoc()['n'];
            $stmt->close();
            if ($count !== 0) throw new RuntimeException('Dati relazionali già presenti: migrazione annullata');
        }
        $plan = menu_migration_plan($familyId);
        if (!hash_equals(menu_migration_hash($plan), $expectedHash)) {
            throw new RuntimeException('Configurazione cambiata: ripetere anteprima');
        }
        $recipeIds = [];
        foreach ($plan['recipes'] as $r) {
            $stmt = menu_migration_query($conn,
                'INSERT INTO ricette(id_famiglia,codice,nome,istruzioni) VALUES(?,?,?,?)',
                'isss', [$familyId,$r['id'],$r['nome'],$r['istruzioni']]);
            $recipeIds[$r['id']] = $conn->insert_id;
            $stmt->close();
        }
        $dishIds = [];
        foreach ($plan['dishes'] as $key=>$name) {
            $recipeId = isset($plan['recipeMap'][$key]) ? $recipeIds[$plan['recipeMap'][$key]] : null;
            $stmt = menu_migration_query($conn,
                'INSERT INTO piatti(id_famiglia,nome,nome_hash,id_ricetta) VALUES(?,?,?,?)',
                'issi', [$familyId,$name,hash('sha256',(string)$key),$recipeId]);
            $dishIds[$key] = $conn->insert_id;
            $stmt->close();
        }
        $m = $plan['master'];
        $stmt = menu_migration_query($conn,
            'INSERT INTO menu_cene_master(id_famiglia,anno_scolastico,data_riferimento,settimana_riferimento,numero_settimane,attivo) VALUES(?,?,?,?,4,0)',
            'issi', [$familyId,$m['anno_scolastico'] ?? '',$m['ciclo']['data_riferimento'],$m['ciclo']['settimana_riferimento']]);
        $masterId = $conn->insert_id;
        $stmt->close();
        foreach ($plan['slots'] as $slot) {
            $stmt = menu_migration_query($conn,
                'INSERT INTO menu_cene_master_piatti(id_master,id_piatto,stagione,settimana,giorno,tipo,ordine) VALUES(?,?,?,?,?,?,?)',
                'iisissi', [$masterId,$dishIds[$slot['key']],$slot['season'],$slot['week'],$slot['day'],$slot['type'],$slot['order']]);
            $stmt->close();
        }
        $conn->commit();
        return ['id_master'=>$masterId,'piatti'=>count($dishIds),'ricette'=>count($recipeIds),'occorrenze'=>count($plan['slots']),'attivo'=>false];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function menu_migration_hash(array $plan): string {
    return hash('sha256', json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

/** Activate only a single staged master after checking it still matches the source. */
function menu_migration_activate(mysqli $conn,int $familyId,string $expectedHash): void {
    if ($familyId<=0 || (int)($_SESSION['id_famiglia_gestione']??0)!==$familyId)
        throw new InvalidArgumentException('Famiglia non valida');
    $conn->set_charset('utf8mb4');
    $conn->begin_transaction();
    try {
        menu_rel_lock_family($conn,$familyId);
        $plan=menu_migration_plan($familyId);
        if (!hash_equals(menu_migration_hash($plan),$expectedHash))
            throw new RuntimeException('Sorgente modificata dopo anteprima');
        $q=menu_migration_query($conn,'SELECT * FROM menu_cene_master WHERE id_famiglia=? FOR UPDATE','i',[$familyId]);
        $masters=$q->get_result()->fetch_all(MYSQLI_ASSOC); $q->close();
        if (count($masters)!==1 || (int)$masters[0]['attivo']!==0)
            throw new RuntimeException('Master non attivabile');
        $m=$masters[0]; $id=(int)$m['id_master']; $source=$plan['master'];
        if ($m['anno_scolastico']!==($source['anno_scolastico']??'')
            || $m['data_riferimento']!==$source['ciclo']['data_riferimento']
            || (int)$m['settimana_riferimento']!==$source['ciclo']['settimana_riferimento']
            || (int)$m['numero_settimane']!==4) throw new RuntimeException('Ciclo non corrispondente');
        $q=menu_migration_query($conn,'SELECT mp.stagione,mp.settimana,mp.giorno,mp.tipo,mp.ordine,p.nome,p.nome_hash FROM menu_cene_master_piatti mp JOIN piatti p ON p.id_piatto=mp.id_piatto AND p.id_famiglia=? WHERE mp.id_master=?','ii',[$familyId,$id]);
        $actual=[];
        foreach ($q->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $key=implode('|',[$row['stagione'],$row['settimana'],$row['giorno'],$row['tipo'],$row['ordine']]);
            $actual[$key]=[$row['nome_hash'],$row['nome']];
        }
        $q->close(); $expected=[];
        foreach ($plan['slots'] as $slot) {
            $key=implode('|',[$slot['season'],$slot['week'],$slot['day'],$slot['type'],$slot['order']]);
            $expected[$key]=[hash('sha256',(string)$slot['key']),$plan['dishes'][$slot['key']]];
        }
        ksort($actual); ksort($expected);
        if ($actual!==$expected) throw new RuntimeException('Piatti migrati non corrispondenti');
        $q=menu_migration_query($conn,'UPDATE menu_cene_master SET attivo=1 WHERE id_master=? AND id_famiglia=? AND attivo=0','ii',[$id,$familyId]);
        if ($q->affected_rows!==1) throw new RuntimeException('Attivazione non riuscita');
        $q->close(); $conn->commit();
    } catch (Throwable $e) { $conn->rollback(); throw $e; }
}
