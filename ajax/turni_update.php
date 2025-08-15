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
$id = isset($data['id']) ? (int)$data['id'] : 0;
$date = $data['date'] ?? null;
$idTipo = isset($data['id_tipo']) ? (int)$data['id_tipo'] : null;
$oraInizio = $data['ora_inizio'] ?? null;
$oraFine = $data['ora_fine'] ?? null;
$idBambini = $data['id_utenti_bambini'] ?? '';
$note = $data['note'] ?? '';
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

if ($id > 0) {
    if (!$idFamiglia || !$idTipo || !$oraInizio || !$oraFine) {
        echo json_encode(['success' => false]);
        exit;
    }
    $stmt = $conn->prepare('UPDATE turni_calendario SET id_tipo = ?, ora_inizio = ?, ora_fine = ?, id_utenti_bambini = ?, note = ? WHERE id = ? AND id_famiglia = ?');
    $stmt->bind_param('issssii', $idTipo, $oraInizio, $oraFine, $idBambini, $note, $id, $idFamiglia);
    $success = $stmt->execute();
    $stmt->close();
} else {
    if (!$date || !$idFamiglia) {
        echo json_encode(['success' => false]);
        exit;
    }
    if ($idTipo) {
        $stmt = $conn->prepare('INSERT INTO turni_calendario (id_famiglia, data, id_tipo) VALUES (?,?,?)');
        $stmt->bind_param('isi', $idFamiglia, $date, $idTipo);
        $success = $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare('DELETE FROM turni_calendario WHERE id_famiglia = ? AND data = ?');
        $stmt->bind_param('is', $idFamiglia, $date);
        $success = $stmt->execute();
        $stmt->close();
    }
}

echo json_encode(['success' => $success]);
