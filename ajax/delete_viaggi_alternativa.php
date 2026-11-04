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

if (empty($_SESSION['vacanze_csrf']) || !hash_equals($_SESSION['vacanze_csrf'], (string)($_POST['vacanze_csrf'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token di sicurezza non valido']);
    exit;
}

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

    // Le spese appartengono alla vacanza e non devono essere eliminate.
    // La FK legacy verso l'alternativa impedirebbe la cancellazione:
    // la azzeriamo, conservando tutte le spese e le nuove associazioni.
    foreach (['viaggi_tratte', 'viaggi_alloggi', 'viaggi_pasti', 'viaggi_altri_costi'] as $table) {
        $stmt = $conn->prepare("UPDATE `$table` SET id_viaggio_alternativa=NULL WHERE id_viaggio=? AND id_viaggio_alternativa=?");
        if (!$stmt) { throw new Exception('Preparazione aggiornamento fallita'); }
        $stmt->bind_param('ii', $idViaggio, $idAlt);
        if (!$stmt->execute()) { throw new Exception('Impossibile scollegare le spese'); }
        $stmt->close();
    }
    // Le FK ON DELETE CASCADE eliminano i collegamenti, non le spese.
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
} catch (Throwable $ex) {
    if (!empty($transactionStarted)) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
}
