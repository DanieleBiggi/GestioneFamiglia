<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:add_film_commento', 'insert')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$idFilm = (int)($_POST['id_film'] ?? 0);
$commento = trim($_POST['commento'] ?? '');
$idUtente = $_SESSION['utente_id'] ?? 0;
if (!$idFilm || $commento === '' || !$idUtente) { echo json_encode(['success'=>false,'error'=>'Dati non validi']); exit; }
$stmt = $conn->prepare('INSERT INTO film_commenti (id_film, id_utente, commento) VALUES (?,?,?)');
$stmt->bind_param('iis', $idFilm, $idUtente, $commento);
if ($stmt->execute()) {
    echo json_encode(['success'=>true, 'id'=>$conn->insert_id]);
} else {
    echo json_encode(['success'=>false,'error'=>'Errore durante l\'inserimento']);
}
?>
