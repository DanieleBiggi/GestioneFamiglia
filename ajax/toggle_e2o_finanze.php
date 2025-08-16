<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id = intval($_POST['id_e2o'] ?? 0);
$escludi = intval($_POST['escludi'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false]);
    exit;
}
$stmt = $conn->prepare('UPDATE bilancio_etichette2operazioni SET escludi_da_finanze_evento = ? WHERE id_e2o = ?');
$stmt->bind_param('ii', $escludi, $id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok]);
