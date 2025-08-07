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
$ids = isset($data['ids']) && is_array($data['ids']) ? array_filter(array_map('intval', $data['ids'])) : [];

if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'Nessun ID']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));

$stmt = $conn->prepare("UPDATE bilancio_utenti2operazioni_etichettate SET saldata = 1, data_saldo = NOW() WHERE id_u2o IN ($placeholders)");
$stmt->bind_param($types, ...$ids);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
