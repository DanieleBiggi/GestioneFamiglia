<?php
include '../includes/session_check.php';
include '../includes/db.php';
header('Content-Type: application/json');

$idUtente = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$id = (int)($_POST['id_chilometro'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare("SELECT m.id_utente, m.id_famiglia FROM mezzi_chilometri mc JOIN mezzi m ON m.id_mezzo = mc.id_mezzo WHERE mc.id_chilometro = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row || (int)$row['id_utente'] !== $idUtente || (int)$row['id_famiglia'] !== $idFamiglia) {
    echo json_encode(['success' => false, 'error' => 'Operazione non autorizzata']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM mezzi_chilometri WHERE id_chilometro=?");
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok]);
