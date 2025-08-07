<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$loggedUserId = $_SESSION['utente_id'] ?? 0;
if ($loggedUserId != 1) {
    echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id    = isset($data['id_u2o']) ? (int)$data['id_u2o'] : 0;
$saldata = !empty($data['saldata']) ? 1 : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID non valido']);
    exit;
}

if ($saldata) {
    $stmt = $conn->prepare("UPDATE bilancio_utenti2operazioni_etichettate SET saldata = 1, data_saldo = NOW() WHERE id_u2o = ?");
} else {
    $stmt = $conn->prepare("UPDATE bilancio_utenti2operazioni_etichettate SET saldata = 0, data_saldo = NULL WHERE id_u2o = ?");
}
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
