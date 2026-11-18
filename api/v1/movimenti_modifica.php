<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
api_method('POST');

// These bank records are personal, even if the other modules are family-wide.
$userId = filter_var($apiConfig['movimenti_user_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!$userId) api_error(503, 'Modifica movimenti non configurata');
if ($oauthAccess !== null && (int)$oauthAccess['id_utente'] !== (int)$userId) api_error(403, 'Utente non autorizzato');
$member = $conn->prepare('SELECT 1 FROM utenti2famiglie uf JOIN utenti u ON u.id=uf.id_utente WHERE uf.id_utente=? AND uf.id_famiglia=? AND u.attivo=1 LIMIT 1');
$member->bind_param('ii', $userId, $familyId);
$member->execute();
$authorized = (bool)$member->get_result()->fetch_row();
$member->close();
if (!$authorized) api_error(403, 'Utente non associato alla famiglia');

function gf_mov_hash(array $row, array $labels): string {
    return hash('sha256', json_encode([
        'source'=>$row['source'], 'id'=>(int)$row['id'],
        'descrizione_extra'=>$row['descrizione_extra'],
        'id_gruppo_transazione'=>$row['id_gruppo_transazione'] === null ? null : (int)$row['id_gruppo_transazione'],
        'etichette'=>$labels
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
try {
    $body = api_body();
    $dry = ($body['dry_run'] ?? false) === true;
    $source = $body['source'] ?? null;
    $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $sources = [
        'bilancio_uscite'=>'id_uscita',
        'bilancio_entrate'=>'id_entrata',
        'movimenti_revolut'=>'id_movimento_revolut'
    ];
    if (!is_string($source) || !isset($sources[$source]) || !$id) api_error(422,'Fonte o ID non valido');
    if ($source === 'movimenti_revolut' && (int)$familyId !== 1) api_error(403,'Fonte non disponibile');
    // Legacy labels are not partitioned by family; restrict their use to the legacy owner family.
    $hasLabels = array_key_exists('etichette', $body);
    if ($hasLabels && (int)$familyId !== 1) api_error(403,'Etichette non disponibili per questa famiglia');
    $hasDesc = array_key_exists('descrizione_extra', $body);
    $hasGroup = array_key_exists('id_gruppo_transazione', $body);
    if (!$hasDesc && !$hasGroup && !$hasLabels) api_error(422,'Nessuna modifica indicata');
    $desc = null;
    if ($hasDesc) {
        if ($body['descrizione_extra'] !== null && !is_string($body['descrizione_extra'])) api_error(422,'Descrizione non valida');
        $desc = api_str($body['descrizione_extra'], 250);
    }
    $group = null;
    if ($hasGroup && $body['id_gruppo_transazione'] !== null) {
        $group = filter_var($body['id_gruppo_transazione'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$group) api_error(422,'Gruppo non valido');
    }
    $newLabels = null;
    if ($hasLabels) {
        $newLabels = api_list($body['etichette'], 30);
        $ids = [];
        foreach ($newLabels as $label) {
            $labelId = filter_var($label, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if (!$labelId || isset($ids[$labelId])) api_error(422,'Elenco etichette non valido o duplicato');
            $ids[$labelId] = true;
        }
        $newLabels = array_keys($ids);
        sort($newLabels, SORT_NUMERIC);
    }
    if (!$dry) {
        $expected = $body['expected_hash'] ?? null;
        if (!is_string($expected) || !preg_match('/^[a-f0-9]{64}$/D',$expected)) api_error(422,'Anteprima obbligatoria');
        $requestId = api_request_id();
    }

    $conn->begin_transaction();
    $pk = $sources[$source];
    // The SQL identifiers are selected exclusively from the fixed whitelist above.
    $sql = "SELECT $pk AS id, descrizione_extra, id_gruppo_transazione FROM $source WHERE $pk=?";
    if ($source !== 'movimenti_revolut') $sql .= ' AND id_utente=' . (int)$userId;
    $sql .= ' FOR UPDATE';
    $q = $conn->prepare($sql);
    $q->bind_param('i',$id);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$row) { $conn->rollback(); api_error(404,'Movimento non trovato o non autorizzato'); }
    if ($source === 'movimenti_revolut') {
        // Match the existing read-only API visibility; never edit raw, filtered-out records.
        $visibility = $conn->prepare('SELECT 1 FROM v_movimenti_revolut_filtrati WHERE id_movimento_revolut=? LIMIT 1');
        $visibility->bind_param('i',$id);
        $visibility->execute();
        $visible = (bool)$visibility->get_result()->fetch_row();
        $visibility->close();
        if (!$visible) { $conn->rollback(); api_error(404,'Movimento Revolut non disponibile'); }
    }
    $row['source'] = $source;
    $existing = [];
    $q = $conn->prepare('SELECT id_etichetta FROM bilancio_etichette2operazioni WHERE tabella_operazione=? AND id_tabella=? ORDER BY id_etichetta FOR UPDATE');
    $q->bind_param('si',$source,$id);
    $q->execute();
    $res = $q->get_result();
    while ($r = $res->fetch_assoc()) $existing[] = (int)$r['id_etichetta'];
    $q->close();
    sort($existing,SORT_NUMERIC);
    $currentHash = gf_mov_hash($row,$existing);
    if (!$dry && !hash_equals($currentHash,$expected)) {
        $conn->rollback();
        api_error(409,'Movimento modificato dopo anteprima. Ripetere lettura e anteprima.');
    }
    if ($hasGroup && $group !== null) {
        $q = $conn->prepare('SELECT 1 FROM bilancio_gruppi_transazione WHERE id_gruppo_transazione=? AND id_utente=? LIMIT 1');
        $q->bind_param('ii',$group,$userId);
        $q->execute();
        $ok = (bool)$q->get_result()->fetch_row();
        $q->close();
        if (!$ok) { $conn->rollback(); api_error(422,'Gruppo inesistente o non autorizzato'); }
    }
    if ($hasLabels) {
        foreach ($newLabels as $labelId) {
            $q = $conn->prepare('SELECT 1 FROM bilancio_etichette WHERE id_etichetta=? LIMIT 1');
            $q->bind_param('i',$labelId);
            $q->execute();
            $ok = (bool)$q->get_result()->fetch_row();
            $q->close();
            if (!$ok) { $conn->rollback(); api_error(422,'Etichetta inesistente'); }
        }
    }
    $before = ['descrizione_extra'=>$row['descrizione_extra'],'id_gruppo_transazione'=>$row['id_gruppo_transazione']===null?null:(int)$row['id_gruppo_transazione'],'etichette'=>$existing];
    $after = [
        'descrizione_extra'=>$hasDesc?$desc:$before['descrizione_extra'],
        'id_gruppo_transazione'=>$hasGroup?$group:$before['id_gruppo_transazione'],
        'etichette'=>$hasLabels?$newLabels:$existing
    ];
    $report = ['source'=>$source,'id'=>$id,'precedente'=>$before,'nuovo'=>$after,'modificato'=>$before!==$after,'expected_hash'=>$currentHash,
        'nota'=>'Le etichette fornite sostituiscono la lista completa. Le associazioni mantenute preservano quote, importi, allegati ed esclusioni; quelle rimosse perdono tali dettagli.'];
    if ($dry) { $conn->rollback(); api_reply(200,['success'=>true,'dry_run'=>true,'anteprima'=>$report]); }

    api_claim_request($conn,$familyId,'movimenti_modifica',$requestId);
    if ($hasDesc || $hasGroup) {
        if ($hasDesc && $hasGroup) {
            $q = $conn->prepare("UPDATE $source SET descrizione_extra=?,id_gruppo_transazione=? WHERE $pk=?");
            $q->bind_param('sii',$desc,$group,$id);
        } elseif ($hasDesc) {
            $q = $conn->prepare("UPDATE $source SET descrizione_extra=? WHERE $pk=?");
            $q->bind_param('si',$desc,$id);
        } else {
            $q = $conn->prepare("UPDATE $source SET id_gruppo_transazione=? WHERE $pk=?");
            $q->bind_param('ii',$group,$id);
        }
        $q->execute(); $q->close();
    }
    if ($hasLabels) {
        $removed = array_diff($existing,$newLabels);
        $added = array_diff($newLabels,$existing);
        foreach ($removed as $labelId) {
            // Removal is explicit: avoid the legacy delete-all/reinsert workflow.
            $q=$conn->prepare('DELETE FROM bilancio_etichette2operazioni WHERE tabella_operazione=? AND id_tabella=? AND id_etichetta=?');
            $q->bind_param('sii',$source,$id,$labelId);
            $q->execute(); $q->close();
        }
        foreach ($added as $labelId) {
            $q=$conn->prepare('INSERT INTO bilancio_etichette2operazioni (tabella_operazione,id_tabella,id_etichetta) VALUES (?,?,?)');
            $q->bind_param('sii',$source,$id,$labelId);
            $q->execute(); $q->close();
        }
    }
    $conn->commit();
    api_reply(200,['success'=>true,'updated'=>$before!==$after,'movimento'=>$report]);
} catch (Throwable $e) {
    $conn->rollback();
    api_json_error($e);
}
