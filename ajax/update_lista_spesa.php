<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:update_lista_spesa', 'update')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$id = (int)($_POST['id'] ?? 0);
$checked = isset($_POST['checked']) && $_POST['checked'] == '1' ? 1 : 0;
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
if (!$id || !$idFamiglia) { echo json_encode(['success'=>false,'error'=>'Dati non validi']); exit; }
$stmt = $conn->prepare('UPDATE lista_spesa SET checked = ?, updated_at = NOW() WHERE id = ? AND id_famiglia = ?');
$stmt->bind_param('iii', $checked, $id, $idFamiglia);
$ok = $stmt->execute();
if ($ok) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'error'=>'Errore durante l\'aggiornamento']);
}
?>
