<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once __DIR__ . '/../includes/etichette_utils.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);
$src = $data['src'] ?? '';

$allowed = [
    'bilancio_entrate' => 'id_entrata',
    'bilancio_uscite' => 'id_uscita'
];

if (!$id || !isset($allowed[$src])) {
    echo json_encode(['success' => false, 'error' => 'Parametri non validi']);
    exit;
}

$idField = $allowed[$src];
$tabellaMap = [
    'bilancio_entrate' => 'entrate',
    'bilancio_uscite'  => 'uscite'
];
$tabellaOperazione = $tabellaMap[$src];

if (!checkIdTabellaEsiste($tabellaOperazione, $id)) {
    echo json_encode(['success' => false, 'error' => 'ID non esistente']);
    exit;
}

$sql = "DELETE FROM $src WHERE $idField = ? AND mezzo = 'contanti'";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$success = $stmt->execute();
if ($success) {
    eliminaEtichetteCollegate($src, $id);
}

echo json_encode(['success' => $success]);

