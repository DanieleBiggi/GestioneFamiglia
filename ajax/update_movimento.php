<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$data = json_decode(file_get_contents("php://input"), true);

$id = intval($data['id'] ?? 0);
$field = $data['field'] ?? '';
$value = trim($data['value'] ?? '');

// Lista campi modificabili per sicurezza
$allowed_fields = ['descrizione_extra', 'note'];

if (!$id || !in_array($field, $allowed_fields)) {
    echo json_encode(['success' => false, 'error' => 'Parametro non valido.']);
    exit;
}

$sql = "UPDATE movimenti_revolut SET $field = ? WHERE id_movimento_revolut = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $value, $id);
$success = $stmt->execute();

echo json_encode(['success' => $success]);