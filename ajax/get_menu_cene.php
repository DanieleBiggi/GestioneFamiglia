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

function normalize_time(?string $time): ?string
{
    $time = trim($time ?? '');
    if ($time === '' || $time === '00:00:00') {
        return null;
    }
    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        return $time . ':00';
    }
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return $time;
    }
    return null;
}

function time_in_range(?string $time, string $from, string $to): bool
{
    return $time && $time >= $from && $time <= $to;
}

function italian_day_name(string $date): string
{
    $days = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];
    $index = (int)date('N', strtotime($date)) - 1;
    return $days[$index] ?? '';
}

function build_turni(array $rows, array $windows): array
{
    $turni = [];
    foreach ($rows as $row) {
        $startTime = normalize_time($row['ora_inizio'] ?? null);
        $endTime = normalize_time($row['ora_fine'] ?? null);
        $startFrom = $windows['start_from'] ?? '18:00:00';
        $startTo = $windows['start_to'] ?? '22:00:00';
        $endFrom = $windows['end_from'] ?? $startFrom;
        $endTo = $windows['end_to'] ?? $startTo;

        $match = time_in_range($startTime, $startFrom, $startTo) || time_in_range($endTime, $endFrom, $endTo);
        if (!$match) {
            continue;
        }

        $turni[$row['data']][] = [
            'descrizione' => $row['descrizione'],
            'ora_inizio' => $startTime,
            'ora_fine' => $endTime,
            'giorno' => italian_day_name($row['data']),
            'data' => $row['data']
        ];
    }

    return $turni;
}

$days = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì'];
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

// Gestione della settimana selezionata
$weekStartParam = $_GET['week_start'] ?? null;
$startOfWeek = new DateTimeImmutable('monday this week');
if ($weekStartParam) {
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $weekStartParam);
    if ($parsed !== false) {
        $startOfWeek = $parsed->modify('monday this week');
    }
}

$currentWeekNumber = (int)$startOfWeek->format('W');
$endOfWeek = $startOfWeek->modify('sunday this week');

$nextWeekStart = $startOfWeek->modify('+1 week');
$nextWeekEnd = $nextWeekStart->modify('sunday this week');

// Recupero turni settimana corrente e successiva
$turniRows = [];
$turniStmt = $conn->prepare('SELECT t.data,
    COALESCE(NULLIF(t.ora_inizio, "00:00:00"), tp.ora_inizio) AS ora_inizio,
    COALESCE(NULLIF(t.ora_fine, "00:00:00"), tp.ora_fine) AS ora_fine,
    tp.descrizione
    FROM turni_calendario t
    JOIN turni_tipi tp ON t.id_tipo = tp.id
    WHERE t.id_famiglia = ? AND t.data BETWEEN ? AND ?
    ORDER BY t.data, COALESCE(NULLIF(t.ora_inizio, "00:00:00"), tp.ora_inizio)');
$startCurrent = $startOfWeek->format('Y-m-d');
$endCurrent = $endOfWeek->format('Y-m-d');
$turniStmt->bind_param('iss', $idFamiglia, $startCurrent, $endCurrent);
$turniStmt->execute();
$turniRows = $turniStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$turniStmt->close();

$turniNextRows = [];
$turniNextStmt = $conn->prepare('SELECT t.data,
    COALESCE(NULLIF(t.ora_inizio, "00:00:00"), tp.ora_inizio) AS ora_inizio,
    COALESCE(NULLIF(t.ora_fine, "00:00:00"), tp.ora_fine) AS ora_fine,
    tp.descrizione
    FROM turni_calendario t
    JOIN turni_tipi tp ON t.id_tipo = tp.id
    WHERE t.id_famiglia = ? AND t.data BETWEEN ? AND ?
    ORDER BY t.data, COALESCE(NULLIF(t.ora_inizio, "00:00:00"), tp.ora_inizio)');
$startNext = $nextWeekStart->format('Y-m-d');
$endNext = $nextWeekEnd->format('Y-m-d');
$turniNextStmt->bind_param('iss', $idFamiglia, $startNext, $endNext);
$turniNextStmt->execute();
$turniNextRows = $turniNextStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$turniNextStmt->close();

$turniByDate = build_turni($turniRows, [
    'start_from' => '18:00:00',
    'start_to' => '22:00:00',
    'end_from' => '18:00:00',
    'end_to' => '22:00:00'
]);
$turniNextByDate = build_turni($turniNextRows, [
    'start_from' => '18:00:00',
    'start_to' => '22:00:00',
    'end_from' => '18:00:00',
    'end_to' => '21:00:00'
]);

// Recupero eventi con orari serali nella settimana corrente
$eventiStmt = $conn->prepare('SELECT e.id, e.titolo, e.data_evento, e.ora_evento, e.data_fine, e.ora_fine
    FROM eventi e
    JOIN eventi_eventi2famiglie f ON e.id = f.id_evento
    WHERE f.id_famiglia = ? AND e.data_evento <= ? AND COALESCE(e.data_fine, e.data_evento) >= ?');
$eventiStmt->bind_param('iss', $idFamiglia, $endCurrent, $startCurrent);
$eventiStmt->execute();
$eventRows = $eventiStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$eventiStmt->close();

$eventiByDate = [];
foreach ($eventRows as $event) {
    $eventStart = $event['data_evento'];
    $eventEnd = $event['data_fine'] ?: $event['data_evento'];
    $startTime = normalize_time($event['ora_evento'] ?? null);
    $endTime = normalize_time($event['ora_fine'] ?? null);

    if (!time_in_range($startTime, '18:00:00', '22:00:00') && !time_in_range($endTime, '18:00:00', '22:00:00')) {
        continue;
    }

    $cursor = new DateTime($eventStart);
    $limit = new DateTime($eventEnd);
    while ($cursor <= $limit) {
        $dateKey = $cursor->format('Y-m-d');
        if ($dateKey >= $startCurrent && $dateKey <= $endCurrent) {
            $eventiByDate[$dateKey][] = [
                'titolo' => $event['titolo'],
                'ora_evento' => $startTime,
                'ora_fine' => $endTime,
                'giorno' => italian_day_name($dateKey)
            ];
        }
        $cursor->modify('+1 day');
    }
}

$ordered = [];
foreach ($days as $index => $day) {
    if (!isset($menu[$day])) {
        $insertStmt->bind_param('is', $idFamiglia, $day);
        $insertStmt->execute();
        $menu[$day] = [
            'id' => $conn->insert_id,
            'giorno' => $day,
            'piatto' => ''
        ];
    }

    $dataGiorno = $startOfWeek->modify("+{$index} day")->format('Y-m-d');
    $ordered[] = array_merge($menu[$day], [
        'data' => $dataGiorno,
        'turni' => $turniByDate[$dataGiorno] ?? [],
        'eventi' => $eventiByDate[$dataGiorno] ?? []
    ]);
}
$insertStmt->close();

echo json_encode([
    'success' => true,
    'items' => $ordered,
    'week' => [
        'number' => $currentWeekNumber,
        'year' => (int)$startOfWeek->format('o'),
        'start' => $startCurrent,
        'end' => $endCurrent
    ],
    'nextWeek' => [
        'number' => (int)$nextWeekStart->format('W'),
        'year' => (int)$nextWeekStart->format('o'),
        'start' => $startNext,
        'end' => $endNext,
        'turni' => $turniNextByDate
    ]
]);
