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
$stmt = $conn->prepare('SELECT t.id, t.data, t.id_tipo,
    IF(t.ora_inizio = "00:00:00", tp.ora_inizio, t.ora_inizio) AS ora_inizio,
    IF(t.ora_fine = "00:00:00", tp.ora_fine, t.ora_fine) AS ora_fine,
    t.id_utenti_bambini, t.note,
    tp.descrizione, tp.colore_bg, tp.colore_testo
    FROM turni_calendario t JOIN turni_tipi tp ON t.id_tipo = tp.id
    WHERE t.id_famiglia = ? AND t.data BETWEEN ? AND ? ORDER BY t.data');
$stmt->bind_param('iss', $idFamiglia, $start, $end);
$stmt->execute();
$res = $stmt->get_result();
$userMap = [];
$uRes = $conn->query("SELECT u.id, COALESCE(NULLIF(u.soprannome,''), CONCAT(u.nome,' ',u.cognome)) AS nome FROM utenti u JOIN utenti2famiglie uf ON u.id = uf.id_utente WHERE uf.id_famiglia = $idFamiglia");
if($uRes){
    while($u = $uRes->fetch_assoc()){
        $userMap[$u['id']] = $u['nome'];
    }
}
$turni = [];
while ($row = $res->fetch_assoc()) {
    $ids = array_filter(array_map('trim', explode(',', $row['id_utenti_bambini'] ?? '')));
    $iniziali = [];
    foreach($ids as $id){
        if(isset($userMap[$id])){
            $parts = preg_split('/\s+/', $userMap[$id]);
            $ini = '';
            foreach($parts as $p){ $ini .= mb_substr($p,0,1); }
            $iniziali[] = strtoupper($ini);
        }
    }
    $row['iniziali_bambini'] = implode('', $iniziali);
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
