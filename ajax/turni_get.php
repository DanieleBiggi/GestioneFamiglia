<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
include '../includes/permissions.php';
if (!has_permission($conn, 'page:turni.php', 'view')) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$year = (int)($_GET['year'] ?? 0);
$month = (int)($_GET['month'] ?? 0);
if (!$year || !$month || !$idFamiglia) {
    echo json_encode([]);
    exit;
}
$start = sprintf('%04d-%02d-01', $year, $month);
$end = date('Y-m-t', strtotime($start));
$stmt = $conn->prepare('SELECT t.id, t.data, t.id_tipo, t.ora_inizio, t.ora_fine, tp.descrizione, tp.colore_bg, tp.colore_testo FROM turni_calendario t JOIN turni_tipi tp ON t.id_tipo = tp.id WHERE t.id_famiglia = ? AND t.data BETWEEN ? AND ? ORDER BY t.data');
$stmt->bind_param('iss', $idFamiglia, $start, $end);
$stmt->execute();
$res = $stmt->get_result();
$turni = [];
while ($row = $res->fetch_assoc()) {
    $turni[$row['data']][] = $row;
}
$stmt->close();

$evStmt = $conn->prepare('SELECT e.id, e.titolo, e.data_evento, te.colore FROM eventi e JOIN eventi_eventi2famiglie f ON e.id = f.id_evento LEFT JOIN eventi_tipi_eventi te ON e.id_tipo_evento = te.id WHERE f.id_famiglia = ? AND e.data_evento BETWEEN ? AND ? ORDER BY e.data_evento');
$evStmt->bind_param('iss', $idFamiglia, $start, $end);
$evStmt->execute();
$evRes = $evStmt->get_result();
$eventi = [];
while ($row = $evRes->fetch_assoc()) {
    $eventi[$row['data_evento']][] = [
        'id' => (int)$row['id'],
        'titolo' => $row['titolo'],
        'colore' => $row['colore']
    ];
}
$evStmt->close();

echo json_encode(['turni' => $turni, 'eventi' => $eventi]);
