<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';

if (!has_permission($conn, 'ajax:delete_viaggi_alternativa', 'delete')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}

$idViaggio = (int)($_POST['id_viaggio'] ?? 0);
$idAlt = (int)($_POST['id_viaggio_alternativa'] ?? 0);

if (!$idViaggio || !$idAlt) {
    echo json_encode(['success' => false, 'error' => 'Dati mancanti']);
    exit;
}

$checkStmt = $conn->prepare('SELECT 1 FROM viaggi_alternative WHERE id_viaggio_alternativa=? AND id_viaggio=?');
$checkStmt->bind_param('ii', $idAlt, $idViaggio);
$checkStmt->execute();
$checkStmt->store_result();
if ($checkStmt->num_rows === 0) {
    $checkStmt->close();
    echo json_encode(['success' => false, 'error' => 'Alternativa non trovata']);
    exit;
}
$checkStmt->close();

$transactionStarted = false;
try {
    if (!$conn->begin_transaction()) {
        throw new Exception('Impossibile avviare la transazione');
    }
    $transactionStarted = true;

    $tables = [
        'viaggi_tratte' => 'DELETE FROM viaggi_tratte WHERE id_viaggio=? AND id_viaggio_alternativa=?',
        'viaggi_alloggi' => 'DELETE FROM viaggi_alloggi WHERE id_viaggio=? AND id_viaggio_alternativa=?',
        'viaggi_pasti' => 'DELETE FROM viaggi_pasti WHERE id_viaggio=? AND id_viaggio_alternativa=?',
        'viaggi_altri_costi' => 'DELETE FROM viaggi_altri_costi WHERE id_viaggio=? AND id_viaggio_alternativa=?'
    ];

    foreach ($tables as $sql) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Errore durante la preparazione della query');
        }
        $stmt->bind_param('ii', $idViaggio, $idAlt);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Errore durante l\'eliminazione dei dati collegati');
        }
        $stmt->close();
    }

    $delAlt = $conn->prepare('DELETE FROM viaggi_alternative WHERE id_viaggio_alternativa=? AND id_viaggio=?');
    if (!$delAlt) {
        throw new Exception('Errore durante la preparazione della query di eliminazione');
    }
    $delAlt->bind_param('ii', $idAlt, $idViaggio);
    if (!$delAlt->execute()) {
        $delAlt->close();
        throw new Exception('Errore durante l\'eliminazione dell\'alternativa');
    }
    $delAlt->close();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $ex) {
    if (!empty($transactionStarted)) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
}
