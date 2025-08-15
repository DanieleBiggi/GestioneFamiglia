<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_e2se = (int)($_POST['id_e2se'] ?? 0);
$id_salvadanaio = (int)($_POST['id_salvadanaio'] ?? 0);
$id_etichetta = (int)($_POST['id_etichetta'] ?? 0);
$id_evento = (int)($_POST['id_evento'] ?? 0);

if(!$id_e2se || !$id_salvadanaio || !$id_etichetta || !$id_evento){
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare('UPDATE eventi_eventi2salvadanai_etichette SET id_evento=?, id_salvadanaio=?, id_etichetta=? WHERE id_e2se=?');
$stmt->bind_param('iiii', $id_evento, $id_salvadanaio, $id_etichetta, $id_e2se);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
