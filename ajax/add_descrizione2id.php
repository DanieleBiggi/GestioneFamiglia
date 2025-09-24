<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$descrizione = trim($_POST['descrizione'] ?? '');
$descrizione_extra = trim($_POST['descrizione_extra'] ?? '');
$id_gruppo = (int)($_POST['id_gruppo_transazione'] ?? 0);
$conto = $_POST['conto'] ?? '';
$id_etichetta = isset($_POST['id_etichetta']) && $_POST['id_etichetta'] !== '' ? (int)$_POST['id_etichetta'] : null;
$id_metodo = (int)($_POST['id_metodo_pagamento'] ?? 0);

if ($descrizione_extra === '') {
    $descrizione_extra = null;
}

if ($descrizione === '' || !$id_gruppo || $conto === '') {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare('INSERT INTO bilancio_descrizione2id (id_utente, descrizione, id_gruppo_transazione, id_metodo_pagamento, id_etichetta, descrizione_extra, conto) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('isiiiss', $_SESSION['utente_id'], $descrizione, $id_gruppo, $id_metodo, $id_etichetta, $descrizione_extra, $conto);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok]);
