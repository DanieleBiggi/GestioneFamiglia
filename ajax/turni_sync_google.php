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

// Query turni  (nomi colonne corretti + alias coerenti)
$stmt = $conn->prepare(
  'SELECT t.id, t.data, t.id_tipo, t.ora_inizio, t.ora_fine,
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
    $turni[$row['data']][] = $row;
}
$stmt->close();
// Query eventi
$evStmt = $conn->prepare(
  'SELECT e.id, e.titolo, e.data_evento, e.ora_evento, te.colore
   FROM eventi e
   JOIN eventi_eventi2famiglie f ON e.id = f.id_evento
   LEFT JOIN eventi_tipi_eventi te ON e.id_tipo_evento = te.id
   WHERE f.id_famiglia = ? AND e.data_evento BETWEEN ? AND ?
   ORDER BY e.data_evento'
);
$evStmt->bind_param('iss', $idFamiglia, $periodStart, $periodEnd);
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
    
    $makeEventId = function(string $prefix, $raw) {
        // 1) base: prefisso + valore grezzo
        $id = strtolower($prefix . '-' . (string)$raw);
        // 2) tieni solo [a-z0-9_-]
        $id = preg_replace('/[^a-z0-9_-]+/', '-', $id);
        // 3) trim dei trattini ripetuti / agli estremi
        $id = trim(preg_replace('/-+/', '-', $id), '-');
        // 4) lunghezza minima 5
        if (strlen($id) < 5) $id = str_pad($id, 5, 'x');
        return $id;
    };
    
    $upsert = function(Google_Service_Calendar $svc, string $calId, string $eventId, array $eventData) {
      if ($eventId === '' || $eventId === null) {
        throw new Exception('eventId vuoto nel upsert');
      }
      try {
        // esiste?
        $svc->events->get($calId, $eventId);
        // aggiorna
        $svc->events->patch($calId, $eventId, new Google_Service_Calendar_Event($eventData));
      } catch (Google_Service_Exception $e) {
        if ($e->getCode() !== 404) {
          error_log("Google GET error for eventId=$eventId: ".$e->getMessage());
          throw $e;
        }
        // inserisci con ID esplicito
        $eventData['id'] = $eventId;
        $svc->events->insert($calId, new Google_Service_Calendar_Event($eventData));
      }
    };


    
    foreach ($turni as $date => $items) {
        foreach ($items as $t) {
            // orari dal turno, fallback al tipo
            $startTime = $normTime($t['ora_inizio'] ?? null) ?: $normTime($t['tp_ora_inizio'] ?? null);
            $endTime   = $normTime($t['ora_fine']   ?? null) ?: $normTime($t['tp_ora_fine']   ?? null);
    
            if ($startTime && $endTime) {
                $evStart = ['dateTime' => $date . 'T' . $startTime, 'timeZone' => $timeZone];
                $evEnd   = ['dateTime' => $date . 'T' . $endTime,   'timeZone' => $timeZone];
            } else {
                // all-day corretto
                $evStart = ['date' => $date];
                $evEnd   = ['date' => $endAllDay($date)];
            }
    
            $eventData = [
                'summary' => $t['descrizione'],
                'start'   => $evStart,
                'end'     => $evEnd,
            ];
    
            $eventId = $makeEventId('turno', $t['id']);
            $upsert($service, $calendarIdTurni, $eventId, $eventData);
        }
    }

    foreach ($eventi as $date => $items) {
      foreach ($items as $e) {
        $evtStartTime = $normTime($e['ora_evento'] ?? null);
        if ($evtStartTime) {
          $start = $date.'T'.$evtStartTime;
          $end   = date('Y-m-d\TH:i:s', strtotime($start.' +1 hour'));
          $evStart = ['dateTime'=>$start, 'timeZone'=>$timeZone];
          $evEnd   = ['dateTime'=>$end,   'timeZone'=>$timeZone];
        } else {
          $evStart = ['date'=>$date];
          $evEnd   = ['date'=>$endAllDay($date)];
        }
    
        $eventData = [
          'summary'=>$e['titolo'],
          'start'=>$evStart,
          'end'=>$evEnd,
          'extendedProperties' => ['private' => ['source'=>'gestione-famiglia','type'=>'evento']]
        ];
    
        $eventId = $makeEventId('evento', $e['id']);
        $upsert($service, $calendarIdEventi, $eventId, $eventData);
      }
    }


    echo json_encode(['success' => true, 'message' => 'Sincronizzazione completata']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
