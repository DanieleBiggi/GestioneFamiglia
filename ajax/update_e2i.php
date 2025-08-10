<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_e2i = (int)($_POST['id_e2i'] ?? 0);
$stato = $_POST['stato'] ?? '';
$note = trim($_POST['note'] ?? '');
$partecipa = $stato === 'partecipa' ? 1 : 0;
$forse = $stato === 'forse' ? 1 : 0;
$assente = $stato === 'assente' ? 1 : 0;

if(!$id_e2i){
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare('UPDATE eventi_eventi2invitati SET partecipa=?, forse=?, assente=?, note=? WHERE id_e2i=?');
$stmt->bind_param('iiisi', $partecipa, $forse, $assente, $note, $id_e2i);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
