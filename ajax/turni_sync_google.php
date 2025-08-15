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
// Query turni
// Periodo
$periodStart = sprintf('%04d-%02d-01', $year, $month);
$periodEnd   = date('Y-m-t', strtotime($periodStart));

// Query turni
$stmt = $conn->prepare(
  'SELECT t.id, t.data, t.id_tipo, t.ora_inizio, t.ora_fine, t.google_calendar_eventid,
          tp.descrizione, tp.colore_bg, tp.colore_testo,
          tp.ora_inizio AS tp_ora_inizio, tp.ora_fine AS tp_ora_fine
   FROM turni_calendario t
   JOIN turni_tipi tp ON t.id_tipo = tp.id
   WHERE t.id_famiglia = ?
     AND t.data BETWEEN ? AND ?
   ORDER BY t.data'
);
$stmt->bind_param('iss', $idFamiglia, $periodStart, $periodEnd);

$stmt->execute();
$res = $stmt->get_result();
$turni = [];
while ($row = $res->fetch_assoc()) {
    $turni[] = $row;
}
$stmt->close();

// Query eventi
$evStmt = $conn->prepare(
  'SELECT e.id, e.titolo, e.data_evento, e.ora_evento, e.google_calendar_eventid
   FROM eventi e
   JOIN eventi_eventi2famiglie f ON e.id = f.id_evento
   WHERE f.id_famiglia = ? AND e.data_evento BETWEEN ? AND ?
   ORDER BY e.data_evento'
);
$evStmt->bind_param('iss', $idFamiglia, $periodStart, $periodEnd);
$evStmt->execute();
$evRes = $evStmt->get_result();
$eventiDb = [];
$eventiByGcId = [];
while ($row = $evRes->fetch_assoc()) {
    $eventiDb[] = $row;
    if (!empty($row['google_calendar_eventid'])) {
        $eventiByGcId[$row['google_calendar_eventid']] = $row['id'];
    }
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
    $calendarIdTurni = '405f4721b468b439755b6aad55d6b40e5c235f8511db6a29b95dc7f96ff329f0@group.calendar.google.com';
    $calendarIdEventi = '29f6c24acdde6722ed7eb92a5eff3d15bdc1a46d210def3314c1b05c15ac024f@group.calendar.google.com';
    
    // Timezone fisso
    $timeZone = 'Europe/Rome';
    
    // Helper: normalizza "HH:MM" -> "HH:MM:00"
    $normTime = function($t) {
        if (!$t) return null;
        if (preg_match('/^\d{2}:\d{2}$/', $t)) return $t . ':00';
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) return $t;
        return null; // formato non valido
    };
    
    // All-day: end = giorno successivo
    $endAllDay = function(string $date) {
        return date('Y-m-d', strtotime($date . ' +1 day'));
    };
    
    // Sync turni: DB -> Google Calendar
    foreach ($turni as $t) {
        $date = $t['data'];
        // orari dal turno, fallback al tipo
        $startTime = $normTime($t['ora_inizio'] ?? null) ?: $normTime($t['tp_ora_inizio'] ?? null);
        $endTime   = $normTime($t['ora_fine']   ?? null) ?: $normTime($t['tp_ora_fine']   ?? null);

        if ($startTime && $endTime) {
            $evStart = ['dateTime' => $date . 'T' . $startTime, 'timeZone' => $timeZone];
            $evEnd   = ['dateTime' => $date . 'T' . $endTime,   'timeZone' => $timeZone];
        } else {
            $evStart = ['date' => $date];
            $evEnd   = ['date' => $endAllDay($date)];
        }

        $eventData = [
            'summary' => $t['descrizione'],
            'start'   => $evStart,
            'end'     => $evEnd,
        ];

        if (!empty($t['google_calendar_eventid'])) {
            try {
                $service->events->patch($calendarIdTurni, $t['google_calendar_eventid'], new Google_Service_Calendar_Event($eventData));
                $upd = $conn->prepare('UPDATE turni_calendario SET data_ultima_sincronizzazione=NOW() WHERE id=?');
                $upd->bind_param('i', $t['id']);
                $upd->execute();
                $upd->close();
            } catch (Google_Service_Exception $e) {
                if ($e->getCode() != 404) throw $e;
                $created = $service->events->insert($calendarIdTurni, new Google_Service_Calendar_Event($eventData));
                $newId = $created->getId();
                $upd = $conn->prepare('UPDATE turni_calendario SET google_calendar_eventid=?, data_ultima_sincronizzazione=NOW() WHERE id=?');
                $upd->bind_param('si', $newId, $t['id']);
                $upd->execute();
                $upd->close();
            }
        } else {
            $created = $service->events->insert($calendarIdTurni, new Google_Service_Calendar_Event($eventData));
            $newId = $created->getId();
            $upd = $conn->prepare('UPDATE turni_calendario SET google_calendar_eventid=?, data_ultima_sincronizzazione=NOW() WHERE id=?');
            $upd->bind_param('si', $newId, $t['id']);
            $upd->execute();
            $upd->close();
        }
    }

    // Insert DB eventi without calendar id into Google Calendar
    foreach ($eventiDb as $e) {
        if (empty($e['google_calendar_eventid'])) {
            $date = $e['data_evento'];
            $evtStartTime = $normTime($e['ora_evento'] ?? null);
            if ($evtStartTime) {
                $start = $date . 'T' . $evtStartTime;
                $end   = date('Y-m-d\TH:i:s', strtotime($start . ' +1 hour'));
                $evStart = ['dateTime' => $start, 'timeZone' => $timeZone];
                $evEnd   = ['dateTime' => $end,   'timeZone' => $timeZone];
            } else {
                $evStart = ['date' => $date];
                $evEnd   = ['date' => $endAllDay($date)];
            }

            $eventData = [
                'summary' => $e['titolo'],
                'start'   => $evStart,
                'end'     => $evEnd,
                'extendedProperties' => ['private' => ['source' => 'gestione-famiglia', 'type' => 'evento']]
            ];

            $created = $service->events->insert($calendarIdEventi, new Google_Service_Calendar_Event($eventData));
            $gcId = $created->getId();
            $upd = $conn->prepare('UPDATE eventi SET google_calendar_eventid=? WHERE id=?');
            $upd->bind_param('si', $gcId, $e['id']);
            $upd->execute();
            $upd->close();
            $eventiByGcId[$gcId] = $e['id'];
        }
    }

    // Fetch Google Calendar events and sync to DB
    $params = [
        'singleEvents' => true,
        'timeMin' => $periodStart . 'T00:00:00Z',
        'timeMax' => $periodEnd . 'T23:59:59Z'
    ];
    $gEvents = $service->events->listEvents($calendarIdEventi, $params);
    while (true) {
        foreach ($gEvents->getItems() as $gEvent) {
            $gcId = $gEvent->getId();
            $summary = $gEvent->getSummary();
            $startObj = $gEvent->getStart();
            if ($startObj->getDateTime()) {
                $dt = new DateTime($startObj->getDateTime());
                $date = $dt->format('Y-m-d');
                $time = $dt->format('H:i');
            } else {
                $date = $startObj->getDate();
                $time = null;
            }

            if (isset($eventiByGcId[$gcId])) {
                $dbId = $eventiByGcId[$gcId];
                $upd = $conn->prepare('UPDATE eventi SET titolo=?, data_evento=?, ora_evento=? WHERE id=?');
                $upd->bind_param('sssi', $summary, $date, $time, $dbId);
                $upd->execute();
                $upd->close();
            } else {
                $ins = $conn->prepare('INSERT INTO eventi (titolo, data_evento, ora_evento, google_calendar_eventid) VALUES (?,?,?,?)');
                $ins->bind_param('ssss', $summary, $date, $time, $gcId);
                $ins->execute();
                $newId = $ins->insert_id;
                $ins->close();

                $link = $conn->prepare('INSERT INTO eventi_eventi2famiglie (id_evento, id_famiglia) VALUES (?,?)');
                $link->bind_param('ii', $newId, $idFamiglia);
                $link->execute();
                $link->close();
            }
        }
        $pageToken = $gEvents->getNextPageToken();
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
            $gEvents = $service->events->listEvents($calendarIdEventi, $params);
        } else {
            break;
        }
    }


    echo json_encode(['success' => true, 'message' => 'Sincronizzazione completata']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
