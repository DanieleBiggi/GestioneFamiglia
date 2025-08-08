<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/etichette_utils.php';
$config = include __DIR__ . '/../includes/table_config.php';

$table = $_GET['table'] ?? $_POST['table'] ?? '';
if (!isset($config[$table])) {
    http_response_code(400);
    echo json_encode(['error' => 'Tabella non valida']);
    exit;
}
$primaryKey = $config[$table]['primary_key'];
$columns = $config[$table]['columns'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$actionMap = ['list' => 'view', 'insert' => 'insert', 'update' => 'update', 'delete' => 'delete'];
$permAction = $actionMap[$action] ?? '';
if (!has_permission($conn, 'table:' . $table, $permAction)) {
    http_response_code(403);
    echo json_encode(['error' => 'Permesso negato']);
    exit;
}


switch ($action) {
    case 'list':
        $search = $_GET['search'] ?? '';
        $sql = "SELECT " . implode(',', $columns) . " FROM `$table`";
        if ($search !== '') {
            $likes = [];
            foreach ($columns as $col) {
                $likes[] = "`$col` LIKE ?";
            }
            $sql .= " WHERE " . implode(' OR ', $likes);
            $stmt = $conn->prepare($sql);
            $param = "%$search%";
            $params = array_fill(0, count($columns), $param);
            $stmt->bind_param(str_repeat('s', count($columns)), ...$params);
        } else {
            $stmt = $conn->prepare($sql);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;
    case 'insert':
        $data = [];
        foreach ($columns as $col) {
            if ($col === $primaryKey) continue;
            $data[$col] = $_POST[$col] ?? null;
        }
        $cols = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('s', count($cols)), ...array_values($data));
        $stmt->execute();
        echo json_encode(['id' => $conn->insert_id]);
        break;
    case 'update':
        $id = $_POST[$primaryKey] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID mancante']);
            break;
        }
        $data = [];
        foreach ($columns as $col) {
            if ($col === $primaryKey) continue;
            if (isset($_POST[$col])) $data[$col] = $_POST[$col];
        }
        $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($data)));
        $sql = "UPDATE `$table` SET $set WHERE `$primaryKey`=?";
        $stmt = $conn->prepare($sql);
        $types = str_repeat('s', count($data)) . 'i';
        $params = array_merge(array_values($data), [$id]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;
    case 'delete':
        $id = $_POST[$primaryKey] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID mancante']);
            break;
        }
        $sql = "DELETE FROM `$table` WHERE `$primaryKey`=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        if ($success && in_array($table, ['bilancio_entrate','bilancio_uscite'], true)) {            
            eliminaEtichetteCollegate($table, (int)$id);
        }
        echo json_encode(['success' => $success]);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Azione non valida']);
        break;
}
?>
