<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:get_viaggi_checklist_messages', 'view')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$id = (int)($_GET['id_checklist'] ?? 0);
if (!$id) { echo json_encode(['success'=>false,'error'=>'ID mancante']); exit; }
$stmt = $conn->prepare('SELECT vcm.id_messaggio, vcm.messaggio, vcm.creato_il, u.username FROM viaggi_checklist_messaggi vcm LEFT JOIN utenti u ON vcm.id_utente=u.id WHERE vcm.id_checklist=? ORDER BY vcm.creato_il');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$messages = [];
while ($r = $res->fetch_assoc()) {
    $messages[] = ['id'=>$r['id_messaggio'], 'messaggio'=>$r['messaggio'], 'username'=>$r['username'], 'creato_il'=>$r['creato_il']];
}
echo json_encode(['success'=>true,'messages'=>$messages]);
?>
