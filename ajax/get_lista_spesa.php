<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function lista_spesa_json(int $status, array $data): never {
    http_response_code($status);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        http_response_code(500);
        $json = '{"success":false,"error":"Errore codifica risposta JSON"}';
    }
    echo $json;
    exit;
}

// Non reindirizzare le chiamate AJAX a una pagina HTML di login.
session_start();
if (empty($_SESSION['utente_id'])) {
    lista_spesa_json(401, ['success'=>false, 'error'=>'Sessione scaduta: effettua nuovamente l’accesso']);
}

try {
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/permissions.php';

    if (!has_permission($conn, 'ajax:get_lista_spesa', 'view')) {
        lista_spesa_json(403, ['success'=>false, 'error'=>'Accesso negato']);
    }
    $familyId = (int)($_SESSION['id_famiglia_gestione'] ?? 0);
    if ($familyId < 1) {
        lista_spesa_json(403, ['success'=>false, 'error'=>'Famiglia non selezionata']);
    }
    $conn->set_charset('utf8mb4');
    $stmt = $conn->prepare(
        'SELECT id, nome, quantita, note, checked FROM lista_spesa WHERE id_famiglia = ? ORDER BY checked ASC, created_at DESC'
    );
    $stmt->bind_param('i', $familyId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    lista_spesa_json(200, ['success'=>true, 'items'=>$items]);
} catch (Throwable $e) {
    error_log('GF ajax/get_lista_spesa.php: ' . get_class($e) . ' code=' . (int)$e->getCode());
    lista_spesa_json(500, ['success'=>false, 'error'=>'Impossibile caricare la lista della spesa']);
}
