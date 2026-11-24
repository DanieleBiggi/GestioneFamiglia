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
    $q=$db->prepare('SELECT mp.stagione,mp.settimana,mp.giorno,mp.tipo,p.nome FROM menu_cene_master_piatti mp JOIN piatti p ON p.id_piatto=mp.id_piatto AND p.id_famiglia=? WHERE mp.id_master=? ORDER BY mp.stagione,mp.settimana,mp.giorno,mp.tipo,mp.ordine');
    $q->bind_param('ii',$familyId,$id);
    $q->execute();
    foreach ($q->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $field=$r['tipo']==='contorno'?'contorno':'cena';
        $value=&$data['stagioni'][$r['stagione']][(int)$r['settimana']][$r['giorno']][$field];
        $value.=($value===''?'':' + ').$r['nome'];
        unset($value);
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
    if (mb_strlen($year)>9) throw new InvalidArgumentException('Anno scolastico troppo lungo');
    $anchor=$data['ciclo']['data_riferimento'];
    $weekRef=$data['ciclo']['settimana_riferimento'];
    $q=$db->prepare('INSERT INTO menu_cene_master(id_famiglia,anno_scolastico,data_riferimento,settimana_riferimento,numero_settimane,attivo) VALUES(?,?,?,?,4,0)');
    $q->bind_param('issi',$familyId,$year,$anchor,$weekRef);
    $q->execute(); $masterId=$db->insert_id; $q->close();
    $dish=$db->prepare('INSERT INTO piatti(id_famiglia,nome,nome_hash) VALUES(?,?,?) ON DUPLICATE KEY UPDATE id_piatto=LAST_INSERT_ID(id_piatto)');
    $slot=$db->prepare('INSERT INTO menu_cene_master_piatti(id_master,id_piatto,stagione,settimana,giorno,tipo,ordine) VALUES(?,?,?,?,?,?,?)');
    foreach (['Estivo','Invernale'] as $season)
        foreach (range(1,4) as $week)
            foreach (['Lunedì','Martedì','Mercoledì','Giovedì','Venerdì'] as $day)
                foreach (['cena'=>'principale','contorno'=>'contorno'] as $field=>$type) {
                    $order=0;
                    foreach (preg_split('/\\R|\\s*\\+\\s*|\\s*;\\s*/u',$data['stagioni'][$season][$week][$day][$field]) as $part) {
                        $name=trim($part);
                        if ($name==='') continue;
                        $hash=hash('sha256',food_config_norm($name));
                        $dish->bind_param('iss',$familyId,$name,$hash);
                        $dish->execute(); $dishId=$db->insert_id;
                        $slot->bind_param('iisissi',$masterId,$dishId,$season,$week,$day,$type,$order);
                        $slot->execute(); $order++;
                    }
                    if ($field==='cena' && !$order) throw new InvalidArgumentException('Cena vuota');
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
