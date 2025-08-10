<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$idMovimento = intval($_POST['id_movimento'] ?? 0);
$src = $_POST['src'] ?? 'movimenti_revolut';
$idCaricamento = intval($_POST['id_caricamento'] ?? 0);
$idSupermercato = intval($_POST['id_supermercato'] ?? 0);
$dataScontrino = $_POST['data_scontrino'] ?? null;
$totaleScontrino = str_replace(',', '.', $_POST['totale_scontrino'] ?? '');
$totaleScontrino = $totaleScontrino !== '' ? floatval($totaleScontrino) : 0;
$descrizione = trim($_POST['descrizione'] ?? '');

$allowedSources = ['movimenti_revolut','bilancio_entrate','bilancio_uscite'];
if (!$idMovimento || !in_array($src, $allowedSources, true)) {
    echo json_encode(['success' => false, 'error' => 'Parametri non validi']);
    exit;
}

$uploadDir = __DIR__ . '/../files/scontrini/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$nomeFile = null;
if (!empty($_FILES['nome_file']['name'])) {
    $nomeFile = basename($_FILES['nome_file']['name']);
    $target = $uploadDir . $nomeFile;
    if (!move_uploaded_file($_FILES['nome_file']['tmp_name'], $target)) {
        echo json_encode(['success' => false, 'error' => 'Upload fallito']);
        exit;
    }
}

if ($idCaricamento > 0) {
    $fields = [];
    $params = [];
    $types = '';
    if ($nomeFile !== null) {
        $fields[] = 'nome_file=?';
        $params[] = $nomeFile;
        $types .= 's';
    }
    $fields[] = 'id_supermercato=?';
    $params[] = $idSupermercato;
    $types .= 'i';
    $fields[] = 'data_scontrino=?';
    $params[] = $dataScontrino ?: null;
    $types .= 's';
    $fields[] = 'totale_scontrino=?';
    $params[] = $totaleScontrino;
    $types .= 'd';
    $fields[] = 'descrizione=?';
    $params[] = $descrizione;
    $types .= 's';
    $params[] = $idCaricamento;
    $types .= 'i';
    $sql = 'UPDATE ocr_caricamenti SET ' . implode(',', $fields) . ' WHERE id_caricamento=?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
} else {
    if ($nomeFile === null) {
        echo json_encode(['success' => false, 'error' => 'File obbligatorio']);
        exit;
    }
    $stmt = $conn->prepare('INSERT INTO ocr_caricamenti (id_utente, id_supermercato, nome_file, data_scontrino, totale_scontrino, indirizzo_ip, JSON_linee, descrizione) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $idUtente = $_SESSION['utente_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $jsonLinee = '[]';
    $stmt->bind_param('iissdsss', $idUtente, $idSupermercato, $nomeFile, $dataScontrino, $totaleScontrino, $ip, $jsonLinee, $descrizione);
    $stmt->execute();
    $idCaricamento = $stmt->insert_id;
    $stmt->close();

    $idFields = [
        'movimenti_revolut' => 'id_movimento_revolut',
        'bilancio_entrate'  => 'id_entrata',
        'bilancio_uscite'   => 'id_uscita'
    ];
    $stmt = $conn->prepare("UPDATE $src SET id_caricamento=? WHERE {$idFields[$src]}=?");
    $stmt->bind_param('ii', $idCaricamento, $idMovimento);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['success' => true]);
