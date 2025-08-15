<?php
include '../includes/session_check.php';
include '../includes/db.php';
header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
$descrizione = trim($_POST['descrizione'] ?? '');
$oraInizio = $_POST['ora_inizio'] ?? '00:00';
$oraFine = $_POST['ora_fine'] ?? '00:00';
$coloreBg = $_POST['colore_bg'] ?? '#ffffff';
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
