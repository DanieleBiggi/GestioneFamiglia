<?php
header('Content-Type: application/json; charset=utf-8');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
require_once '../includes/menu_cene_config.php';
$idFamiglia = (int)($_SESSION['id_famiglia_gestione'] ?? 0);
if ($idFamiglia <= 0 || !has_permission($conn, 'page:menu_cene.php', 'view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}
$week = $_GET['week_start'] ?? '';
$date = is_string($week) ? DateTimeImmutable::createFromFormat('!Y-m-d', $week) : false;
if (!$date || $date->format('Y-m-d') !== $week || $date->format('N') !== '1') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Settimana non valida']);
    exit;
}
$stmt = $conn->prepare('SELECT giorno, piatto FROM menu_cene_settimanale WHERE id_famiglia = ? AND week_start = ?');
$stmt->bind_param('is', $idFamiglia, $week);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
try {
    $matches = food_config_match_recipes($rows, food_config_recipes($idFamiglia));
    echo json_encode(['success' => true, 'week_start' => $week, 'menu' => $rows] + $matches, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Esportazione menù: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Impossibile esportare il menù']);
}
