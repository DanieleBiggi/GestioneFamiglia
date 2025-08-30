<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'table:viaggi', 'update')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$idViaggio = (int)($_POST['id_viaggio'] ?? 0);
$note = $_POST['note'] ?? '';
if (!$idViaggio) { echo json_encode(['success'=>false,'error'=>'ID mancante']); exit; }
$stmt = $conn->prepare('UPDATE viaggi SET note=? WHERE id_viaggio=?');
$stmt->bind_param('si', $note, $idViaggio);
$ok = $stmt->execute();
echo json_encode(['success'=>$ok]);
?>
