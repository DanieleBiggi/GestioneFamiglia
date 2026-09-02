<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
    $data_scadenza = !empty($_POST['data_scadenza']) ? $_POST['data_scadenza'] : null;
    $da13 = isset($_POST['da_13esima']) ? (float)$_POST['da_13esima'] : 0;
    $da14 = isset($_POST['da_14esima']) ? (float)$_POST['da_14esima'] : 0;
    $importo = isset($_POST['importo']) ? (float)$_POST['importo'] : 0;
    $tipologia = $_POST['tipologia'] ?? 'uscita';
    $tipologia_spesa = $_POST['tipologia_spesa'] ?? 'fissa';

    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE budget SET id_salvadanaio=?, descrizione=?, data_inizio=?, data_scadenza=?, da_13esima=?, da_14esima=?, importo=? WHERE id_budget=? AND id_famiglia=?');
        $stmt->bind_param('isssdddii', $id_salvadanaio, $descrizione, $data_inizio, $data_scadenza, $da13, $da14, $importo, $id, $idFamiglia);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    } else {
        $stmt = $conn->prepare('INSERT INTO budget (tipologia, tipologia_spesa, id_salvadanaio, descrizione, data_inizio, data_scadenza, da_13esima, da_14esima, importo, id_famiglia) VALUES (?,?,?,?,?,?,?,?,?,?)');
        if (!$stmt) {
            die("Errore nella prepare: " . $conn->error);
        }
        
        if (!$stmt->bind_param('ssisssdddi', $tipologia, $tipologia_spesa, $id_salvadanaio, $descrizione, $data_inizio, $data_scadenza, $da13, $da14, $importo, $idFamiglia)) {
            die("Errore nella bind_param: " . $stmt->error);
        }

        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    }
}

if ($action === 'duplicate_next_year') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
        return $id > 0;
    })));

    if (!$ids) {
        echo json_encode(['success' => false, 'error' => 'Nessun budget selezionato']);
        exit;
    }

    $addOneYear = static function ($date) {
        if (!$date) {
            return null;
        }

        $dt = DateTime::createFromFormat('!Y-m-d', $date);
        $errors = DateTime::getLastErrors();
        if (!$dt || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new RuntimeException('Data non valida: ' . $date);
        }

        $targetYear = (int)$dt->format('Y') + 1;
        $month = (int)$dt->format('m');
        $day = (int)$dt->format('d');

        if ($month === 2 && $day === 29 && !checkdate(2, 29, $targetYear)) {
            $day = 28;
        }

        return sprintf('%04d-%02d-%02d', $targetYear, $month, $day);
    };

    $selectStmt = $conn->prepare('SELECT id_salvadanaio, tipologia, importo, descrizione, data_inizio, data_scadenza, tipologia_spesa FROM budget WHERE id_budget=? AND id_famiglia=?');
    $insertStmt = $conn->prepare('INSERT INTO budget (tipologia, tipologia_spesa, id_salvadanaio, descrizione, data_inizio, data_scadenza, da_13esima, da_14esima, importo, id_famiglia) VALUES (?,?,?,?,?,?,?,?,?,?)');

    if (!$selectStmt || !$insertStmt) {
        if ($selectStmt) {
            $selectStmt->close();
        }
        if ($insertStmt) {
            $insertStmt->close();
        }
        echo json_encode(['success' => false, 'error' => 'Impossibile preparare la duplicazione']);
        exit;
    }

    $duplicated = 0;
    $conn->begin_transaction();

    try {
        foreach ($ids as $id) {
            $selectStmt->bind_param('ii', $id, $idFamiglia);
            if (!$selectStmt->execute()) {
                throw new RuntimeException('Errore durante la lettura del budget');
            }

            $result = $selectStmt->get_result();
            $row = $result->fetch_assoc();
            $result->free();

            if (!$row) {
                throw new RuntimeException('Budget non trovato o non appartenente alla famiglia');
            }

            $id_salvadanaio = $row['id_salvadanaio'] !== null ? (int)$row['id_salvadanaio'] : null;
            $tipologia = $row['tipologia'];
            $tipologia_spesa = $row['tipologia_spesa'];
            $descrizione = $row['descrizione'];
            $data_inizio = $addOneYear($row['data_inizio']);
            $data_scadenza = $addOneYear($row['data_scadenza']);
            $da13 = 0.0;
            $da14 = 0.0;
            $importo = (float)$row['importo'];

            $insertStmt->bind_param('ssisssdddi', $tipologia, $tipologia_spesa, $id_salvadanaio, $descrizione, $data_inizio, $data_scadenza, $da13, $da14, $importo, $idFamiglia);
            if (!$insertStmt->execute()) {
                throw new RuntimeException('Errore durante la creazione del budget duplicato');
            }

            $duplicated++;
        }

        $conn->commit();
        $selectStmt->close();
        $insertStmt->close();
        echo json_encode(['success' => true, 'duplicated' => $duplicated]);
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        $selectStmt->close();
        $insertStmt->close();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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


echo json_encode(['success' => false, 'error' => 'Azione non valida']);
