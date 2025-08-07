<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);
$field = $data['field'] ?? '';
$value = trim($data['value'] ?? '');
$src = $data['src'] ?? 'movimenti_revolut';

$allowedSources = ['movimenti_revolut', 'bilancio_entrate', 'bilancio_uscite'];
$allowedFields = [
  'movimenti_revolut' => ['descrizione_extra', 'note', 'id_gruppo_transazione'],
  'bilancio_entrate'  => ['descrizione_extra', 'note', 'id_gruppo_transazione'],
  'bilancio_uscite'   => ['descrizione_extra', 'note', 'id_gruppo_transazione']
];

if (!$id || !in_array($src, $allowedSources, true) || !in_array($field, $allowedFields[$src])) {
  echo json_encode(['success' => false, 'error' => 'Parametri non validi']);
  exit;
}

$idFields = [
  'movimenti_revolut' => 'id_movimento_revolut',
  'bilancio_entrate'  => 'id_entrata',
  'bilancio_uscite'   => 'id_uscita'
];

$sql = "UPDATE $src SET $field = ? WHERE {$idFields[$src]} = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('si', $value, $id);
$success = $stmt->execute();

echo json_encode(['success' => $success]);
