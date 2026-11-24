<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
require_once '../includes/menu_cene_relazionale.php';
$conn->set_charset('utf8mb4');

if (!has_permission($conn, 'ajax:update_menu_cena', 'update')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$piatto = trim($_POST['piatto'] ?? '');
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

if (!$id || !$idFamiglia) {
    echo json_encode(['success' => false, 'error' => 'Dati non validi']);
    exit;
}

$conn->begin_transaction();
try {
    menu_rel_lock_family($conn, $idFamiglia);
    $stmt = $conn->prepare('UPDATE menu_cene_settimanale SET piatto = ? WHERE id = ? AND id_famiglia = ?');
    $stmt->bind_param('sii', $piatto, $id, $idFamiglia);
    $stmt->execute();
    $stmt->close();
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Modifica cena: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore durante il salvataggio']);
}
