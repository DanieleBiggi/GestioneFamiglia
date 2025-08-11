<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$idUtente = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$idFamiglia) {
    echo json_encode(['success' => false, 'error' => 'Famiglia non valida']);
    exit;
}

if ($action === 'save') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $id_salvadanaio = isset($_POST['id_salvadanaio']) && $_POST['id_salvadanaio'] !== '' ? (int)$_POST['id_salvadanaio'] : null;
    $descrizione = trim($_POST['descrizione'] ?? '');
    $data_inizio = $_POST['data_inizio'] ?? null;
    $data_scadenza = $_POST['data_scadenza'] ?? null;
    $da13 = isset($_POST['da_13esima']) ? (float)$_POST['da_13esima'] : 0;
    $da14 = isset($_POST['da_14esima']) ? (float)$_POST['da_14esima'] : 0;
    $importo = isset($_POST['importo']) ? (float)$_POST['importo'] : 0;

    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE budget SET id_salvadanaio=?, descrizione=?, data_inizio=?, data_scadenza=?, da_13esima=?, da_14esima=?, importo=? WHERE id_budget=? AND id_famiglia=?');
        $stmt->bind_param('isssdddii', $id_salvadanaio, $descrizione, $data_inizio, $data_scadenza, $da13, $da14, $importo, $id, $idFamiglia);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    } else {
        $stmt = $conn->prepare('INSERT INTO budget (id_salvadanaio, descrizione, data_inizio, data_scadenza, da_13esima, da_14esima, importo, id_famiglia, id_utente) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->bind_param('isssdddii', $id_salvadanaio, $descrizione, $data_inizio, $data_scadenza, $da13, $da14, $importo, $idFamiglia, $idUtente);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    }
}

if ($action === 'delete') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
        $stmt = $conn->prepare('DELETE FROM budget WHERE id_budget=? AND id_famiglia=?');
        $stmt->bind_param('ii', $id, $idFamiglia);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    }
    echo json_encode(['success' => false]);
    exit;
}

if ($action === 'duplicate') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
        $stmt = $conn->prepare('INSERT INTO budget (tipologia, importo, id_salvadanaio, descrizione, data_inizio, data_scadenza, tipologia_spesa, da_13esima, da_14esima, id_utente, id_famiglia)
            SELECT tipologia, importo, id_salvadanaio, descrizione, data_inizio, data_scadenza, tipologia_spesa, da_13esima, da_14esima, ?, id_famiglia FROM budget WHERE id_budget=? AND id_famiglia=?');
        $stmt->bind_param('iii', $idUtente, $id, $idFamiglia);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    }
    echo json_encode(['success' => false]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Azione non valida']);
