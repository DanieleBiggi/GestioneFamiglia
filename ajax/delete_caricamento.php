<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
include '../includes/permissions.php';

if (!has_permission($conn, 'table:ocr_caricamenti', 'delete')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$idUtente = $_SESSION['utente_id'] ?? 0;
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID mancante']);
    exit;
}

$stmt = $conn->prepare('SELECT nome_file FROM ocr_caricamenti WHERE id_caricamento=? AND id_utente=?');
$stmt->bind_param('ii', $id, $idUtente);
$stmt->execute();
$stmt->bind_result($nomeFile);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Record non trovato']);
    $stmt->close();
    exit;
}
$stmt->close();

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('DELETE FROM ocr_caricamenti WHERE id_caricamento=? AND id_utente=?');
    $stmt->bind_param('ii', $id, $idUtente);
    $stmt->execute();
    $stmt->close();
    $conn->commit();
    if ($nomeFile) {
        @unlink(__DIR__ . '/../files/scontrini/' . $nomeFile);
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
