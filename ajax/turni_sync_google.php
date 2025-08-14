<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
include '../includes/permissions.php';
if (!has_permission($conn, 'page:turni.php', 'view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accesso negato']);
    exit;
}
$data = json_decode(file_get_contents('php://input'), true);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$year = isset($data['year']) ? (int)$data['year'] : 0;
$month = isset($data['month']) ? (int)$data['month'] : -1; // 0-based
if (!$idFamiglia || !$year || $month < 0) {
    echo json_encode(['success' => false, 'message' => 'Parametri non validi']);
    exit;
}
$month++; // convert to 1-based
$start = sprintf('%04d-%02d-01', $year, $month);
$end = date('Y-m-t', strtotime($start));
// Query turni
$stmt = $conn->prepare('SELECT t.data, t.id_tipo, tp.descrizione, tp.colore_bg, tp.colore_testo FROM turni_calendario t JOIN turni_tipi tp ON t.id_tipo = tp.id WHERE t.id_famiglia = ? AND t.data BETWEEN ? AND ? ORDER BY t.data');
$stmt->bind_param('iss', $idFamiglia, $start, $end);
$stmt->execute();
$res = $stmt->get_result();
$turni = [];
while ($row = $res->fetch_assoc()) {
    $turni[$row['data']][] = $row;
}
$stmt->close();
// Query eventi
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
// Google Calendar integration
require_once __DIR__ . '/../vendor/autoload.php';
$credentialsFile = __DIR__ . '/../config/google_credentials.json';
$tokenFile = __DIR__ . '/../config/google_token.json';
if (!file_exists($credentialsFile)) {
    echo json_encode(['success' => false, 'message' => 'Credenziali Google mancanti']);
    exit;
}
try {
    $client = new Google_Client();
    $client->setAuthConfig($credentialsFile);
    $client->addScope(Google_Service_Calendar::CALENDAR);
    $client->setAccessType('offline');
    if (file_exists($tokenFile)) {
        $accessToken = json_decode(file_get_contents($tokenFile), true);
        $client->setAccessToken($accessToken);
        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents($tokenFile, json_encode($client->getAccessToken()));
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Token Google mancante']);
        exit;
    }
    $service = new Google_Service_Calendar($client);
    $calendarIdTurni = getenv('GOOGLE_CALENDAR_ID') ?: 'primary';
    $calendarIdEventi = getenv('GOOGLE_CALENDAR_ID') ?: 'primary';
    foreach ($turni as $date => $items) {
        foreach ($items as $t) {
            $event = new Google_Service_Calendar_Event([
                'summary' => $t['descrizione'],
                'start' => ['date' => $date],
                'end' => ['date' => $date],
            ]);
            $service->events->insert($calendarIdTurni, $event);
        }
    }
    foreach ($eventi as $date => $items) {
        foreach ($items as $e) {
            $event = new Google_Service_Calendar_Event([
                'summary' => $e['titolo'],
                'start' => ['date' => $date],
                'end' => ['date' => $date],
            ]);
            $service->events->insert($calendarIdEventi, $event);
        }
    }
    echo json_encode(['success' => true, 'message' => 'Sincronizzazione completata']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
