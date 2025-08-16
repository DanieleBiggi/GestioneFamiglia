<?php
include '../includes/session_check.php';
include '../includes/db.php';
header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
$descrizione = trim($_POST['descrizione'] ?? '');
$oraInizio = trim($_POST['ora_inizio'] ?? '');
$oraInizio = $oraInizio !== '' ? $oraInizio : null;
$oraFine = trim($_POST['ora_fine'] ?? '');
$oraFine = $oraFine !== '' ? $oraFine : null;
$allowedColors = ['#a4bdfc', '#7ae7bf', '#dbadff', '#ff887c', '#fbd75b', '#ffb878', '#46d6db', '#e1e1e1', '#5484ed', '#51b749'];
$coloreBg = $_POST['colore_bg'] ?? $allowedColors[0];
if (!in_array($coloreBg, $allowedColors, true)) {
    $coloreBg = $allowedColors[0];
}
$coloreTesto = $_POST['colore_testo'] ?? '#000000';
$attivo = isset($_POST['attivo']) ? 1 : 0;

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE turni_tipi SET descrizione=?, ora_inizio=?, ora_fine=?, colore_bg=?, colore_testo=?, attivo=? WHERE id=?");
    $stmt->bind_param('ssssssi', $descrizione, $oraInizio, $oraFine, $coloreBg, $coloreTesto, $attivo, $id);
    $ok = $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("INSERT INTO turni_tipi (descrizione, ora_inizio, ora_fine, colore_bg, colore_testo, attivo) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('sssssi', $descrizione, $oraInizio, $oraFine, $coloreBg, $coloreTesto, $attivo);
    $ok = $stmt->execute();
    $stmt->close();
}

echo json_encode(['success' => $ok]);
