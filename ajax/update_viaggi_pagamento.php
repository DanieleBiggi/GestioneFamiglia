<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';

$allowedTables = [
    'viaggi_tratte' => ['id' => 'id_tratta', 'perm' => 'table:viaggi_tratte'],
    'viaggi_alloggi' => ['id' => 'id_alloggio', 'perm' => 'table:viaggi_alloggi'],
    'viaggi_pasti' => ['id' => 'id_pasto', 'perm' => 'table:viaggi_pasti'],
    'viaggi_altri_costi' => ['id' => 'id_costo', 'perm' => 'table:viaggi_altri_costi'],
];

$table = $_POST['table'] ?? '';
if (!isset($allowedTables[$table])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tabella non supportata']);
    exit;
}

$tableInfo = $allowedTables[$table];
if (!has_permission($conn, $tableInfo['perm'], 'update')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$viaggioId = (int)($_POST['id_viaggio'] ?? 0);
$altId = (int)($_POST['id_viaggio_alternativa'] ?? 0);
$value = isset($_POST['value']) && (int)$_POST['value'] === 1 ? 1 : 0;

if ($id <= 0 || $viaggioId <= 0 || $altId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dati mancanti']);
    exit;
}

$sql = sprintf(
    'UPDATE %s SET pagato=? WHERE %s=? AND id_viaggio=? AND id_viaggio_alternativa=?',
    $table,
    $tableInfo['id']
);
$stmt = $conn->prepare($sql);
$stmt->bind_param('iiii', $value, $id, $viaggioId, $altId);
$success = $stmt->execute();

if (!$success) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Impossibile aggiornare il pagamento']);
    exit;
}

echo json_encode(['success' => true]);
