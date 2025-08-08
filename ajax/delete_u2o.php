<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_u2o = intval($_POST['id_u2o'] ?? 0);
if (!$id_u2o) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM bilancio_utenti2operazioni_etichettate WHERE id_u2o = ?");
$stmt->bind_param('i', $id_u2o);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);

