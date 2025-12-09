<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';

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

$stmt = $conn->prepare('UPDATE menu_cene_settimanale SET piatto = ? WHERE id = ? AND id_famiglia = ?');
$stmt->bind_param('sii', $piatto, $id, $idFamiglia);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok]);
