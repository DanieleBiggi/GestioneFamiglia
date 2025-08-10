<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:add_lista_spesa', 'insert')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$nome = trim($_POST['nome'] ?? '');
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
if ($nome === '' || !$idFamiglia) { echo json_encode(['success'=>false,'error'=>'Dati non validi']); exit; }
$stmt = $conn->prepare('INSERT INTO lista_spesa (id_famiglia, nome) VALUES (?, ?)');
$stmt->bind_param('is', $idFamiglia, $nome);
$ok = $stmt->execute();
if ($ok) {
    echo json_encode(['success'=>true, 'id'=>$conn->insert_id]);
} else {
    echo json_encode(['success'=>false,'error'=>'Errore durante l\'inserimento']);
}
?>
