<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
api_method('GET','POST');
if (!$allowVacations) api_error(403, 'Modulo vacanze non abilitato');

$ownerId = filter_var($apiConfig['movimenti_user_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!$ownerId) api_error(503, 'Accesso alle finanze non configurato');
if ($oauthAccess !== null && (int)$oauthAccess['id_utente'] !== (int)$ownerId) api_error(403, 'Finanze personali non autorizzate');
$membership = $conn->prepare('SELECT 1 FROM utenti2famiglie uf JOIN utenti u ON u.id=uf.id_utente WHERE uf.id_utente=? AND uf.id_famiglia=? AND u.attivo=1 LIMIT 1');
$membership->bind_param('ii', $ownerId, $familyId);
$membership->execute();
$isMember = (bool)$membership->get_result()->fetch_row();
$membership->close();
if (!$isMember) api_error(403, 'Utente non autorizzato');
// The legacy labels and saving links have no family_id. Do not expose them to another family.
if ((int)$familyId !== 1) api_error(403, 'Collegamenti finanziari legacy non disponibili per questa famiglia');

function gf_fin_fetch(mysqli $conn, string $sql, string $types = '', array $values = []): array {
    $stmt = $conn->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}
function gf_fin_id($value, string $label): int {
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($id === false || $id === null) api_error(422, 'ID non valido: ' . $label);
    return (int)$id;
}
function gf_fin_saving(mysqli $conn, int $savingId, int $familyId, bool $lock = false): array {
    // Salvadanai are legacy global data. Only accept those already attached to
    // a family-scoped budget or to a trip owned by this family.
    $rows = gf_fin_fetch($conn,
        'SELECT s.id_salvadanaio,s.nome_salvadanaio,s.importo_attuale FROM salvadanai s ' .
        'WHERE s.id_salvadanaio=? AND (' .
        'EXISTS (SELECT 1 FROM budget b WHERE b.id_salvadanaio=s.id_salvadanaio AND b.id_famiglia=?) ' .
        'OR EXISTS (SELECT 1 FROM viaggi_pianificazione p JOIN viaggi_api_famiglie f ON f.id_viaggio=p.id_viaggio ' .
        'WHERE p.id_salvadanaio=s.id_salvadanaio AND f.id_famiglia=?)) LIMIT 1' . ($lock ? ' FOR UPDATE' : ''),
        'iii', [$savingId,$familyId,$familyId]);
    if (!$rows) api_error(404, 'Salvadanaio non associato alla famiglia');
    return $rows[0];
}
function gf_fin_trip(mysqli $conn, int $tripId, int $familyId): array {
    $rows = gf_fin_fetch($conn, 'SELECT v.id_viaggio,v.titolo,p.id_salvadanaio,p.id_alternativa_confermata ' .
        'FROM viaggi v JOIN viaggi_api_famiglie vf ON vf.id_viaggio=v.id_viaggio AND vf.id_famiglia=? ' .
        'LEFT JOIN viaggi_pianificazione p ON p.id_viaggio=v.id_viaggio WHERE v.id_viaggio=? LIMIT 1 FOR UPDATE',
        'ii', [$familyId,$tripId]);
    if (!$rows) api_error(404,'Vacanza non autorizzata');
    return $rows[0];
}
function gf_fin_event(mysqli $conn, int $eventId, int $familyId): array {
    $rows = gf_fin_fetch($conn,'SELECT e.id,e.titolo FROM eventi e JOIN eventi_eventi2famiglie ef ON ef.id_evento=e.id WHERE ef.id_famiglia=? AND e.id=? LIMIT 1','ii',[$familyId,$eventId]);
    if (!$rows) api_error(404,'Evento non autorizzato');
    return $rows[0];
}
function gf_fin_label(mysqli $conn, int $labelId): array {
    $rows = gf_fin_fetch($conn,'SELECT id_etichetta,descrizione,attivo FROM bilancio_etichette WHERE id_etichetta=? LIMIT 1','i',[$labelId]);
    if (!$rows) api_error(404,'Etichetta non trovata');
    return $rows[0];
}
function gf_fin_budget(mysqli $conn, int $budgetId, int $familyId): array {
    $rows = gf_fin_fetch($conn,'SELECT id_budget,id_salvadanaio,descrizione,importo,tipologia FROM budget WHERE id_budget=? AND id_famiglia=? LIMIT 1 FOR UPDATE','ii',[$budgetId,$familyId]);
    if (!$rows) api_error(404,'Budget non autorizzato');
    return $rows[0];
}
function gf_fin_snapshot(mysqli $conn, int $familyId, string $type, int $id): array {
    if ($type === 'vacanza') {
        $trip = gf_fin_trip($conn,$id,$familyId);
        $savingId = (int)($trip['id_salvadanaio'] ?? 0);
        $trip['salvadanaio'] = $savingId ? gf_fin_saving($conn,$savingId,$familyId) : null;
        $trip['collegamenti'] = $savingId ? gf_fin_snapshot($conn,$familyId,'salvadanaio',$savingId) : null;
        return $trip;
    }
    if ($type === 'salvadanaio') {
        $saving = gf_fin_saving($conn,$id,$familyId);
        $saving['budget'] = gf_fin_fetch($conn,'SELECT id_budget,descrizione,importo,tipologia,tipologia_spesa FROM budget WHERE id_famiglia=? AND id_salvadanaio=? ORDER BY id_budget','ii',[$familyId,$id]);
        $saving['etichette'] = gf_fin_fetch($conn,'SELECT x.id_e2se,x.id_etichetta,e.descrizione FROM eventi_eventi2salvadanai_etichette x JOIN bilancio_etichette e ON e.id_etichetta=x.id_etichetta WHERE x.id_salvadanaio=? AND x.id_etichetta IS NOT NULL ORDER BY x.id_e2se','i',[$id]);
        $saving['eventi'] = gf_fin_fetch($conn,'SELECT x.id_e2se,x.id_evento,e.titolo FROM eventi_eventi2salvadanai_etichette x JOIN eventi e ON e.id=x.id_evento JOIN eventi_eventi2famiglie ef ON ef.id_evento=e.id AND ef.id_famiglia=? WHERE x.id_salvadanaio=? ORDER BY x.id_e2se','ii',[$familyId,$id]);
        $saving['vacanze'] = gf_fin_fetch($conn,'SELECT v.id_viaggio,v.titolo FROM viaggi_pianificazione p JOIN viaggi v ON v.id_viaggio=p.id_viaggio JOIN viaggi_api_famiglie vf ON vf.id_viaggio=v.id_viaggio AND vf.id_famiglia=? WHERE p.id_salvadanaio=?','ii',[$familyId,$id]);
        return $saving;
    }
    if ($type === 'etichetta') {
        $label = gf_fin_label($conn,$id);
        $label['salvadanai'] = gf_fin_fetch($conn,
            'SELECT x.id_e2se,x.id_salvadanaio,s.nome_salvadanaio FROM eventi_eventi2salvadanai_etichette x JOIN salvadanai s ON s.id_salvadanaio=x.id_salvadanaio WHERE x.id_etichetta=? ORDER BY x.id_e2se','i',[$id]);
        $label['eventi'] = gf_fin_fetch($conn,
            'SELECT x.id_e2se,x.id_evento,e.titolo FROM eventi_eventi2salvadanai_etichette x JOIN eventi e ON e.id=x.id_evento JOIN eventi_eventi2famiglie ef ON ef.id_evento=e.id AND ef.id_famiglia=? WHERE x.id_etichetta=? ORDER BY x.id_e2se','ii',[$familyId,$id]);
        // The monetary value is stored on individual links; never multiply by the number of labels.
        // Return the components, not a misleading aggregate that double-counts cross-labelled movements.
        $label['movimenti'] = gf_fin_fetch($conn,
            "SELECT x.id_e2o,x.tabella_operazione AS source,x.id_tabella AS id_movimento,x.importo AS importo_etichetta,x.escludi_da_finanze_evento," .
            "CASE WHEN x.tabella_operazione='bilancio_uscite' THEN -ABS(bu.importo) WHEN x.tabella_operazione='bilancio_entrate' THEN ABS(be.importo) ELSE mr.amount END AS importo_movimento," .
            "COALESCE(x.descrizione_extra,bu.descrizione_extra,be.descrizione_extra,mr.descrizione_extra,bu.descrizione_operazione,be.descrizione_operazione,mr.description) AS descrizione " .
            "FROM bilancio_etichette2operazioni x " .
            "LEFT JOIN bilancio_uscite bu ON x.tabella_operazione='bilancio_uscite' AND x.id_tabella=bu.id_uscita AND bu.id_utente=? " .
            "LEFT JOIN bilancio_entrate be ON x.tabella_operazione='bilancio_entrate' AND x.id_tabella=be.id_entrata AND be.id_utente=? " .
            "LEFT JOIN v_movimenti_revolut_filtrati mr ON x.tabella_operazione='movimenti_revolut' AND x.id_tabella=mr.id_movimento_revolut " .
            "WHERE x.id_etichetta=? AND ((x.tabella_operazione='bilancio_uscite' AND bu.id_uscita IS NOT NULL) OR (x.tabella_operazione='bilancio_entrate' AND be.id_entrata IS NOT NULL) OR (x.tabella_operazione='movimenti_revolut' AND mr.id_movimento_revolut IS NOT NULL)) ORDER BY x.id_e2o",
            'iii',[(int)$GLOBALS['ownerId'],(int)$GLOBALS['ownerId'],$id]);
        return $label;
    }
    if ($type === 'evento') {
        $event = gf_fin_event($conn,$id,$familyId);
        $event['etichette'] = gf_fin_fetch($conn,'SELECT x.id_e2se,x.id_etichetta,b.descrizione FROM eventi_eventi2salvadanai_etichette x JOIN bilancio_etichette b ON b.id_etichetta=x.id_etichetta WHERE x.id_evento=? ORDER BY x.id_e2se','i',[$id]);
        $event['salvadanai'] = gf_fin_fetch($conn,'SELECT x.id_e2se,x.id_salvadanaio,s.nome_salvadanaio FROM eventi_eventi2salvadanai_etichette x JOIN salvadanai s ON s.id_salvadanaio=x.id_salvadanaio WHERE x.id_evento=? ORDER BY x.id_e2se','i',[$id]);
        return $event;
    }
    api_error(422,'Tipo non valido');
}
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        $type = $_GET['tipo'] ?? 'vacanza';
        if ($type === 'catalogo') {
            $catalog = [
                'vacanze'=>gf_fin_fetch($conn,'SELECT v.id_viaggio AS id,v.titolo AS descrizione FROM viaggi v JOIN viaggi_api_famiglie vf ON vf.id_viaggio=v.id_viaggio AND vf.id_famiglia=? ORDER BY v.data_inizio DESC LIMIT 200','i',[$familyId]),
                'salvadanai'=>gf_fin_fetch($conn,'SELECT DISTINCT s.id_salvadanaio AS id,s.nome_salvadanaio AS descrizione FROM salvadanai s WHERE EXISTS (SELECT 1 FROM budget b WHERE b.id_salvadanaio=s.id_salvadanaio AND b.id_famiglia=?) OR EXISTS (SELECT 1 FROM viaggi_pianificazione p JOIN viaggi_api_famiglie vf ON vf.id_viaggio=p.id_viaggio AND vf.id_famiglia=? WHERE p.id_salvadanaio=s.id_salvadanaio) ORDER BY descrizione LIMIT 200','ii',[$familyId,$familyId]),
                'budget'=>gf_fin_fetch($conn,'SELECT id_budget AS id,descrizione,id_salvadanaio,importo,tipologia FROM budget WHERE id_famiglia=? ORDER BY id_budget DESC LIMIT 200','i',[$familyId]),
                'etichette'=>gf_fin_fetch($conn,'SELECT id_etichetta AS id,descrizione,attivo FROM bilancio_etichette ORDER BY descrizione LIMIT 200'),
                'eventi'=>gf_fin_fetch($conn,'SELECT e.id,e.titolo AS descrizione FROM eventi e JOIN eventi_eventi2famiglie f ON f.id_evento=e.id WHERE f.id_famiglia=? ORDER BY e.data_evento DESC LIMIT 200','i',[$familyId])
            ];
            api_reply(200,['success'=>true,'catalogo'=>$catalog]);
        }
        if (!in_array($type,['vacanza','salvadanaio','etichetta','evento'],true)) api_error(422,'Tipo non valido');
        $id = gf_fin_id($_GET['id'] ?? null,'id');
        api_reply(200,['success'=>true,'tipo'=>$type,'dati'=>gf_fin_snapshot($conn,$familyId,$type,$id)]);
    }
    $body = api_body();
    $dry = ($body['dry_run'] ?? false) === true;
    $action = $body['azione'] ?? null;
    $linkTypes = ['etichetta_salvadanaio','etichetta_evento','budget_salvadanaio','vacanza_salvadanaio'];
    $operations = ['collega','scollega'];
    if (!is_string($action) || !in_array($action,$operations,true)) api_error(422,'Azione non valida');
    $type = $body['tipo'] ?? null;
    if (!is_string($type) || !in_array($type,$linkTypes,true)) api_error(422,'Collegamento non valido');
    $leftName = [
        'etichetta_salvadanaio'=>'id_etichetta','etichetta_evento'=>'id_etichetta',
        'budget_salvadanaio'=>'id_budget','vacanza_salvadanaio'=>'id_viaggio'
    ][$type];
    $left = gf_fin_id($body[$leftName] ?? null,$leftName);
    $rightRaw = $body['id_salvadanaio'] ?? null;
    if ($type==='etichetta_evento') $rightRaw=$body['id_evento'] ?? null;
    $right = $action==='scollega' && $type==='vacanza_salvadanaio' && $rightRaw===null ? null : gf_fin_id($rightRaw,'destinazione');
    if (!$dry) {
        $expected = $body['expected_hash'] ?? null;
        if (!is_string($expected) || !preg_match('/^[a-f0-9]{64}$/D',$expected)) api_error(422,'Anteprima obbligatoria');
        $requestId = api_request_id();
    }
    $conn->begin_transaction();
    // Always validate each endpoint against family membership before preview and write.
    if (str_starts_with($type,'etichetta_')) gf_fin_label($conn,$left);
    if ($type==='budget_salvadanaio') $budget = gf_fin_budget($conn,$left,$familyId);
    if ($type==='vacanza_salvadanaio') $trip = gf_fin_trip($conn,$left,$familyId);
    if ($type==='etichetta_evento') gf_fin_event($conn,(int)$right,$familyId);
    if ($type==='etichetta_salvadanaio' || $type==='budget_salvadanaio' || ($type==='vacanza_salvadanaio' && $right !== null)) {
        gf_fin_saving($conn,(int)$right,$familyId,true);
    }
    if ($type==='budget_salvadanaio') {
        // An unlinked budget is permitted, but never create an implicit saving.
        $current = $budget['id_salvadanaio']===null?null:(int)$budget['id_salvadanaio'];
        if ($action==='collega' && $current!==null && $current!==$right) api_error(409,'Budget già associato ad altro salvadanaio');
        if ($action==='scollega' && $current!==$right) api_error(409,'Il budget non è collegato al salvadanaio indicato');
        $exists = $current===$right;
        $willChange = $action==='collega'?!$exists:$exists;
        $state=['id_salvadanaio'=>$current];
    } elseif ($type==='vacanza_salvadanaio') {
        $current = $trip['id_salvadanaio']===null?null:(int)$trip['id_salvadanaio'];
        if ($action==='collega' && $current!==null && $current!==$right) api_error(409,'Vacanza già collegata a un altro salvadanaio');
        if ($action==='collega' && empty($trip['id_alternativa_confermata'])) api_error(409,'Confermare prima lo scenario della vacanza');
        if ($action==='scollega' && $current===null) api_error(409,'Nessun salvadanaio collegato');
        if ($action==='scollega' && $right!==null && $current!==$right) api_error(409,'Collegamento diverso da quello indicato');
        if ($action==='collega') {
            $other = gf_fin_fetch($conn,'SELECT p.id_viaggio FROM viaggi_pianificazione p WHERE p.id_salvadanaio=? AND p.id_viaggio<>? LIMIT 1','ii',[$right,$left]);
            if ($other) api_error(409,'Salvadanaio già collegato a un altra vacanza');
        }
        $exists = $current===$right;
        $willChange = $action==='collega'?!$exists:true;
        $state=['id_salvadanaio'=>$current];
    } else {
        $column = $type==='etichetta_evento'?'id_evento':'id_salvadanaio';
        $links=gf_fin_fetch($conn,"SELECT id_e2se,id_evento,id_salvadanaio,id_etichetta FROM eventi_eventi2salvadanai_etichette WHERE id_etichetta=? AND $column=? ORDER BY id_e2se FOR UPDATE",'ii',[$left,$right]);
        $simple=array_values(array_filter($links, static function($row) use ($type) {
            return $type==='etichetta_evento' ? $row['id_salvadanaio']===null : $row['id_evento']===null;
        }));
        // A three-way relationship must be kept intact; ask user to manage that record in the web UI.
        if ($action==='scollega' && count($simple)!==1) api_error(409,'Scollegamento ambiguo o record condiviso: gestione manuale necessaria');
        if ($action==='collega' && count($links)>0) api_error(409,'Collegamento già esistente');
        $exists=count($simple)>0;
        $willChange=true;
        $state=['collegamenti'=>$links];
    }
    $hash=hash('sha256',json_encode(['tipo'=>$type,'azione'=>$action,'origine'=>$left,'destinazione'=>$right,'stato'=>$state],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    if (!$dry && !hash_equals($hash,$expected)) {
        $conn->rollback();
        api_error(409,'Collegamenti modificati dopo anteprima');
    }
    $preview=['azione'=>$action,'tipo'=>$type,'origine'=>$left,'destinazione'=>$right,'precedente'=>$state,'modifica'=>$willChange,'expected_hash'=>$hash,
        'nota'=>'Collega o scollega solo la relazione indicata: non modifica importi, budget preventivi, movimenti o saldi.'];
    if ($dry) { $conn->rollback(); api_reply(200,['success'=>true,'dry_run'=>true,'anteprima'=>$preview]); }
    api_claim_request($conn,$familyId,'finanze_collegamenti',$requestId);
    if ($willChange) {
        if ($type==='budget_salvadanaio') {
            $new=$action==='collega'?$right:null;
            $q=$conn->prepare('UPDATE budget SET id_salvadanaio=? WHERE id_budget=? AND id_famiglia=?');
            $q->bind_param('iii',$new,$left,$familyId);$q->execute();$q->close();
        } elseif ($type==='vacanza_salvadanaio') {
            if ($action==='collega') {
                $q=$conn->prepare('INSERT IGNORE INTO viaggi_pianificazione (id_viaggio) VALUES (?)');
                $q->bind_param('i',$left);$q->execute();$q->close();
                $q=$conn->prepare('UPDATE viaggi_pianificazione SET id_salvadanaio=? WHERE id_viaggio=?');
                $q->bind_param('ii',$right,$left);$q->execute();$q->close();
            } else {
                $q=$conn->prepare('UPDATE viaggi_pianificazione SET id_salvadanaio=NULL,obiettivo_salvadanaio=NULL WHERE id_viaggio=?');
                $q->bind_param('i',$left);$q->execute();$q->close();
            }
        } else {
            if ($action==='collega') {
                if ($type==='etichetta_evento') {
                    $q=$conn->prepare('INSERT INTO eventi_eventi2salvadanai_etichette (id_etichetta,id_evento) VALUES (?,?)');
                } else {
                    $q=$conn->prepare('INSERT INTO eventi_eventi2salvadanai_etichette (id_etichetta,id_salvadanaio) VALUES (?,?)');
                }
                $q->bind_param('ii',$left,$right);$q->execute();$q->close();
            } else {
                $rowId=(int)$simple[0]['id_e2se'];
                $q=$conn->prepare('DELETE FROM eventi_eventi2salvadanai_etichette WHERE id_e2se=?');
                $q->bind_param('i',$rowId);$q->execute();$q->close();
            }
        }
    }
    $conn->commit();
    api_reply(200,['success'=>true,'updated'=>$willChange,'collegamento'=>$preview]);
} catch(Throwable $e) {
    $conn->rollback();
    api_json_error($e);
}
