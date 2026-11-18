<?php
declare(strict_types=1);
require_once '../includes/session_check.php';
require_once '../includes/db.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Metodo non consentito.');
    $familyId = (int)($_SESSION['id_famiglia_gestione'] ?? 0);
    $userId = (int)($_SESSION['utente_id'] ?? 0);
    if ($familyId!==1 || $userId!==1) throw new RuntimeException('Non autorizzato.');
    $input = json_decode(file_get_contents('php://input'),true);
    if (!is_array($input)) throw new RuntimeException('Richiesta non valida.');
    $csrf = (string)($input['csrf'] ?? '');
    if (!$csrf || !hash_equals((string)($_SESSION['csrf_conto_etichette'] ?? ''),$csrf)) throw new RuntimeException('Sessione scaduta.');
    $ids = $input['ids'] ?? null;
    if (!is_array($ids) || count($ids)>200) throw new RuntimeException('Elenco non valido.');
    $ids = array_values(array_unique(array_map('intval',$ids)));
    foreach ($ids as $id) if ($id<=0) throw new RuntimeException('ID non valido.');
    $conn->begin_transaction();
    $stmt = $conn->prepare('UPDATE etichette_conti_condivisi SET saldata=1,data_saldo=NOW() WHERE id=? AND id_famiglia=? AND saldata=0');
    foreach ($ids as $id) {
        $stmt->bind_param('ii',$id,$familyId);
        if (!$stmt->execute() || $stmt->affected_rows!==1) throw new RuntimeException('Operazione non disponibile o già saldata: '.$id);
    }
    $stmt->close();
    $conn->commit();
    echo json_encode(['success'=>true,'updated'=>count($ids)]);
} catch (Throwable $e) {
    if (isset($conn) && method_exists($conn,'rollback')) $conn->rollback();
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
