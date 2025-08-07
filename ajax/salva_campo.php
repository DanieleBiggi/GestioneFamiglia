<?php
include '../includes/session_check.php';
require_once '../includes/db.php';

$id = intval($_POST['id'] ?? 0);
$campo = $_POST['campo'];
$valore = $_POST['valore'];

$allowed = ['note', 'descrizione_extra', 'id_gruppo_transazione'];
if (!in_array($campo, $allowed)) exit;

$stmt = $conn->prepare("UPDATE movimenti_revolut SET $campo = ? WHERE id_movimento_revolut = ?");
$stmt->bind_param("si", $valore, $id);
$stmt->execute();

header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit;
