<?php
include '../includes/session_check.php';
require_once '../includes/db.php';
require_once '../includes/permissions.php';
header('Content-Type: application/json');
$action = $_POST['action'] ?? '';
try {
    switch ($action) {
        case 'update_invitato':
            if (!has_permission($conn,'table:eventi_invitati','update')) throw new Exception('Accesso negato');
            $id = (int)($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $cognome = trim($_POST['cognome'] ?? '');
            $stmt = $conn->prepare('UPDATE eventi_invitati SET nome=?, cognome=? WHERE id=?');
            $stmt->bind_param('ssi',$nome,$cognome,$id);
            $stmt->execute();
            echo json_encode(['success'=>true]);
            break;
        case 'add_famiglia':
            if (!has_permission($conn,'table:eventi_invitati2famiglie','insert')) throw new Exception('Accesso negato');
            $idInv = (int)($_POST['id_invitato'] ?? ($_POST['id'] ?? 0));
            $idFam = (int)($_POST['id_famiglia'] ?? 0);
            $inizio = $_POST['data_inizio'] ?? '';
            $fine = $_POST['data_fine'] ?? '9999-12-31';
            $attivo = isset($_POST['attivo']) ? 1 : 0;
            $stmt = $conn->prepare('INSERT INTO eventi_invitati2famiglie (id_invitato,id_famiglia,data_inizio,data_fine,attivo) VALUES (?,?,?,?,?)');
            $stmt->bind_param('iissi',$idInv,$idFam,$inizio,$fine,$attivo);
            $stmt->execute();
            echo json_encode(['success'=>true]);
            break;
        case 'update_famiglia':
            if (!has_permission($conn,'table:eventi_invitati2famiglie','update')) throw new Exception('Accesso negato');
            $id = (int)($_POST['id'] ?? 0);
            $idFam = (int)($_POST['id_famiglia'] ?? 0);
            $inizio = $_POST['data_inizio'] ?? '';
            $fine = $_POST['data_fine'] ?? '9999-12-31';
            $attivo = isset($_POST['attivo']) ? 1 : 0;
            $stmt = $conn->prepare('UPDATE eventi_invitati2famiglie SET id_famiglia=?, data_inizio=?, data_fine=?, attivo=? WHERE id_i2f=?');
            $stmt->bind_param('issii',$idFam,$inizio,$fine,$attivo,$id);
            $stmt->execute();
            echo json_encode(['success'=>true]);
            break;
        case 'delete_famiglia':
            if (!has_permission($conn,'table:eventi_invitati2famiglie','delete')) throw new Exception('Accesso negato');
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $conn->prepare('DELETE FROM eventi_invitati2famiglie WHERE id_i2f=?');
            $stmt->bind_param('i',$id);
            $stmt->execute();
            echo json_encode(['success'=>true]);
            break;
        default:
            throw new Exception('Azione non valida');
    }
} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
