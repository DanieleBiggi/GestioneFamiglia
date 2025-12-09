<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';

if (!has_permission($conn, 'ajax:get_menu_cene', 'view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}

$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
if (!$idFamiglia) {
    echo json_encode(['success' => false, 'error' => 'Famiglia non selezionata']);
    exit;
}

$days = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];
$menu = [];

$stmt = $conn->prepare('SELECT id, giorno, piatto FROM menu_cene_settimanale WHERE id_famiglia = ?');
$stmt->bind_param('i', $idFamiglia);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $menu[$row['giorno']] = ['id' => (int)$row['id'], 'giorno' => $row['giorno'], 'piatto' => $row['piatto'] ?? ''];
}
$stmt->close();

$insertStmt = $conn->prepare('INSERT INTO menu_cene_settimanale (id_famiglia, giorno, piatto) VALUES (?, ?, "")');

$ordered = [];
foreach ($days as $day) {
    if (!isset($menu[$day])) {
        $insertStmt->bind_param('is', $idFamiglia, $day);
        $insertStmt->execute();
        $menu[$day] = [
            'id' => $conn->insert_id,
            'giorno' => $day,
            'piatto' => ''
        ];
    }
    $ordered[] = $menu[$day];
}
$insertStmt->close();

echo json_encode(['success' => true, 'items' => $ordered]);
