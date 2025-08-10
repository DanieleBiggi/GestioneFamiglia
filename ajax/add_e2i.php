<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_evento = (int)($_POST['id_evento'] ?? 0);
$id_invitato = (int)($_POST['id_invitato'] ?? 0);
$stato = $_POST['stato'] ?? '';
$note = trim($_POST['note'] ?? '');
$partecipa = $stato === 'partecipa' ? 1 : 0;
$forse = $stato === 'forse' ? 1 : 0;
$assente = $stato === 'assente' ? 1 : 0;

if(!$id_evento || !$id_invitato){
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare('INSERT INTO eventi_eventi2invitati (id_evento, id_invitato, partecipa, forse, assente, note) VALUES (?,?,?,?,?,?)');
$stmt->bind_param('iiiiis', $id_evento, $id_invitato, $partecipa, $forse, $assente, $note);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
