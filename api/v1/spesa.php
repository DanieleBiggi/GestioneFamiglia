<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/spesa_associazioni_helper.php';
api_method('GET', 'POST');
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $conn->prepare('SELECT id, nome, quantita, note, checked FROM lista_spesa WHERE id_famiglia=? ORDER BY id');
        $stmt->bind_param('i', $familyId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        api_reply(200, ['success'=>true, 'items'=>$items]);
    }
    $body = api_body();
    $items = api_list($body['items'] ?? null, 100);
    if (!$items) api_error(422, 'La lista non può essere vuota');
    $validated = [];
    foreach ($items as $item) {
        $item = api_assoc($item);
        $note = api_str($item['note'] ?? null, 255);
        if (array_key_exists('associazioni', $item)) {
            $prefix = spesa_associazioni_note($item['associazioni']);
            $note = spesa_associazioni_merge_note($prefix, $note);
        }
        $validated[] = [
            api_str($item['nome'] ?? null, 255, true),
            api_str($item['quantita'] ?? null, 100),
            $note
        ];
    }
    if (($body['dry_run'] ?? false) === true) api_reply(200, ['success'=>true,'dry_run'=>true,'items'=>$validated]);
    $requestId = api_request_id();
    $conn->begin_transaction();
    api_claim_request($conn, $familyId, 'spesa', $requestId);
    $stmt = $conn->prepare('INSERT INTO lista_spesa (id_famiglia, nome, quantita, note) VALUES (?, ?, ?, ?)');
    $nome = $quantita = $note = null;
    $stmt->bind_param('isss', $familyId, $nome, $quantita, $note);
    $ids = [];
    foreach ($validated as $data) {
        [$nome, $quantita, $note] = $data;
        $stmt->execute();
        $ids[] = $conn->insert_id;
    }
    $stmt->close();
    $conn->commit();
    api_reply(201, ['success'=>true,'created'=>count($ids),'ids'=>$ids]);
} catch (Throwable $e) {
    $conn->rollback();
    api_json_error($e);
}
