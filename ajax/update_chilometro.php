<?php
include '../includes/session_check.php';
include '../includes/db.php';
header('Content-Type: application/json');

$idUtente = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$idMezzo = (int)($_POST['id_mezzo'] ?? 0);
$id = (int)($_POST['id_chilometro'] ?? 0);
$data = $_POST['data_chilometro'] ?? '';
$chilometri = (int)($_POST['chilometri'] ?? 0);

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

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE mezzi_chilometri SET data_chilometro=?, chilometri=? WHERE id_chilometro=? AND id_mezzo=?");
    $stmt->bind_param('siii', $data, $chilometri, $id, $idMezzo);
} else {
    $stmt = $conn->prepare("INSERT INTO mezzi_chilometri (id_mezzo, data_chilometro, chilometri) VALUES (?,?,?)");
    $stmt->bind_param('isi', $idMezzo, $data, $chilometri);
}
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok]);
