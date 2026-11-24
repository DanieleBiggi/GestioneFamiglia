<?php
header('Content-Type: application/json; charset=utf-8');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
require_once '../includes/menu_cene_master.php';
$conn->set_charset('utf8mb4');
$idFamiglia = (int)($_SESSION['id_famiglia_gestione'] ?? 0);
$kind = $_GET['kind'] ?? $_POST['kind'] ?? '';
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'update' : 'view';
if ($idFamiglia <= 0 || !in_array($kind, ['master', 'ricette'], true)
    || !has_permission($conn, 'page:menu_cene_config.php', $action)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}
function menu_editor_revision(int $idFamiglia, string $kind, array $data): string {
    return hash('sha256', json_encode([$idFamiglia, $kind, $data], JSON_THROW_ON_ERROR));
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $active = menu_rel_read($conn, $idFamiglia);
        $data = $kind === 'master'
            ? ($active ?? food_config_read($idFamiglia, 'master') ?? [
                'version' => 1,
                'anno_scolastico' => '2026-2027',
                'ciclo' => ['settimane' => 4, 'data_riferimento' => '2026-09-14', 'settimana_riferimento' => 2],
                'stagioni' => menu_cene_default_master()
            ])
            : ($active === null ? food_config_recipes($idFamiglia) : menu_rel_recipes($conn, $idFamiglia));
        echo json_encode([
            'success' => true, 'data' => $data,
            'revision' => $active === null ? null : menu_editor_revision($idFamiglia, $kind, $data)
        ], JSON_UNESCAPED_UNICODE);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || empty($_SESSION['menu_config_csrf']) || !hash_equals($_SESSION['menu_config_csrf'], $token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sessione scaduta: ricarica la pagina']);
            exit;
        }
        $raw = $_POST['json'] ?? '';
        if (!is_string($raw) || strlen($raw) > 250000) throw new InvalidArgumentException('JSON troppo grande');
        $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new InvalidArgumentException('Il JSON deve essere un oggetto');
        $conn->begin_transaction();
        try {
            menu_rel_lock_family($conn, $idFamiglia);
            $active = menu_rel_read($conn, $idFamiglia, true);
            $revision = $_POST['revision'] ?? '';
            $current = $kind === 'master' ? $active : menu_rel_recipes($conn, $idFamiglia);
            if ($active === null || !is_string($revision) || !hash_equals(menu_editor_revision($idFamiglia, $kind, $current), $revision)) {
                $conn->rollback();
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => $active === null
                    ? 'Configurazione relazionale non attivata'
                    : 'Configurazione cambiata: ricarica la pagina prima di salvare']);
                exit;
            }
            if ($kind === 'master') {
                menu_rel_save($conn, $idFamiglia, $data);
                $saved = menu_rel_read($conn, $idFamiglia);
            } else {
                menu_rel_save_recipes($conn, $idFamiglia, $data);
                $saved = menu_rel_recipes($conn, $idFamiglia);
            }
            $conn->commit();
            echo json_encode(['success' => true, 'revision' => menu_editor_revision($idFamiglia, $kind, $saved)]);
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
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
