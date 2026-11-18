<?php
declare(strict_types=1);
require_once '../includes/session_check.php';
require_once '../includes/db.php';
header('Content-Type: application/json; charset=utf-8');
$inTransaction = false;
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Metodo non consentito.');
    $familyId = (int)($_SESSION['id_famiglia_gestione'] ?? 0);
    $userId = (int)($_SESSION['utente_id'] ?? 0);
    if ($familyId!==1 || $userId!==1) throw new RuntimeException('Non autorizzato.');
    $input = json_decode(file_get_contents('php://input'),true);
    if (!is_array($input)) throw new RuntimeException('Richiesta non valida.');
    $csrf = (string)($input['csrf'] ?? '');
    if (!$csrf || !hash_equals((string)($_SESSION['csrf_conto_etichette'] ?? ''),$csrf)) throw new RuntimeException('Sessione scaduta.');
    $accountIds = $input['ids'] ?? null;
    $legacyIds = $input['quote_ids'] ?? null;
    if (!is_array($accountIds) || !is_array($legacyIds) || count($accountIds)+count($legacyIds)>200) throw new RuntimeException('Elenco non valido.');
    foreach (array_merge($accountIds,$legacyIds) as $id) {
        if (!is_numeric($id) || (int)$id<1) throw new RuntimeException('ID non valido.');
    }
    $accountIds=array_values(array_unique(array_map('intval',$accountIds)));
    $legacyIds=array_values(array_unique(array_map('intval',$legacyIds)));
    if (!$accountIds && !$legacyIds) throw new RuntimeException('Nessuna operazione selezionata.');
    $conn->begin_transaction();
    $inTransaction = true;
    $stmt = $conn->prepare('UPDATE etichette_conti_condivisi SET saldata=1,data_saldo=NOW() WHERE id=? AND id_famiglia=? AND saldata=0');
    foreach ($accountIds as $id) {
        $stmt->bind_param('ii',$id,$familyId);
        if (!$stmt->execute() || $stmt->affected_rows!==1) throw new RuntimeException('Compensazione già saldata o non disponibile: '.$id);
    }
    $stmt->close();
    $legacy = $conn->prepare('UPDATE bilancio_utenti2operazioni_etichettate SET saldata=1,data_saldo=NOW() WHERE id_u2o=? AND saldata=0');
    foreach ($legacyIds as $id) {
        $legacy->bind_param('i',$id);
        if (!$legacy->execute() || $legacy->affected_rows!==1) throw new RuntimeException('Quota già saldata o non disponibile: '.$id);
    }
    $legacy->close();
    $conn->commit();
    $inTransaction = false;
    echo json_encode(['success'=>true,'updated'=>count($accountIds)+count($legacyIds)]);
} catch (Throwable $e) {
    if ($inTransaction) $conn->rollback();
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
