<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$idMovimento = intval($input['id_movimento'] ?? 0);
$src = $input['src'] ?? '';
$idCaricamento = intval($input['id_caricamento'] ?? 0);

$allowedSources = ['movimenti_revolut','bilancio_entrate','bilancio_uscite'];
if (!$idMovimento || !$idCaricamento || !in_array($src, $allowedSources, true)) {
    echo json_encode(['success' => false, 'error' => 'Parametri non validi']);
    exit;
}

$idFields = [
    'movimenti_revolut' => 'id_movimento_revolut',
    'bilancio_entrate' => 'id_entrata',
    'bilancio_uscite' => 'id_uscita',
];
$stmt = $conn->prepare("UPDATE $src SET id_caricamento=? WHERE {$idFields[$src]}=?");
$stmt->bind_param('ii', $idCaricamento, $idMovimento);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
