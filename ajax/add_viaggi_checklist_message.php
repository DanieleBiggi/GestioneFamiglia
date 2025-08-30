<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:add_viaggi_checklist_message', 'insert')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$idChecklist = (int)($_POST['id_checklist'] ?? 0);
$messaggio = trim($_POST['messaggio'] ?? '');
$idUtente = $_SESSION['id'] ?? 0;
if (!$idChecklist || $messaggio === '' || !$idUtente) { echo json_encode(['success'=>false,'error'=>'Dati non validi']); exit; }
$stmt = $conn->prepare('INSERT INTO viaggi_checklist_messaggi (id_checklist, id_utente, messaggio) VALUES (?,?,?)');
$stmt->bind_param('iis', $idChecklist, $idUtente, $messaggio);
$ok = $stmt->execute();
echo json_encode(['success'=>$ok]);
?>
