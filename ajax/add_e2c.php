<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_evento = (int)($_POST['id_evento'] ?? 0);
$id_cibo = (int)($_POST['id_cibo'] ?? 0);
$quantita_input = trim($_POST['quantita'] ?? '');
$quantita = $quantita_input === '' ? null : (float)$quantita_input;

if(!$id_evento || !$id_cibo){
    echo json_encode(['success' => false]);
    exit;
}

if($quantita === null){
    $stmt = $conn->prepare('INSERT INTO eventi_eventi2cibo (id_evento, id_cibo, quantita) VALUES (?,?,NULL)');
    $stmt->bind_param('ii', $id_evento, $id_cibo);
} else {
    $stmt = $conn->prepare('INSERT INTO eventi_eventi2cibo (id_evento, id_cibo, quantita) VALUES (?,?,?)');
    $stmt->bind_param('iid', $id_evento, $id_cibo, $quantita);
}
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
