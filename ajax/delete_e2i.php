<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_e2i = intval($_POST['id_e2i'] ?? 0);
if (!$id_e2i) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare('DELETE FROM eventi_eventi2invitati WHERE id_e2i = ?');
$stmt->bind_param('i', $id_e2i);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
