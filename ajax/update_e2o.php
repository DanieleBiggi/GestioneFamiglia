<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/render_movimento_etichetta.php';

$id_e2o = intval($_POST['id_e2o'] ?? 0);
$descrizione_extra = trim($_POST['descrizione_extra'] ?? '');
$importo_input = trim($_POST['importo'] ?? '');
$importo = $importo_input === '' ? null : (float)$importo_input;

if (!$id_e2o) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare("SELECT id_tabella, tabella_operazione, id_etichetta, allegato FROM bilancio_etichette2operazioni WHERE id_e2o = ?");
$stmt->bind_param('i', $id_e2o);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$info) {
    echo json_encode(['success' => false]);
    exit;
}

$allegato = $info['allegato'];
if (!empty($_FILES['allegato']['name'])) {
    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $filename = $id_e2o . '_' . time() . '_' . basename($_FILES['allegato']['name']);
    $target = $dir . '/' . $filename;
    if (move_uploaded_file($_FILES['allegato']['tmp_name'], $target)) {
        $allegato = 'uploads/' . $filename;
    }
}

$sql = "UPDATE bilancio_etichette2operazioni SET descrizione_extra = ?, importo = ?, allegato = ? WHERE id_e2o = ?";
$stmtU = $conn->prepare($sql);
$stmtU->bind_param('sdsi', $descrizione_extra, $importo, $allegato, $id_e2o);
$success = $stmtU->execute();
$stmtU->close();

$mov = null;
if ($success) {
    switch ($info['tabella_operazione']) {
        case 'movimenti_revolut':
            $stmtM = $conn->prepare("SELECT id_movimento_revolut AS id, COALESCE(NULLIF(descrizione_extra,''), description) AS descrizione, descrizione_extra, started_date AS data_operazione, amount, 'movimenti_revolut' AS tabella, 'revolut' AS source FROM v_movimenti_revolut WHERE id_movimento_revolut = ?");
            $stmtM->bind_param('i', $info['id_tabella']);
            break;
        case 'bilancio_entrate':
            $stmtM = $conn->prepare("SELECT id_entrata AS id, descrizione_operazione AS descrizione, descrizione_extra, data_operazione, importo AS amount, 'bilancio_entrate' AS tabella, 'ca' AS source FROM bilancio_entrate WHERE id_entrata = ?");
            $stmtM->bind_param('i', $info['id_tabella']);
            break;
        case 'bilancio_uscite':
            $stmtM = $conn->prepare("SELECT id_uscita AS id, descrizione_operazione AS descrizione, descrizione_extra, data_operazione, -importo AS amount, 'bilancio_uscite' AS tabella, 'ca' AS source FROM bilancio_uscite WHERE id_uscita = ?");
            $stmtM->bind_param('i', $info['id_tabella']);
            break;
        default:
            $stmtM = null;
    }

    if ($stmtM && $stmtM->execute()) {
        $mov = $stmtM->get_result()->fetch_assoc();
        $stmtM->close();
    }
}

if ($mov) {
    ob_start();
    render_movimento_etichetta($mov, (int)$info['id_etichetta']);
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html, 'rowId' => 'mov-' . $mov['tabella'] . '-' . $mov['id']]);
} else {
    echo json_encode(['success' => $success]);
}
