<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';

if (!has_permission($conn, 'ajax:import_menu_cene', 'insert')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}

$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
if (!$idFamiglia) {
    echo json_encode(['success' => false, 'error' => 'Famiglia non selezionata']);
    exit;
}

$raw = $_POST['items'] ?? '';
$lines = array_map('trim', preg_split('/\r?\n/', $raw));
$days = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];

$stmt = $conn->prepare('SELECT id, giorno FROM menu_cene_settimanale WHERE id_famiglia = ?');
$stmt->bind_param('i', $idFamiglia);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[$row['giorno']] = (int)$row['id'];
}
$stmt->close();

$insertStmt = $conn->prepare('INSERT INTO menu_cene_settimanale (id_famiglia, giorno, piatto) VALUES (?, ?, "")');
foreach ($days as $day) {
    if (!isset($rows[$day])) {
        $insertStmt->bind_param('is', $idFamiglia, $day);
        $insertStmt->execute();
        $rows[$day] = $conn->insert_id;
    }
}
$insertStmt->close();

$updateStmt = $conn->prepare('UPDATE menu_cene_settimanale SET piatto = ? WHERE id = ? AND id_famiglia = ?');
foreach ($days as $i => $day) {
    $piatto = $lines[$i] ?? '';
    $updateStmt->bind_param('sii', $piatto, $rows[$day], $idFamiglia);
    $updateStmt->execute();
}
$updateStmt->close();

echo json_encode(['success' => true]);
