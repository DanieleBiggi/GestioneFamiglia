<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id_etichetta'] ?? 0);
$descrizione = trim($data['descrizione'] ?? '');
$attivo = isset($data['attivo']) && $data['attivo'] == 1 ? 1 : 0;
$da_dividere = isset($data['da_dividere']) && $data['da_dividere'] == 1 ? 1 : 0;
$utenti = trim($data['utenti_tra_cui_dividere'] ?? '');

if (!$id || $descrizione === '') {
    echo json_encode(['success' => false, 'error' => 'Dati non validi']);
    exit;
}

$stmt = $conn->prepare('UPDATE bilancio_etichette SET descrizione = ?, attivo = ?, da_dividere = ?, utenti_tra_cui_dividere = ? WHERE id_etichetta = ?');
$stmt->bind_param('siisi', $descrizione, $attivo, $da_dividere, $utenti, $id);
$ok = $stmt->execute();

if ($ok) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Errore durante l\'aggiornamento']);
}
