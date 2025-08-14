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
$stmt = $conn->prepare('SELECT t.id, t.data, t.id_tipo, t.inizio_turno, t.fine_turno, tp.descrizione, tp.colore_bg, tp.colore_testo, tp.inizio_turno AS tp_inizio_turno, tp.fine_turno AS tp_fine_turno FROM turni_calendario t JOIN turni_tipi tp ON t.id_tipo = tp.id WHERE t.id_famiglia = ? AND t.data BETWEEN ? AND ? ORDER BY t.data');
$stmt->bind_param('iss', $idFamiglia, $start, $end);
$stmt->execute();
$res = $stmt->get_result();
$turni = [];
while ($row = $res->fetch_assoc()) {
    $turni[$row['data']][] = $row;
}
$stmt->close();
// Query eventi
$evStmt = $conn->prepare('SELECT e.id, e.titolo, e.data_evento, e.ora_evento, te.colore FROM eventi e JOIN eventi_eventi2famiglie f ON e.id = f.id_evento LEFT JOIN eventi_tipi_eventi te ON e.id_tipo_evento = te.id WHERE f.id_famiglia = ? AND e.data_evento BETWEEN ? AND ? ORDER BY e.data_evento');
$evStmt->bind_param('iss', $idFamiglia, $start, $end);
$evStmt->execute();
$evRes = $evStmt->get_result();
$eventi = [];
while ($row = $evRes->fetch_assoc()) {
    $eventi[$row['data_evento']][] = [
        'id' => (int)$row['id'],
        'titolo' => $row['titolo'],
        'colore' => $row['colore'],
        'ora_evento' => $row['ora_evento']
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
    $timeZone = date_default_timezone_get();
    foreach ($turni as $date => $items) {
        foreach ($items as $t) {
            $startTime = $t['inizio_turno'] ?: $t['tp_inizio_turno'];
            $endTime = $t['fine_turno'] ?: $t['tp_fine_turno'];
            if ($startTime && $endTime) {
                $start = ['dateTime' => $date . 'T' . $startTime, 'timeZone' => $timeZone];
                $end = ['dateTime' => $date . 'T' . $endTime, 'timeZone' => $timeZone];
            } else {
                $start = ['date' => $date];
                $end = ['date' => $date];
            }
            $eventData = [
                'summary' => $t['descrizione'],
                'start' => $start,
                'end' => $end,
            ];
            $eventId = 'turno' . $t['id'];
            $event = new Google_Service_Calendar_Event($eventData);
            try {
                $service->events->patch($calendarIdTurni, $eventId, $event);
            } catch (Exception $ex) {
                if ($ex instanceof Google_Service_Exception && $ex->getCode() == 404) {
                    $eventData['id'] = $eventId;
                    $event = new Google_Service_Calendar_Event($eventData);
                    $service->events->insert($calendarIdTurni, $event);
                } else {
                    throw $ex;
                }
            }
        }
    }
    foreach ($eventi as $date => $items) {
        foreach ($items as $e) {
            $eventId = 'evento' . $e['id'];
            if ($e['ora_evento']) {
                $startDateTime = $date . 'T' . $e['ora_evento'];
                $endDateTime = date('Y-m-d\TH:i:s', strtotime($startDateTime . ' +1 hour'));
                $start = ['dateTime' => $startDateTime, 'timeZone' => $timeZone];
                $end = ['dateTime' => $endDateTime, 'timeZone' => $timeZone];
            } else {
                $start = ['date' => $date];
                $end = ['date' => $date];
            }
            $eventData = [
                'summary' => $e['titolo'],
                'start' => $start,
                'end' => $end,
            ];
            $event = new Google_Service_Calendar_Event($eventData);
            try {
                $service->events->patch($calendarIdEventi, $eventId, $event);
            } catch (Exception $ex) {
                if ($ex instanceof Google_Service_Exception && $ex->getCode() == 404) {
                    $eventData['id'] = $eventId;
                    $event = new Google_Service_Calendar_Event($eventData);
                    $service->events->insert($calendarIdEventi, $event);
                } else {
                    throw $ex;
                }
            }
        }
    }
    echo json_encode(['success' => true, 'message' => 'Sincronizzazione completata']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
