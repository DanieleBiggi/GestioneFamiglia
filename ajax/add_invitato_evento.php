<?php
include '../includes/session_check.php';
require_once '../includes/db.php';
require_once '../includes/permissions.php';
header('Content-Type: application/json');
if (!has_permission($conn, 'table:eventi_invitati', 'insert')) {
    echo json_encode(['success'=>false,'error'=>'Accesso negato']);
    exit;
}
$nome = trim($_POST['nome'] ?? '');
$cognome = trim($_POST['cognome'] ?? '');
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
if ($nome === '' || $cognome === '' || !$idFamiglia) {
    echo json_encode(['success'=>false,'error'=>'Dati mancanti']);
    exit;
}
$conn->begin_transaction();
try {
    $stmt = $conn->prepare('SELECT i.id FROM eventi_invitati i JOIN eventi_invitati2famiglie f ON i.id=f.id_invitato WHERE f.id_famiglia=? AND f.attivo=1 AND i.nome=? AND i.cognome=?');
    $stmt->bind_param('iss', $idFamiglia, $nome, $cognome);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $idInv = (int)$row['id'];
    } else {
        $stmt = $conn->prepare('INSERT INTO eventi_invitati (nome,cognome) VALUES (?,?)');
        $stmt->bind_param('ss', $nome, $cognome);
        $stmt->execute();
        $idInv = $stmt->insert_id;
    }
    $stmt = $conn->prepare('SELECT id_i2f FROM eventi_invitati2famiglie WHERE id_invitato=? AND id_famiglia=? AND attivo=1');
    $stmt->bind_param('ii', $idInv, $idFamiglia);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res->num_rows) {
        $stmt = $conn->prepare('INSERT INTO eventi_invitati2famiglie (id_invitato,id_famiglia,data_inizio) VALUES (?,?,CURDATE())');
        $stmt->bind_param('ii', $idInv, $idFamiglia);
        $stmt->execute();
    }
    $conn->commit();
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
