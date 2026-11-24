<?php
require_once __DIR__.'/menu_cene_config.php';

/**
 * Relational master access. Caller supplies the authenticated family ID.
 * No fallback on SQL errors: callers must not silently overwrite legacy data.
 */
function menu_rel_read(mysqli $db, int $familyId, bool $lock = false): ?array {
    if ($familyId <= 0) throw new InvalidArgumentException('Famiglia non valida');
    $sql = 'SELECT id_master,anno_scolastico,data_riferimento,settimana_riferimento,numero_settimane FROM menu_cene_master WHERE id_famiglia=? AND attivo=1 ORDER BY id_master DESC LIMIT 2';
    $q = $db->prepare($sql.($lock?' FOR UPDATE':''));
    $q->bind_param('i',$familyId);
    $q->execute();
    $rows = $q->get_result()->fetch_all(MYSQLI_ASSOC);
    $q->close();
    if (!$rows) return null;
    if (count($rows)!==1) throw new RuntimeException('Più master attivi');
    $m = $rows[0];
    $data = ['version'=>1,'anno_scolastico'=>$m['anno_scolastico'],
        'ciclo'=>['settimane'=>(int)$m['numero_settimane'],'data_riferimento'=>$m['data_riferimento'],
        'settimana_riferimento'=>(int)$m['settimana_riferimento']], 'stagioni'=>[]];
    foreach (['Estivo','Invernale'] as $season)
        foreach (range(1,4) as $week)
            foreach (['Lunedì','Martedì','Mercoledì','Giovedì','Venerdì'] as $day)
                $data['stagioni'][$season][$week][$day]=['cena'=>'','contorno'=>''];
    $id=(int)$m['id_master'];
    $q=$db->prepare('SELECT mp.stagione,mp.settimana,mp.giorno,mp.tipo,mp.testo_originale FROM menu_cene_master_piatti mp JOIN piatti p ON p.id_piatto=mp.id_piatto AND p.id_famiglia=? WHERE mp.id_master=? ORDER BY mp.stagione,mp.settimana,mp.giorno,mp.tipo,mp.ordine');
    $q->bind_param('ii',$familyId,$id);
    $q->execute();
    foreach ($q->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $field=$r['tipo']==='contorno'?'contorno':'cena';
        if ($r['testo_originale'] !== null) {
            $data['stagioni'][$r['stagione']][(int)$r['settimana']][$r['giorno']][$field] = $r['testo_originale'];
        }
    }
    $q->close();
    food_config_validate('master',$data);
    return $data;
}

/** Must run inside the caller's transaction, after locking the family row. */
function menu_rel_save(mysqli $db,int $familyId,array $data): int {
    if ($familyId<=0) throw new InvalidArgumentException('Famiglia non valida');
    food_config_validate('master',$data);
    $year=$data['anno_scolastico']??'';
    $anchor=$data['ciclo']['data_riferimento'];
    $weekRef=$data['ciclo']['settimana_riferimento'];
    $q=$db->prepare('INSERT INTO menu_cene_master(id_famiglia,anno_scolastico,data_riferimento,settimana_riferimento,numero_settimane,attivo) VALUES(?,?,?,?,4,0)');
    $q->bind_param('issi',$familyId,$year,$anchor,$weekRef);
    $q->execute(); $masterId=$db->insert_id; $q->close();
    $dish=$db->prepare('INSERT INTO piatti(id_famiglia,nome,nome_hash) VALUES(?,?,?) ON DUPLICATE KEY UPDATE id_piatto=LAST_INSERT_ID(id_piatto)');
    $slot=$db->prepare('INSERT INTO menu_cene_master_piatti(id_master,id_piatto,stagione,settimana,giorno,tipo,ordine,testo_originale) VALUES(?,?,?,?,?,?,?,?)');
    foreach (['Estivo','Invernale'] as $season)
        foreach (range(1,4) as $week)
            foreach (['Lunedì','Martedì','Mercoledì','Giovedì','Venerdì'] as $day)
                foreach (['cena'=>'principale','contorno'=>'contorno'] as $field=>$type) {
                    $order=0;
                    $text = $data['stagioni'][$season][$week][$day][$field];
                    foreach (menu_rel_parts($text) as $name) {
                        $hash=hash('sha256',food_config_norm($name));
                        $dish->bind_param('iss',$familyId,$name,$hash);
                        $dish->execute(); $dishId=$db->insert_id;
                        $original = $order === 0 ? $text : null;
                        $slot->bind_param('iisissis',$masterId,$dishId,$season,$week,$day,$type,$order,$original);
                        $slot->execute(); $order++;
                    }
                }
    $dish->close(); $slot->close();
    $q=$db->prepare('UPDATE menu_cene_master SET attivo=0 WHERE id_famiglia=? AND attivo=1');
    $q->bind_param('i',$familyId); $q->execute(); $q->close();
    $q=$db->prepare('UPDATE menu_cene_master SET attivo=1 WHERE id_master=? AND id_famiglia=?');
    $q->bind_param('ii',$masterId,$familyId); $q->execute(); $q->close();
    return $masterId;
}

function menu_rel_lock_family(mysqli $db,int $familyId): void {
    $q=$db->prepare('SELECT id_famiglia FROM famiglie WHERE id_famiglia=? FOR UPDATE');
    $q->bind_param('i',$familyId); $q->execute();
    $found=$q->get_result()->fetch_assoc(); $q->close();
    if (!$found) throw new RuntimeException('Famiglia non trovata');
}

/** Keep the submitted text separately from the normalized catalog entries. */
function menu_rel_parts(string $text): array {
    if ($text === '') return [];
    $parts = array_values(array_filter(
        array_map('trim', preg_split('/\R|\s*\+\s*|\s*;\s*/u', $text)),
        static fn(string $part): bool => $part !== ''
    ));
    return $parts ?: [$text];
}

function menu_rel_recipes(mysqli $db, int $familyId): array {
    $stmt = $db->prepare('SELECT codice AS id, nome, istruzioni FROM ricette WHERE id_famiglia=? AND attiva=1 ORDER BY id_ricetta');
    $stmt->bind_param('i', $familyId);
    $stmt->execute();
    $recipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return ['version' => 1, 'ricette' => $recipes];
}

function menu_rel_save_recipes(mysqli $db, int $familyId, array $data): void {
    food_config_validate('ricette', $data);
    $stmt = $db->prepare('UPDATE ricette SET attiva=0 WHERE id_famiglia=? AND attiva=1');
    $stmt->bind_param('i', $familyId);
    $stmt->execute();
    $stmt->close();
    $stmt = $db->prepare('UPDATE piatti SET id_ricetta=NULL WHERE id_famiglia=? AND id_ricetta IS NOT NULL');
    $stmt->bind_param('i', $familyId);
    $stmt->execute();
    $stmt->close();
    $recipe = $db->prepare('INSERT INTO ricette (id_famiglia,codice,nome,istruzioni) VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE id_ricetta=LAST_INSERT_ID(id_ricetta),nome=VALUES(nome),istruzioni=VALUES(istruzioni),attiva=1');
    $dish = $db->prepare('INSERT INTO piatti (id_famiglia,nome,nome_hash,id_ricetta) VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE id_ricetta=VALUES(id_ricetta)');
    foreach ($data['ricette'] as $entry) {
        $recipe->bind_param('isss', $familyId, $entry['id'], $entry['nome'], $entry['istruzioni']);
        $recipe->execute();
        $recipeId = $db->insert_id;
        $name = trim($entry['nome']);
        $hash = hash('sha256', food_config_norm($name));
        $dish->bind_param('issi', $familyId, $name, $hash, $recipeId);
        $dish->execute();
    }
    $recipe->close();
    $dish->close();
}
