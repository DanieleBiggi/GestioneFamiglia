<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/spesa_associazioni_helper.php';
api_method('POST');
try {
    $body = api_body();
    $items = api_list($body['items'] ?? null, 100);
    if (!$items) api_error(422, 'Nessun prodotto da aggiornare');
    $dryRun = ($body['dry_run'] ?? false) === true;
    $updates = [];
    $seen = [];
    foreach ($items as $item) {
        $item = api_assoc($item);
        $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$id || isset($seen[$id])) api_error(422, 'ID prodotto non valido o duplicato');
        $seen[$id] = true;
        if (!is_array($item['associazioni'] ?? null)) api_error(422, 'Associazioni obbligatorie');
        $updates[] = ['id'=>$id, 'prefix'=>spesa_associazioni_note($item['associazioni'])];
    }

    // La preview e la scrittura rileggono le note attuali: non si ipotizza mai
    // che i prodotti o le annotazioni siano rimasti invariati.
    if (!$dryRun) {
        $requestId = api_request_id();
        $conn->begin_transaction();
        api_claim_request($conn, $familyId, 'spesa_associazioni', $requestId);
    }
    $select = $conn->prepare(
        'SELECT nome, note FROM lista_spesa WHERE id=? AND id_famiglia=?' . (!$dryRun ? ' FOR UPDATE' : '')
    );
    $id = 0;
    $select->bind_param('ii', $id, $familyId);
    $preview = [];
    foreach ($updates as $update) {
        $id = $update['id'];
        $select->execute();
        $row = $select->get_result()->fetch_assoc();
        if (!$row) api_error(422, 'Prodotto inesistente nella famiglia configurata');
        $newNote = spesa_associazioni_merge_note($update['prefix'], $row['note'] ?? '');
        $preview[] = [
            'id'=>$id,
            'nome'=>$row['nome'],
            'note_originali'=>$row['note'] ?? '',
            'note_nuove'=>$newNote
        ];
    }
    $select->close();
    if ($dryRun) {
        api_reply(200, ['success'=>true, 'dry_run'=>true, 'items'=>$preview]);
    }
    $stmt = $conn->prepare('UPDATE lista_spesa SET note=? WHERE id=? AND id_famiglia=?');
    $newNote = '';
    $stmt->bind_param('sii', $newNote, $id, $familyId);
    foreach ($preview as $item) {
        $id = $item['id'];
        $newNote = $item['note_nuove'];
        $stmt->execute();
    }
    $stmt->close();
    $conn->commit();
    api_reply(200, ['success'=>true, 'updated'=>count($preview), 'items'=>$preview]);
} catch (Throwable $e) {
    $conn->rollback();
    api_json_error($e);
}
