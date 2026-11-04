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

$links = [
    'viaggi_tratte' => 'viaggi_tratte_alternative',
    'viaggi_alloggi' => 'viaggi_alloggi_alternative',
    'viaggi_pasti' => 'viaggi_pasti_alternative',
    'viaggi_altri_costi' => 'viaggi_altri_costi_alternative'
];
$link = $links[$table];
// Aggiorna la spesa una sola volta, verificando l'appartenenza allo scenario.
$sql = sprintf(
    'UPDATE %s e SET e.pagato=? WHERE e.%s=? AND e.id_viaggio=? AND EXISTS (SELECT 1 FROM %s l JOIN viaggi_alternative a ON a.id_viaggio_alternativa=l.id_viaggio_alternativa WHERE l.%s=e.%s AND l.id_viaggio_alternativa=? AND a.id_viaggio=e.id_viaggio)',
    $table, $tableInfo['id'], $link, $tableInfo['id'], $tableInfo['id']
);
$stmt = $conn->prepare($sql);
$stmt->bind_param('iiii', $value, $id, $viaggioId, $altId);
$success = $stmt->execute();

if (!$success) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Impossibile aggiornare il pagamento']);
    exit;
}

if ($stmt->affected_rows === 0) {
    // Nessuna modifica potrebbe anche significare valore già uguale: verificare il collegamento in UI.
}
echo json_encode(['success' => true]);
