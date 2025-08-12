<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_evento = (int)($_POST['id_evento'] ?? 0);
$cibo = trim($_POST['cibo'] ?? '');
$quantita_input = trim($_POST['quantita'] ?? '');
$quantita = $quantita_input === '' ? null : (float)$quantita_input;

if(!$id_evento || $cibo === ''){
    echo json_encode(['success' => false]);
    exit;
}

$famiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$stmt = $conn->prepare('SELECT id FROM eventi_cibo WHERE id_famiglia = ? AND piatto = ? AND attivo = 1');
$stmt->bind_param('is', $famiglia, $cibo);
$stmt->execute();
$stmt->bind_result($id_cibo);
if(!$stmt->fetch()){
    $stmt->close();
    $stmt = $conn->prepare("INSERT INTO eventi_cibo (id_famiglia, piatto, um) VALUES (?,?, 'quantita')");
    $stmt->bind_param('is', $famiglia, $cibo);
    $stmt->execute();
    $id_cibo = $stmt->insert_id;
}
$stmt->close();

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
