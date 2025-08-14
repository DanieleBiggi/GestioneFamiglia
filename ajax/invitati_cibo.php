<?php
include '../includes/session_check.php';
require_once '../includes/db.php';
require_once '../includes/permissions.php';
header('Content-Type: application/json');
$action = $_POST['action'] ?? '';
try {
    switch ($action) {
        case 'add':
            if (!has_permission($conn,'table:eventi_cibo','insert')) throw new Exception('Accesso negato');
            $idFam = $_SESSION['id_famiglia_gestione'] ?? 0;
            $piatto = trim($_POST['piatto'] ?? '');
            $dolce = isset($_POST['dolce']) ? 1 : 0;
            $bere = isset($_POST['bere']) ? 1 : 0;
            $um = $_POST['um'] ?? '';
            $stmt = $conn->prepare('INSERT INTO eventi_cibo (id_famiglia,piatto,dolce,bere,um) VALUES (?,?,?,?,?)');
            $stmt->bind_param('isiis',$idFam,$piatto,$dolce,$bere,$um);
            $stmt->execute();
            echo json_encode(['success'=>true]);
            break;
        case 'update':
            if (!has_permission($conn,'table:eventi_cibo','update')) throw new Exception('Accesso negato');
            $id = (int)($_POST['id'] ?? 0);
            $piatto = trim($_POST['piatto'] ?? '');
            $dolce = isset($_POST['dolce']) ? 1 : 0;
            $bere = isset($_POST['bere']) ? 1 : 0;
            $um = $_POST['um'] ?? '';
            $stmt = $conn->prepare('UPDATE eventi_cibo SET piatto=?, dolce=?, bere=?, um=? WHERE id=?');
            $stmt->bind_param('siisi',$piatto,$dolce,$bere,$um,$id);
            $stmt->execute();
            echo json_encode(['success'=>true]);
            break;
        case 'delete':
            if (!has_permission($conn,'table:eventi_cibo','delete')) throw new Exception('Accesso negato');
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $conn->prepare('DELETE FROM eventi_cibo WHERE id=?');
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
