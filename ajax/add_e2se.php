<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_evento = (int)($_POST['id_evento'] ?? 0);
$id_salvadanaio = (int)($_POST['id_salvadanaio'] ?? 0);
$id_etichetta = (int)($_POST['id_etichetta'] ?? 0);

if(!$id_evento || !$id_salvadanaio || !$id_etichetta){
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare('INSERT INTO eventi_eventi2salvadanai_etichette (id_evento, id_salvadanaio, id_etichetta) VALUES (?,?,?)');
$stmt->bind_param('iii', $id_evento, $id_salvadanaio, $id_etichetta);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
