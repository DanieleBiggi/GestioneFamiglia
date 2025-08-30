<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:add_viaggi_feedback', 'insert')) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Accesso negato']);
    exit;
}
$idViaggio = (int)($_POST['id_viaggio'] ?? 0);
$voto = (($_POST['voto'] ?? '') === '' ? null : (int)$_POST['voto']);
$commento = trim($_POST['commento'] ?? '');
$idUtente = $_SESSION['utente_id'] ?? 0;
if(!$idViaggio || (!$commento && $voto === null) || !$idUtente){
    echo json_encode(['success'=>false,'error'=>'Dati non validi']);
    exit;
}
$stmt = $conn->prepare('INSERT INTO viaggi_feedback (id_viaggio, id_utente, voto, commento) VALUES (?,?,?,?)');
$stmt->bind_param('iiis', $idViaggio, $idUtente, $voto, $commento);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success'=>$ok]);
?>
