<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:add_viaggi_alternativa', 'insert')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$idViaggio = (int)($_POST['id_viaggio'] ?? 0);
$desc = trim($_POST['breve_descrizione'] ?? '');
if (!$idViaggio || $desc === '') { echo json_encode(['success'=>false,'error'=>'Dati mancanti']); exit; }
$stmt = $conn->prepare('INSERT INTO viaggi_alternative (id_viaggio, breve_descrizione) VALUES (?,?)');
$stmt->bind_param('is', $idViaggio, $desc);
$ok = $stmt->execute();
echo json_encode(['success'=>$ok]);
?>
