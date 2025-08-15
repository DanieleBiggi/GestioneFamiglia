<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_e2l = (int)($_POST['id_e2l'] ?? 0);
$luogo = trim($_POST['luogo'] ?? '');

if(!$id_e2l || $luogo === ''){
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

$stmt = $conn->prepare('UPDATE eventi_eventi2luogo SET id_luogo=? WHERE id_e2l=?');
$stmt->bind_param('ii', $id_luogo, $id_e2l);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
