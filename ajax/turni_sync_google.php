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

function log_sync(mysqli $conn, ?int $idTurno, ?int $idEvento, string $azione, string $esito, string $messaggio = '', ?string $dati_evento = null): void {
    $stmt = $conn->prepare('INSERT INTO turni_sync_google_log (id_turno, id_evento, azione, esito, messaggio, dati_evento) VALUES (?,?,?,?,?,?)');
    $stmt->bind_param('iissss', $idTurno, $idEvento, $azione, $esito, $messaggio, $dati_evento);
    $stmt->execute();
    $stmt->close();
}

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
  'SELECT e.id, e.titolo, e.data_evento, e.ora_evento, e.data_fine, e.ora_fine, e.google_calendar_eventid
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

// Carica le regole per gli eventi Google
$rules = [];
$ruleRes = $conn->query('SELECT id, creator_email, description_keyword, id_tipo_evento FROM eventi_google_rules WHERE attiva=1');
if ($ruleRes) {
    while ($r = $ruleRes->fetch_assoc()) {
        $r['invitati'] = [];
        $rules[$r['id']] = $r;
    }
    $ruleRes->close();
    if ($rules) {
        $invRes = $conn->query('SELECT id_rule, id_invitato FROM eventi_google_rules_invitati');
        if ($invRes) {
            while ($ir = $invRes->fetch_assoc()) {
                if (isset($rules[$ir['id_rule']])) {
                    $rules[$ir['id_rule']]['invitati'][] = (int)$ir['id_invitato'];
                }
            }
            $invRes->close();
        }
    }
}
// Google Calendar integration
require_once __DIR__ . '/../vendor/autoload.php';
$credentialsFile = __DIR__ . '/../config/google_credentials.json';
$tokenFile = __DIR__ . '/../config/google_token.json';
if (!file_exists($credentialsFile)) {
    echo json_encode(['success' => false, 'message' => 'Credenziali Google mancanti']);
    exit;
}
try {
    /*
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
    */
    $serviceAccountJson = __DIR__ . '/../config/service-account.json'; // fuori webroot
    //$calendarId = 'TUO_CALENDAR_ID'; // es. 'tuaemail@gmail.com' oppure l'ID del secondario
    
    $client = new Google_Client();
    $client->setApplicationName('Calendar SA');
    $client->setScopes([Google_Service_Calendar::CALENDAR]); // o CALENDAR_READONLY
    $client->setAuthConfig($serviceAccountJson);
    // NON impostare ->setSubject() (serve solo con delega di dominio Workspace)

    $service = new Google_Service_Calendar($client);
    $calendarIdTurni = '405f4721b468b439755b6aad55d6b40e5c235f8511db6a29b95dc7f96ff329f0@group.calendar.google.com';
    $calendarIdEventi = '29f6c24acdde6722ed7eb92a5eff3d15bdc1a46d210def3314c1b05c15ac024f@group.calendar.google.com';

    // Timezone fisso
    $timeZone = 'Europe/Rome';

    // Mappa tra codice colore e colorId di Google Calendar
    $colorMap = [];
    try {
        $gColors = $service->colors->get();
        $eventColors = $gColors->getEvent();
        foreach ($eventColors as $id => $c) {
            $colorMap[strtolower($c->getBackground())] = $id;
        }
    } catch (Exception $e) {
        // in caso di errore la mappa resterà vuota e gli eventi useranno il colore di default
    }

    // Helper: normalizza "HH:MM" -> "HH:MM:00"
    // Ritorna null se l'orario è vuoto o impostato a mezzanotte,
    // così da poter usare il fallback sugli orari del tipo turno.
    $normTime = function($t) {
        if (!$t || $t === '00:00' || $t === '00:00:00') {
            return null;
        }
        if (preg_match('/^\\d{2}:\\d{2}$/', $t)) {
            return $t . ':00';
        }
        if (preg_match('/^\\d{2}:\\d{2}:\\d{2}$/', $t)) {
            return $t;
        }
        return null; // formato non valido
    };
    
    // All-day: end = giorno successivo
    $endAllDay = function(string $date) {
        return date('Y-m-d', strtotime($date . ' +1 day'));
    };
    
    // Sync turni: DB -> Google Calendar
    foreach ($turni as $t) {
        try {
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

            $colBg = strtolower($t['colore_bg']);
            if (isset($colorMap[$colBg])) {
                $eventData['colorId'] = $colorMap[$colBg];
            }

            $payload = json_encode($eventData, JSON_UNESCAPED_UNICODE);

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
            log_sync($conn, (int)$t['id'], null, 'turno_db_to_google', 'success', '', $payload);
        } catch (Exception $e) {
            log_sync($conn, (int)$t['id'], null, 'turno_db_to_google', 'error', $e->getMessage(), $payload);
        }
    }

    // Insert DB eventi without calendar id into Google Calendar
    foreach ($eventiDb as $e) {
        if (empty($e['google_calendar_eventid'])) {
            try {
            $date        = $e['data_evento'];
            $evtStartTime = $normTime($e['ora_evento'] ?? null);
            $evtEndTime   = $normTime($e['ora_fine']   ?? null);
            $endDate      = $e['data_fine'] ?: $date;
    
            if ($evtStartTime) {
                $start = $date . 'T' . $evtStartTime;
    
                if ($evtEndTime) {
                    $end     = $endDate . 'T' . $evtEndTime;
                    $evStart = ['dateTime' => $start, 'timeZone' => $timeZone];
                    $evEnd   = ['dateTime' => $end,   'timeZone' => $timeZone];
                } elseif ($e['data_fine'] && $endDate !== $date) {
                    // multi-day event without end time -> all-day
                    $evStart = ['date' => $date];
                    $evEnd   = ['date' => $endAllDay($endDate)];
                } else {
                    $end     = date('Y-m-d\TH:i:s', strtotime($start . ' +1 hour'));
                    $evStart = ['dateTime' => $start, 'timeZone' => $timeZone];
                    $evEnd   = ['dateTime' => $end,   'timeZone' => $timeZone];
                }
            } else {
                $evStart = ['date' => $date];
                $evEnd   = ['date' => $endAllDay($endDate)];
            }
    
            $eventData = [
                'summary' => $e['titolo'],
                'start'   => $evStart,
                'end'     => $evEnd,
                'extendedProperties' => ['private' => ['source' => 'gestione-famiglia', 'type' => 'evento']]
            ];

            $payload = json_encode($eventData, JSON_UNESCAPED_UNICODE);

            $created = $service->events->insert($calendarIdEventi, new Google_Service_Calendar_Event($eventData));
            $gcId = $created->getId();
            $upd = $conn->prepare('UPDATE eventi SET google_calendar_eventid=? WHERE id=?');
            $upd->bind_param('si', $gcId, $e['id']);
            $upd->execute();
            $upd->close();
            $eventiByGcId[$gcId] = $e['id'];
            log_sync($conn, null, (int)$e['id'], 'evento_db_to_google', 'success', '', $payload);
        } catch (Exception $ex) {
            log_sync($conn, null, (int)$e['id'], 'evento_db_to_google', 'error', $ex->getMessage(), $payload);
        }
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
            $eventId = null;
            try {
                $summary = $gEvent->getSummary();
                $startObj = $gEvent->getStart();
                $endObj = $gEvent->getEnd();
                $creatorEmail = '';
                $creatorObj = $gEvent->getCreator();
                if ($creatorObj) { $creatorEmail = $creatorObj->getEmail(); }
                $description = $gEvent->getDescription();
                $textMatch = trim(($summary ?? '') . ' ' . ($description ?? ''));

            $matchedRules = [];
            foreach ($rules as $rule) {
                if (!empty($rule['creator_email']) && strcasecmp($rule['creator_email'], $creatorEmail) !== 0) {
                    continue;
                }
                if (!empty($rule['description_keyword']) && stripos($textMatch, $rule['description_keyword']) === false) {
                    continue;
                }
                $matchedRules[] = $rule;
            }
            $idTipoEvento = null;
            foreach ($matchedRules as $rule) {
                if (!empty($rule['id_tipo_evento'])) { $idTipoEvento = (int)$rule['id_tipo_evento']; break; }
            }
            if ($startObj->getDateTime()) {
                $dt = new DateTime($startObj->getDateTime());
                $date = $dt->format('Y-m-d');
                $time = $dt->format('H:i');
            } else {
                $date = $startObj->getDate();
                $time = null;
            }
            if ($endObj->getDateTime()) {
                $dt = new DateTime($endObj->getDateTime());
                $data_fine = $dt->format('Y-m-d');
                $ora_fine = $dt->format('H:i');
            } else {
                // Google all-day events use exclusive end date
                $data_fine = date('Y-m-d', strtotime($endObj->getDate() . ' -1 day'));
                $ora_fine = null;
            }

                if (isset($eventiByGcId[$gcId])) {
                    $dbId = $eventiByGcId[$gcId];
                    $upd = $conn->prepare('UPDATE eventi SET titolo=?, data_evento=?, ora_evento=?,data_fine=?, ora_fine=?, descrizione=?, id_tipo_evento=IFNULL(?, id_tipo_evento), creator_email=? WHERE id=?');
                    $upd->bind_param('ssssssisi', $summary, $date, $time, $data_fine, $ora_fine, $description, $idTipoEvento, $creatorEmail, $dbId);
                    $upd->execute();
                    $upd->close();
                    $eventId = $dbId;
                } else {
                    $ins = $conn->prepare('INSERT INTO eventi (titolo, data_evento, ora_evento, data_fine, ora_fine, descrizione, id_tipo_evento, google_calendar_eventid, creator_email) VALUES (?,?,?,?,?,?,?,?,?)');
                    $ins->bind_param('ssssssiss', $summary, $date, $time, $data_fine, $ora_fine, $description, $idTipoEvento, $gcId, $creatorEmail);
                    $ins->execute();
                    $newId = $ins->insert_id;
                    $ins->close();

                    $link = $conn->prepare('INSERT INTO eventi_eventi2famiglie (id_evento, id_famiglia) VALUES (?,?)');
                    $link->bind_param('ii', $newId, $idFamiglia);
                    $link->execute();
                    $link->close();
                    $eventId = $newId;
                }

                if (!empty($matchedRules) && isset($eventId)) {
                    foreach ($matchedRules as $rule) {
                        foreach ($rule['invitati'] as $idInv) {
                            $chk = $conn->prepare('SELECT 1 FROM eventi_eventi2invitati WHERE id_evento=? AND id_invitato=?');
                            $chk->bind_param('ii', $eventId, $idInv);
                            $chk->execute();
                            $exists = $chk->get_result()->num_rows > 0;
                            $chk->close();
                            if (!$exists) {
                                $insInv = $conn->prepare('INSERT INTO eventi_eventi2invitati (id_evento, id_invitato) VALUES (?,?)');
                                $insInv->bind_param('ii', $eventId, $idInv);
                                $insInv->execute();
                                $insInv->close();
                            }
                        }
                    }
                }

                log_sync($conn, null, $eventId, 'evento_google_to_db', 'success', '', null);
            } catch (Exception $e) {
                $refId = $eventId ?? ($eventiByGcId[$gcId] ?? null);
                log_sync($conn, null, $refId, 'evento_google_to_db', 'error', $e->getMessage(), null);
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
    log_sync($conn, null, null, 'general', 'error', $e->getMessage(), null);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
