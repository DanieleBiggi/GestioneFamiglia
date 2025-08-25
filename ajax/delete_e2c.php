<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_e2c = (int)($_POST['id_e2c'] ?? 0);
if(!$id_e2c){
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare('DELETE FROM eventi_eventi2cibo WHERE id_e2c=?');
$stmt->bind_param('i', $id_e2c);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
