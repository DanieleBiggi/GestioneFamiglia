<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_salvadanaio = (int)($_POST['id_salvadanaio'] ?? 0);
$nome = $_POST['nome_salvadanaio'] ?? '';
$importo = isset($_POST['importo_attuale']) ? (float)$_POST['importo_attuale'] : 0;
$data_scadenza = $_POST['data_scadenza'] ?? null;
$data_scadenza = $data_scadenza !== null && $data_scadenza !== '' ? $data_scadenza : null;
$modalita = $_POST['accantonamento_modalita'] ?? 'automatico';
if (!in_array($modalita, ['automatico', 'fisso'], true)) {
    http_response_code(422);
    echo json_encode(['success'=>false,'error'=>'Modalità di accantonamento non valida']); exit;
}
$fisso = null;
if ($modalita === 'fisso') {
    $raw = $_POST['accantonamento_fisso'] ?? '';
    if (!is_scalar($raw) || !is_numeric($raw) || (float)$raw < 0 || (float)$raw > 1000000) {
        http_response_code(422);
        echo json_encode(['success'=>false,'error'=>'Importo mensile fisso non valido']); exit;
    }
    $fisso = round((float)$raw, 2);
}
$now = date('Y-m-d H:i:s');

if(!$id_salvadanaio || $nome === ''){
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare('UPDATE salvadanai SET nome_salvadanaio=?, data_aggiornamento_manuale=IF(importo_attuale <> ?, ?, data_aggiornamento_manuale), importo_attuale=?, data_scadenza=?, accantonamento_modalita=?, accantonamento_fisso=? WHERE id_salvadanaio=?');
$stmt->bind_param('sdsdssdi', $nome, $importo, $now, $importo, $data_scadenza, $modalita, $fisso, $id_salvadanaio);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
