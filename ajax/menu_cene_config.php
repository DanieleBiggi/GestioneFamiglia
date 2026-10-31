<?php
header('Content-Type: application/json; charset=utf-8');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
require_once '../includes/menu_cene_master.php';
$idFamiglia = (int)($_SESSION['id_famiglia_gestione'] ?? 0);
$kind = $_GET['kind'] ?? $_POST['kind'] ?? '';
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'update' : 'view';
if ($idFamiglia <= 0 || !in_array($kind, ['master', 'ricette'], true)
    || !has_permission($conn, 'page:menu_cene_config.php', $action)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($kind === 'master') {
            $data = food_config_read($idFamiglia, 'master');
            if ($data === null) {
                $data = [
                    'version' => 1,
                    'anno_scolastico' => '2026-2027',
                    'ciclo' => ['settimane' => 4, 'data_riferimento' => '2026-09-14', 'settimana_riferimento' => 2],
                    'stagioni' => menu_cene_master()
                ];
            }
        } else {
            $data = food_config_recipes($idFamiglia);
        }
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['menu_config_csrf'] ?? '', $token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sessione scaduta: ricarica la pagina']);
            exit;
        }
        $raw = $_POST['json'] ?? '';
        if (!is_string($raw) || strlen($raw) > 250000) throw new InvalidArgumentException('JSON troppo grande');
        $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new InvalidArgumentException('Il JSON deve essere un oggetto');
        food_config_save($idFamiglia, $kind, $data);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Metodo non supportato']);
    }
} catch (InvalidArgumentException | JsonException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Configurazione menù: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Impossibile leggere o salvare la configurazione']);
}
