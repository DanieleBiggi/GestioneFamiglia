<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:clear_lista_spesa', 'delete')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
if (!$idFamiglia) { echo json_encode(['success'=>false,'error'=>'Dati non validi']); exit; }
$stmt = $conn->prepare('DELETE FROM lista_spesa WHERE id_famiglia = ?');
$stmt->bind_param('i', $idFamiglia);
$ok = $stmt->execute();
echo json_encode(['success'=>$ok]);
?>
