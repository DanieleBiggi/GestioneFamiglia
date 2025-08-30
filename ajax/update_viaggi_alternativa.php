<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:update_viaggi_alternativa', 'update')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$idAlt = (int)($_POST['id_viaggio_alternativa'] ?? 0);
$desc = trim($_POST['breve_descrizione'] ?? '');
if (!$idAlt || $desc === '') { echo json_encode(['success'=>false,'error'=>'Dati mancanti']); exit; }
$stmt = $conn->prepare('UPDATE viaggi_alternative SET breve_descrizione=? WHERE id_viaggio_alternativa=?');
$stmt->bind_param('si', $desc, $idAlt);
$ok = $stmt->execute();
echo json_encode(['success'=>$ok]);
?>
