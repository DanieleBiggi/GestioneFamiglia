<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:get_lista_spesa', 'view')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$stmt = $conn->prepare('SELECT id, nome, checked FROM lista_spesa WHERE id_famiglia = ? ORDER BY checked ASC, created_at DESC');
$stmt->bind_param('i', $idFamiglia);
$stmt->execute();
$res = $stmt->get_result();
$items = $res->fetch_all(MYSQLI_ASSOC);
echo json_encode(['success'=>true, 'items'=>$items]);
?>
