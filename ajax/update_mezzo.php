<?php
include '../includes/session_check.php';
include '../includes/db.php';
header('Content-Type: application/json');

$idUtente = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$id = (int)($_POST['id_mezzo'] ?? 0);
$nome = $_POST['nome_mezzo'] ?? '';
$data = $_POST['data_immatricolazione'] ?? '';
$consumo = $_POST['consumo_litri_100km'] !== '' ? (float)$_POST['consumo_litri_100km'] : null;
$attivo = isset($_POST['attivo']) ? 1 : 0;

$stmt = $conn->prepare("SELECT id_utente FROM mezzi WHERE id_mezzo=? AND id_famiglia=?");
$stmt->bind_param('ii', $id, $idFamiglia);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row || (int)$row['id_utente'] !== $idUtente) {
    echo json_encode(['success' => false, 'error' => 'Operazione non autorizzata']);
    exit;
}

$stmt = $conn->prepare("UPDATE mezzi SET nome_mezzo=?, data_immatricolazione=?, consumo_litri_100km=?, attivo=? WHERE id_mezzo=? AND id_famiglia=?");
$stmt->bind_param('ssdiii', $nome, $data, $consumo, $attivo, $id, $idFamiglia);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok]);
