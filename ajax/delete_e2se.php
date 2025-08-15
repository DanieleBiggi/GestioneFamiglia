<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_e2se = (int)($_POST['id_e2se'] ?? 0);
if(!$id_e2se){
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare('DELETE FROM eventi_eventi2salvadanai_etichette WHERE id_e2se=?');
$stmt->bind_param('i', $id_e2se);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
