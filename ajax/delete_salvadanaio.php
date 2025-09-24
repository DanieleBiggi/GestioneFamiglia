<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';

if (!has_permission($conn, 'table:salvadanai', 'delete')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}

$id = (int)($_POST['id_salvadanaio'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID non valido']);
    exit;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('DELETE FROM budget WHERE id_salvadanaio = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM eventi_eventi2salvadanai_etichette WHERE id_salvadanaio = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM salvadanai WHERE id_salvadanaio = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    if ($stmt->affected_rows === 0) {
        throw new Exception('Salvadanaio non trovato');
    }
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
