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

$stmtUpd = $conn->prepare("UPDATE bilancio_utenti2operazioni_etichettate SET importo_utente = ?, saldata = ?, data_saldo = ? WHERE id_u2o = ?");
$stmtIns = $conn->prepare("INSERT INTO bilancio_utenti2operazioni_etichettate (id_e2o, id_utente, importo_utente, saldata, data_saldo) VALUES (?, ?, ?, ?, ?)");
foreach ($rows as $r) {
    $id = intval($r['id_u2o'] ?? 0);
    $importo = isset($r['importo_utente']) && $r['importo_utente'] !== '' ? (float)$r['importo_utente'] : null;
    $saldata = !empty($r['saldata']) ? 1 : 0;
    $data_saldo = $r['data_saldo'] ?? null;
    if ($data_saldo === '') $data_saldo = null;
    if ($id > 0) {
        $stmtUpd->bind_param('disi', $importo, $saldata, $data_saldo, $id);
        $stmtUpd->execute();
    } else {
        $id_e2o = intval($r['id_e2o'] ?? 0);
        $id_utente = intval($r['id_utente'] ?? 0);
        if ($id_e2o > 0 && $id_utente > 0) {
            $stmtIns->bind_param('iidis', $id_e2o, $id_utente, $importo, $saldata, $data_saldo);
            $stmtIns->execute();
        }
    }
}
$stmtUpd->close();
$stmtIns->close();

echo json_encode(['success' => true]);
