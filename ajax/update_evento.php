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
$famiglie = $_POST['famiglie'] ?? [];
if (!is_array($famiglie)) { $famiglie = []; }

if (!$id) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare('UPDATE eventi SET titolo = ?, descrizione = ?, data_evento = ?, ora_evento = ?, id_tipo_evento = ? WHERE id = ?');
$stmt->bind_param('ssssii', $titolo, $descrizione, $data_evento, $ora_evento, $id_tipo_evento, $id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    $stmtDel = $conn->prepare('DELETE FROM eventi_eventi2famiglie WHERE id_evento = ?');
    $stmtDel->bind_param('i', $id);
    $stmtDel->execute();
    $stmtDel->close();

    if ($famiglie) {
        $stmtIns = $conn->prepare('INSERT INTO eventi_eventi2famiglie (id_evento, id_famiglia) VALUES (?,?)');
        foreach ($famiglie as $fid) {
            $fid = (int)$fid;
            if ($fid > 0) {
                $stmtIns->bind_param('ii', $id, $fid);
                $stmtIns->execute();
            }
        }
        $stmtIns->close();
    }
}

echo json_encode(['success' => $success]);
