<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_e2o = intval($_POST['id_e2o'] ?? 0);
if (!$id_e2o) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM bilancio_etichette2operazioni WHERE id_e2o = ?");
$stmt->bind_param('i', $id_e2o);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    $stmtU = $conn->prepare("DELETE FROM bilancio_utenti2operazioni_etichettate WHERE id_e2o = ?");
    $stmtU->bind_param('i', $id_e2o);
    $stmtU->execute();
    $stmtU->close();
}

echo json_encode(['success' => $success]);
