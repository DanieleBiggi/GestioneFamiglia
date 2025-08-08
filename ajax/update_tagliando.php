<?php
include '../includes/session_check.php';
include '../includes/db.php';
header('Content-Type: application/json');

$idUtente = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$idMezzo = (int)($_POST['id_mezzo'] ?? 0);
$id = (int)($_POST['id_tagliando'] ?? 0);
$nome = $_POST['nome_tagliando'] ?? '';
$data_scadenza = $_POST['data_scadenza'] ?? '';
$attivo = isset($_POST['attivo']) ? 1 : 0;

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
    $stmt = $conn->prepare("UPDATE mezzi_tagliandi SET nome_tagliando=?, data_scadenza=?, attivo=? WHERE id_tagliando=? AND id_mezzo=?");
    $stmt->bind_param('ssiii', $nome, $data_scadenza, $attivo, $id, $idMezzo);
} else {
    $stmt = $conn->prepare("INSERT INTO mezzi_tagliandi (id_mezzo, nome_tagliando, data_scadenza, attivo) VALUES (?,?,?,?)");
    $stmt->bind_param('issi', $idMezzo, $nome, $data_scadenza, $attivo);
}
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok]);

