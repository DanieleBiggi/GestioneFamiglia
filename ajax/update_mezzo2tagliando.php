<?php
include '../includes/session_check.php';
include '../includes/db.php';
header('Content-Type: application/json');

$idUtente = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$idTagliando = (int)($_POST['id_tagliando'] ?? 0);
$id = (int)($_POST['id_record'] ?? 0);
$data = $_POST['data_tagliando'] ?? '';
$km = (int)($_POST['chilometri'] ?? 0);

$stmt = $conn->prepare("SELECT m.id_utente, m.id_famiglia FROM mezzi_tagliandi mt JOIN mezzi m ON mt.id_mezzo = m.id_mezzo WHERE mt.id_tagliando=?");
$stmt->bind_param('i', $idTagliando);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row || (int)$row['id_utente'] !== $idUtente || (int)$row['id_famiglia'] !== $idFamiglia) {
    echo json_encode(['success' => false, 'error' => 'Operazione non autorizzata']);
    exit;
}

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE mezzi_mezzi2tagliandi SET data_tagliando=?, chilometri=? WHERE id_record=? AND id_tagliando=?");
    $stmt->bind_param('siii', $data, $km, $id, $idTagliando);
} else {
    $stmt = $conn->prepare("INSERT INTO mezzi_mezzi2tagliandi (id_tagliando, data_tagliando, chilometri) VALUES (?,?,?)");
    $stmt->bind_param('isi', $idTagliando, $data, $km);
}
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok]);

