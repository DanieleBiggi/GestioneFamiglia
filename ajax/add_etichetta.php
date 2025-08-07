<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$descrizione = trim($data['descrizione'] ?? '');
$attivo = isset($data['attivo']) && $data['attivo'] == 1 ? 1 : 0;
$da_dividere = isset($data['da_dividere']) && $data['da_dividere'] == 1 ? 1 : 0;
$utenti = trim($data['utenti_tra_cui_dividere'] ?? '');

if ($descrizione === '') {
    echo json_encode(['success' => false, 'error' => 'Descrizione mancante']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO bilancio_etichette (descrizione, attivo, da_dividere, utenti_tra_cui_dividere) VALUES (?, ?, ?, ?)');
$stmt->bind_param('siis', $descrizione, $attivo, $da_dividere, $utenti);
$ok = $stmt->execute();

if ($ok) {
    echo json_encode(['success' => true, 'id' => $conn->insert_id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Errore durante l\'inserimento']);
}

