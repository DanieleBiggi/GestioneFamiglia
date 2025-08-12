<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
include '../includes/permissions.php';

if (!has_permission($conn, 'table:eventi', 'update')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$titolo = trim($_POST['titolo'] ?? '');
$descrizione = trim($_POST['descrizione'] ?? '');
$data_evento = $_POST['data_evento'] ?? null;
$ora_evento = $_POST['ora_evento'] ?? null;
$id_tipo_evento = (int)($_POST['id_tipo_evento'] ?? 0);
if ($id_tipo_evento === 0) { $id_tipo_evento = null; }

if (!$id) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare('UPDATE eventi SET titolo = ?, descrizione = ?, data_evento = ?, ora_evento = ?, id_tipo_evento = ? WHERE id = ?');
$stmt->bind_param('ssssii', $titolo, $descrizione, $data_evento, $ora_evento, $id_tipo_evento, $id);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
