<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:update_lista_spesa', 'update')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$id = (int)($_POST['id'] ?? 0);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
if (!$id || !$idFamiglia) { echo json_encode(['success'=>false,'error'=>'Dati non validi']); exit; }

if (isset($_POST['checked'])) {
    $checked = $_POST['checked'] == '1' ? 1 : 0;
    $stmt = $conn->prepare('UPDATE lista_spesa SET checked = ?, updated_at = NOW() WHERE id = ? AND id_famiglia = ?');
    $stmt->bind_param('iii', $checked, $id, $idFamiglia);
} else {
    $nome = trim($_POST['nome'] ?? '');
    $quantita = trim($_POST['quantita'] ?? '');
    $note = trim($_POST['note'] ?? '');
    if ($nome === '') { echo json_encode(['success'=>false,'error'=>'Nome mancante']); exit; }
    $stmt = $conn->prepare('UPDATE lista_spesa SET nome = ?, quantita = ?, note = ?, updated_at = NOW() WHERE id = ? AND id_famiglia = ?');
    $stmt->bind_param('sssii', $nome, $quantita, $note, $id, $idFamiglia);
}
$ok = $stmt->execute();
if ($ok) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'error'=>'Errore durante l\'aggiornamento']);
}
?>
