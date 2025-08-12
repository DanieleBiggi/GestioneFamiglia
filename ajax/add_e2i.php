<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_evento = (int)($_POST['id_evento'] ?? 0);
$invitato = trim($_POST['invitato'] ?? '');
$stato = $_POST['stato'] ?? '';
$note = trim($_POST['note'] ?? '');
$partecipa = $stato === 'partecipa' ? 1 : 0;
$forse = $stato === 'forse' ? 1 : 0;
$assente = $stato === 'assente' ? 1 : 0;

if(!$id_evento || $invitato === ''){
    echo json_encode(['success' => false]);
    exit;
}

$famiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$parts = explode(' ', $invitato, 2);
$nome = $parts[0] ?? '';
$cognome = $parts[1] ?? '';

$stmt = $conn->prepare('SELECT i.id FROM eventi_invitati i JOIN eventi_invitati2famiglie f ON i.id = f.id_invitato WHERE f.id_famiglia = ? AND f.attivo = 1 AND i.nome = ? AND i.cognome = ?');
$stmt->bind_param('iss', $famiglia, $nome, $cognome);
$stmt->execute();
$stmt->bind_result($id_invitato);
if(!$stmt->fetch()){
    $stmt->close();
    $stmt = $conn->prepare('INSERT INTO eventi_invitati (nome, cognome) VALUES (?,?)');
    $stmt->bind_param('ss', $nome, $cognome);
    $stmt->execute();
    $id_invitato = $stmt->insert_id;
    $stmt->close();
    $stmt = $conn->prepare('INSERT INTO eventi_invitati2famiglie (id_invitato, id_famiglia, data_inizio) VALUES (?,?,CURDATE())');
    $stmt->bind_param('ii', $id_invitato, $famiglia);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt->close();
}

$stmt = $conn->prepare('INSERT INTO eventi_eventi2invitati (id_evento, id_invitato, partecipa, forse, assente, note) VALUES (?,?,?,?,?,?)');
$stmt->bind_param('iiiiis', $id_evento, $id_invitato, $partecipa, $forse, $assente, $note);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
