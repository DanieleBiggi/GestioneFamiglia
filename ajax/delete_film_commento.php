<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:delete_film_commento', 'delete')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$id = (int)($_POST['id'] ?? 0);
$idUtente = $_SESSION['utente_id'] ?? 0;
if (!$id || !$idUtente) { echo json_encode(['success'=>false,'error'=>'Dati non validi']); exit; }
$stmt = $conn->prepare('DELETE FROM film_commenti WHERE id=? AND id_utente=?');
$stmt->bind_param('ii', $id, $idUtente);
if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'error'=>'Errore durante l\'eliminazione']);
}
?>
