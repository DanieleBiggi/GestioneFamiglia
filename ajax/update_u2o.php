<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_u2o = intval($_POST['id_u2o'] ?? 0);
$id_e2o = intval($_POST['id_e2o'] ?? 0);
$id_utente = intval($_POST['id_utente'] ?? 0);
$quote_input = trim($_POST['quote'] ?? '');
$quote = $quote_input === '' ? 1 : (float)$quote_input;
$saldata = !empty($_POST['saldata']) ? 1 : 0;
$data_saldo = trim($_POST['data_saldo'] ?? '');
if ($data_saldo === '') {
    $data_saldo = null;
}

if ($id_u2o > 0) {
    $stmt = $conn->prepare("UPDATE bilancio_utenti2operazioni_etichettate SET id_utente = ?, quote = ?, saldata = ?, data_saldo = ? WHERE id_u2o = ?");
    $stmt->bind_param('idisi', $id_utente, $quote, $saldata, $data_saldo, $id_u2o);
    $ok = $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("INSERT INTO bilancio_utenti2operazioni_etichettate (id_e2o, id_utente, quote, saldata, data_saldo) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('iidis', $id_e2o, $id_utente, $quote, $saldata, $data_saldo);
    $ok = $stmt->execute();
    $stmt->close();
}

echo json_encode(['success' => $ok]);
