<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
include '../includes/permissions.php';
if (!has_permission($conn, 'table:turni_calendario', 'update')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}
$data = json_decode(file_get_contents('php://input'), true);
$date = $data['date'] ?? null;
$idTipo = isset($data['id_tipo']) ? (int)$data['id_tipo'] : null;
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
if (!$date || !$idFamiglia) {
    echo json_encode(['success' => false]);
    exit;
}
if ($idTipo) {
    $stmt = $conn->prepare('INSERT INTO turni_calendario (id_famiglia, data, id_tipo) VALUES (?,?,?) ON DUPLICATE KEY UPDATE id_tipo = VALUES(id_tipo)');
    $stmt->bind_param('isi', $idFamiglia, $date, $idTipo);
    $success = $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare('DELETE FROM turni_calendario WHERE id_famiglia = ? AND data = ?');
    $stmt->bind_param('is', $idFamiglia, $date);
    $success = $stmt->execute();
    $stmt->close();
}
echo json_encode(['success' => $success]);
