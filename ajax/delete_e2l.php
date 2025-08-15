<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_e2l = (int)($_POST['id_e2l'] ?? 0);
if(!$id_e2l){
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare('DELETE FROM eventi_eventi2luogo WHERE id_e2l=?');
$stmt->bind_param('i', $id_e2l);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
