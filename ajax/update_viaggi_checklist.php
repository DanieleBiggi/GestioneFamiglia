<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:update_viaggi_checklist', 'update')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$id = (int)($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['success'=>false,'error'=>'ID mancante']); exit; }
if (isset($_POST['completata'])) {
    $completata = $_POST['completata'] == '1' ? 1 : 0;
    $stmt = $conn->prepare('UPDATE viaggi_checklist SET completata=? WHERE id_checklist=?');
    $stmt->bind_param('ii', $completata, $id);
} elseif (isset($_POST['id_utente'])) {
    if ($_POST['id_utente'] === '') {
        $stmt = $conn->prepare('UPDATE viaggi_checklist SET id_utente=NULL WHERE id_checklist=?');
        $stmt->bind_param('i', $id);
    } else {
        $idUtente = (int)$_POST['id_utente'];
        $stmt = $conn->prepare('UPDATE viaggi_checklist SET id_utente=? WHERE id_checklist=?');
        $stmt->bind_param('ii', $idUtente, $id);
    }
} else {
    echo json_encode(['success'=>false,'error'=>'Dati non validi']);
    exit;
}
$ok = $stmt->execute();
echo json_encode(['success'=>$ok]);
?>
