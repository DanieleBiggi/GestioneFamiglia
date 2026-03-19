<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
include '../includes/permissions.php';

if (!has_permission($conn, 'table:turni_calendario', 'update')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}

$idFamiglia = (int)($_SESSION['id_famiglia_gestione'] ?? 0);
if ($idFamiglia <= 0) {
    echo json_encode(['success' => false, 'error' => 'Famiglia non valida']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$items = $data['items'] ?? null;
if (!is_array($items) || !$items) {
    echo json_encode(['success' => false, 'error' => 'JSON non valido o vuoto']);
    exit;
}

$tipiRes = $conn->query("SELECT id, descrizione, ora_inizio, ora_fine FROM turni_tipi WHERE attivo = 1");
$tipiByCode = [];
if ($tipiRes) {
    while ($row = $tipiRes->fetch_assoc()) {
        $codes = [
            mb_strtoupper(trim((string)$row['descrizione'])),
            mb_strtoupper(mb_substr(trim((string)$row['descrizione']), 0, 1)),
        ];
        foreach ($codes as $code) {
            if ($code === '') {
                continue;
            }
            $tipiByCode[$code][] = [
                'id' => (int)$row['id'],
                'descrizione' => $row['descrizione'],
                'ora_inizio' => $row['ora_inizio'] ?: '00:00:00',
                'ora_fine' => $row['ora_fine'] ?: '00:00:00',
            ];
        }
    }
}

$rowsToInsert = [];
$months = [];

foreach ($items as $index => $item) {
    if (!is_array($item)) {
        echo json_encode(['success' => false, 'error' => 'Ogni elemento del JSON deve essere un oggetto']);
        exit;
    }

    $date = trim((string)($item['data'] ?? ''));
    $tipoTurno = mb_strtoupper(trim((string)($item['tipo_turno'] ?? '')));
    $position = $index + 1;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
        echo json_encode(['success' => false, 'error' => "Data non valida alla riga {$position}"]);
        exit;
    }
    if ($tipoTurno === '') {
        echo json_encode(['success' => false, 'error' => "tipo_turno mancante alla riga {$position}"]);
        exit;
    }
    if (empty($tipiByCode[$tipoTurno])) {
        echo json_encode(['success' => false, 'error' => "Tipo turno '{$tipoTurno}' non trovato alla riga {$position}"]);
        exit;
    }
    if (count($tipiByCode[$tipoTurno]) > 1) {
        echo json_encode(['success' => false, 'error' => "Tipo turno '{$tipoTurno}' ambiguo alla riga {$position}"]);
        exit;
    }

    $tipo = $tipiByCode[$tipoTurno][0];
    $rowsToInsert[] = [
        'data' => $date,
        'id_tipo' => $tipo['id'],
        'ora_inizio' => $tipo['ora_inizio'],
        'ora_fine' => $tipo['ora_fine'],
    ];
    $months[substr($date, 0, 7)] = true;
}

if (!$rowsToInsert) {
    echo json_encode(['success' => false, 'error' => 'Nessun turno da importare']);
    exit;
}

$deleteStmt = $conn->prepare('DELETE FROM turni_calendario WHERE id_famiglia = ? AND data BETWEEN ? AND ?');
$insertStmt = $conn->prepare('INSERT INTO turni_calendario (id_famiglia, data, ora_inizio, ora_fine, id_tipo) VALUES (?, ?, ?, ?, ?)');

if (!$deleteStmt || !$insertStmt) {
    echo json_encode(['success' => false, 'error' => 'Impossibile preparare la query di importazione']);
    exit;
}

foreach (array_keys($months) as $month) {
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $deleteStmt->bind_param('iss', $idFamiglia, $monthStart, $monthEnd);
    if (!$deleteStmt->execute()) {
        $deleteStmt->close();
        $insertStmt->close();
        echo json_encode(['success' => false, 'error' => 'Errore durante la pulizia dei turni esistenti']);
        exit;
    }
}

foreach ($rowsToInsert as $row) {
    $insertStmt->bind_param('isssi', $idFamiglia, $row['data'], $row['ora_inizio'], $row['ora_fine'], $row['id_tipo']);
    if (!$insertStmt->execute()) {
        $deleteStmt->close();
        $insertStmt->close();
        echo json_encode(['success' => false, 'error' => 'Errore durante il salvataggio dei turni']);
        exit;
    }
}

$deleteStmt->close();
$insertStmt->close();

echo json_encode([
    'success' => true,
    'imported' => count($rowsToInsert),
    'months_replaced' => array_keys($months),
]);
