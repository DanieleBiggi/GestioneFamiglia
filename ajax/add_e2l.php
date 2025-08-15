<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_evento = (int)($_POST['id_evento'] ?? 0);
$luogo = trim($_POST['luogo'] ?? '');

if(!$id_evento || $luogo === ''){
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare('SELECT id FROM eventi_luogo WHERE indirizzo = ?');
$stmt->bind_param('s', $luogo);
$stmt->execute();
$stmt->bind_result($id_luogo);
if(!$stmt->fetch()){
    $stmt->close();
    $stmt = $conn->prepare('INSERT INTO eventi_luogo (indirizzo) VALUES (?)');
    $stmt->bind_param('s', $luogo);
    $stmt->execute();
    $id_luogo = $stmt->insert_id;
}
$stmt->close();

$stmt = $conn->prepare('INSERT INTO eventi_eventi2luogo (id_evento, id_luogo) VALUES (?,?)');
$stmt->bind_param('ii', $id_evento, $id_luogo);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
