<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'error' => 'Payload non valido.']);
    exit;
}

$rows = $input['rows'] ?? [];
$fields = $input['fields'] ?? [];
$idUtente = $_SESSION['utente_id'] ?? 0;

$allowedFields = ['id_gruppo_transazione', 'descrizione_extra', 'note'];
$fields = array_intersect_key($fields, array_flip($allowedFields));

if (empty($rows) || empty($fields)) {
    echo json_encode(['success' => false, 'error' => 'Nessun dato da aggiornare.']);
    exit;
}

$tableMap = [
    'bilancio_entrate' => ['pk' => 'id_entrata', 'userColumn' => 'id_utente'],
    'bilancio_uscite' => ['pk' => 'id_uscita', 'userColumn' => 'id_utente'],
    'movimenti_revolut' => ['pk' => 'id_movimento_revolut'],
];

$updated = 0;
$errors = [];

foreach ($rows as $row) {
    $tabella = $row['tabella'] ?? '';
    $id = isset($row['id']) ? (int) $row['id'] : 0;

    if ($id <= 0 || !isset($tableMap[$tabella])) {
        $errors[] = 'Record non valido.';
        continue;
    }

    $setParts = [];
    $params = [];
    $types = '';

    if (array_key_exists('id_gruppo_transazione', $fields)) {
        $setParts[] = 'id_gruppo_transazione = ?';
        $types .= 'i';
        $val = $fields['id_gruppo_transazione'];
        $params[] = ($val === '' || $val === null) ? null : (int) $val;
    }
    if (array_key_exists('descrizione_extra', $fields)) {
        $setParts[] = 'descrizione_extra = ?';
        $types .= 's';
        $params[] = (string) $fields['descrizione_extra'];
    }
    if (array_key_exists('note', $fields)) {
        $setParts[] = 'note = ?';
        $types .= 's';
        $params[] = (string) $fields['note'];
    }

    $pk = $tableMap[$tabella]['pk'];
    $types .= 'i';
    $params[] = $id;

    $sql = "UPDATE {$tabella} SET " . implode(', ', $setParts) . " WHERE {$pk} = ?";
    if (!empty($tableMap[$tabella]['userColumn'])) {
        $sql .= " AND {$tableMap[$tabella]['userColumn']} = ?";
        $types .= 'i';
        $params[] = (int) $idUtente;
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $errors[] = 'Errore preparazione query.';
        continue;
    }

    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $updated += 1;
    } else {
        $errors[] = 'Errore aggiornamento.';
    }
    $stmt->close();
}

echo json_encode([
    'success' => $updated > 0,
    'updated' => $updated,
    'errors' => $errors,
]);
