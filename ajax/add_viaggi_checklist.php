<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:add_viaggi_checklist', 'insert')) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Accesso negato']);
    exit;
}
$idViaggio = (int)($_POST['id_viaggio'] ?? 0);
$voce = trim($_POST['voce'] ?? '');
$idUtente = ($_POST['id_utente'] === '' ? null : (int)($_POST['id_utente'] ?? 0));
if(!$idViaggio || $voce === ''){
    echo json_encode(['success'=>false,'error'=>'Dati non validi']);
    exit;
}
$stmt = $conn->prepare('INSERT INTO viaggi_checklist (id_viaggio, voce, id_utente) VALUES (?,?,?)');
$stmt->bind_param('isi', $idViaggio, $voce, $idUtente);
$ok = $stmt->execute();
$stmt->close();
echo json_encode(['success'=>$ok]);
?>
