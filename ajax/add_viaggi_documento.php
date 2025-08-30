<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:add_viaggi_documento', 'insert')) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Accesso negato']);
    exit;
}
$idViaggio = (int)($_POST['id_viaggio'] ?? 0);
if (!$idViaggio || empty($_FILES['file']['name'])) {
    echo json_encode(['success'=>false,'error'=>'Dati mancanti']);
    exit;
}
$uploadDir = __DIR__ . '/../files/vacanze/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$nomeFile = basename($_FILES['file']['name']);
$target = $uploadDir . $nomeFile;
if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
    echo json_encode(['success'=>false,'error'=>'Upload fallito']);
    exit;
}
$idUtente = $_SESSION['id'] ?? 0;
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$idSupermercato = 0;
$dataScontrino = null;
$totale = 0;
$jsonLinee = '[]';
$descrizione = null;
$stmt = $conn->prepare('INSERT INTO ocr_caricamenti (id_utente, id_supermercato, nome_file, data_scontrino, totale_scontrino, indirizzo_ip, JSON_linee, descrizione) VALUES (?,?,?,?,?,?,?,?)');
$stmt->bind_param('iissdsss', $idUtente, $idSupermercato, $nomeFile, $dataScontrino, $totale, $ip, $jsonLinee, $descrizione);
$stmt->execute();
$idCaricamento = $stmt->insert_id;
$stmt->close();
$stmt = $conn->prepare('INSERT INTO viaggi2caricamenti (id_viaggio, id_caricamento) VALUES (?,?)');
$stmt->bind_param('ii', $idViaggio, $idCaricamento);
$ok = $stmt->execute();
$stmt->close();
echo json_encode(['success'=>$ok]);
?>
