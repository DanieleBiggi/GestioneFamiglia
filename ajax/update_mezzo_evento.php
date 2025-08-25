<?php
include '../includes/session_check.php';
include '../includes/db.php';
header('Content-Type: application/json');

$idUtente = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$idMezzo = (int)($_POST['id_mezzo'] ?? 0);
$idEvento = (int)($_POST['id_evento'] ?? 0);
$idTipo = (int)($_POST['id_tipo_evento'] ?? 0);
$dataEvento = $_POST['data_evento'] ?? '';
$kmEvento = (int)($_POST['km_evento'] ?? 0);
$note = $_POST['note'] ?? '';

$stmt = $conn->prepare("SELECT id_utente FROM mezzi WHERE id_mezzo=? AND id_famiglia=?");
$stmt->bind_param('ii', $idMezzo, $idFamiglia);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row || (int)$row['id_utente'] !== $idUtente) {
    echo json_encode(['success' => false, 'error' => 'Operazione non autorizzata']);
    exit;
}

if ($idEvento > 0) {
    $stmt = $conn->prepare("UPDATE mezzi_eventi SET id_tipo_evento=?, data_evento=?, km_evento=?, note=? WHERE id_evento=? AND id_mezzo=?");
    $stmt->bind_param('isissi', $idTipo, $dataEvento, $kmEvento, $note, $idEvento, $idMezzo);
} else {
    $stmt = $conn->prepare("INSERT INTO mezzi_eventi (id_mezzo, id_tipo_evento, data_evento, km_evento, note) VALUES (?,?,?,?,?)");
    $stmt->bind_param('iisis', $idMezzo, $idTipo, $dataEvento, $kmEvento, $note);
}
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok]);
