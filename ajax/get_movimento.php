<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$tabella = $_GET['tabella'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$allowed = ['movimenti_revolut','bilancio_entrate','bilancio_uscite'];
if (!in_array($tabella, $allowed, true) || $id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parametri non validi']);
    exit;
}

if ($tabella === 'bilancio_entrate') {
    $stmt = $conn->prepare("SELECT COALESCE(NULLIF(descrizione_extra,''), descrizione_operazione) AS descrizione, note, importo AS amount, data_operazione FROM bilancio_entrate WHERE id_entrata = ?");
} elseif ($tabella === 'bilancio_uscite') {
    $stmt = $conn->prepare("SELECT COALESCE(NULLIF(descrizione_extra,''), descrizione_operazione) AS descrizione, note, -importo AS amount, data_operazione FROM bilancio_uscite WHERE id_uscita = ?");
} else {
    $stmt = $conn->prepare("SELECT description AS descrizione, note, amount, started_date AS data_operazione FROM movimenti_revolut WHERE id_movimento_revolut = ?");
}
$stmt->bind_param('i', $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Movimento non trovato']);
    exit;
}

$data['amount'] = (float)$data['amount'];

echo json_encode(['success' => true, 'data' => $data]);
