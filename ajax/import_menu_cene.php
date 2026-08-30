<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';

if (!has_permission($conn, 'ajax:import_menu_cene', 'insert')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}

$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
if (!$idFamiglia) {
    echo json_encode(['success' => false, 'error' => 'Famiglia non selezionata']);
    exit;
}

$weekStartRaw = trim($_POST['week_start'] ?? '');
$weekStart = DateTimeImmutable::createFromFormat('!Y-m-d', $weekStartRaw);
if (!$weekStart || $weekStart->format('Y-m-d') !== $weekStartRaw || $weekStart->format('N') !== '1') {
    echo json_encode(['success' => false, 'error' => 'Settimana non valida']);
    exit;
}

$raw = $_POST['items'] ?? '';
$lines = array_map('trim', preg_split('/\r?\n/', $raw));
$days = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì'];
$weekStartSql = $weekStart->format('Y-m-d');

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('INSERT INTO menu_cene_settimanale (id_famiglia, week_start, giorno, piatto)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE piatto = VALUES(piatto)');

    foreach ($days as $i => $day) {
        $piatto = $lines[$i] ?? '';
        $stmt->bind_param('isss', $idFamiglia, $weekStartSql, $day, $piatto);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
    }
    $stmt->close();
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore durante l\'import']);
}
