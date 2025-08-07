<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$rows = $data['rows'] ?? [];
if (!is_array($rows)) {
    echo json_encode(['success' => false, 'error' => 'Dati non validi']);
    exit;
}

$stmt = $conn->prepare("UPDATE bilancio_utenti2operazioni_etichettate SET importo_utente = ?, saldata = ?, data_saldo = ? WHERE id_u2o = ?");
foreach ($rows as $r) {
    $id = intval($r['id_u2o'] ?? 0);
    if ($id <= 0) continue;
    $importo = isset($r['importo_utente']) && $r['importo_utente'] !== '' ? (float)$r['importo_utente'] : 0;
    $saldata = !empty($r['saldata']) ? 1 : 0;
    $data_saldo = $r['data_saldo'] ?? null;
    if ($data_saldo === '') $data_saldo = null;
    $stmt->bind_param('disi', $importo, $saldata, $data_saldo, $id);
    $stmt->execute();
}
$stmt->close();

echo json_encode(['success' => true]);
