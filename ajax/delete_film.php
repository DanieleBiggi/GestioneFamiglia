<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:delete_film', 'delete')) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Accesso negato']);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true);
$idFilm = (int)($input['id'] ?? 0);
$idUtente = $_SESSION['utente_id'] ?? 0;
if (!$idFilm || !$idUtente) {
    echo json_encode(['success'=>false,'error'=>'Dati non validi']);
    exit;
}
$conn->begin_transaction();
try {
    $stmt = $conn->prepare('DELETE FROM film_commenti WHERE id_film=? AND id_utente=?');
    $stmt->bind_param('ii', $idFilm, $idUtente);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE fl FROM film2liste fl JOIN film_liste l ON fl.id_lista=l.id_lista WHERE fl.id_film=? AND l.id_utente=?');
    $stmt->bind_param('ii', $idFilm, $idUtente);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM film_utenti WHERE id_film=? AND id_utente=?');
    $stmt->bind_param('ii', $idFilm, $idUtente);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        throw new Exception('Errore durante l\'eliminazione');
    }
    $stmt->close();

    $stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM film_utenti WHERE id_film=?');
    $stmt->bind_param('i', $idFilm);
    $stmt->execute();
    $cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();

    if ($cnt == 0) {
        $stmt = $conn->prepare('DELETE FROM film WHERE id_film=?');
        $stmt->bind_param('i', $idFilm);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>
