<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permissions.php';
$action = $_GET['action'] ?? $_POST['action'] ?? '';
switch ($action) {
    case 'list':
        if (!has_permission($conn, 'table:utenti', 'view')) { http_response_code(403); echo json_encode(['error'=>'Permesso negato']); exit; }
        $search = $_GET['search'] ?? ($_GET['username'] ?? '');
        $userlevelid = $_GET['userlevelid'] ?? '';
        $id_famiglia = $_GET['id_famiglia'] ?? '';
        $sql = "SELECT u.id, u.username, u.nome, u.cognome, u.soprannome, u.email, u.id_famiglia_attuale, u.id_famiglia_gestione, u.attivo, u.userlevelid,
                       ul.userlevelname, f.nome_famiglia AS famiglia_attuale,
                       GROUP_CONCAT(CONCAT(f2.nome_famiglia, ' (', ul2.userlevelname, ')') ORDER BY f2.nome_famiglia SEPARATOR ', ') AS famiglie
                FROM utenti u
                LEFT JOIN userlevels ul ON u.userlevelid = ul.userlevelid
                LEFT JOIN famiglie f ON u.id_famiglia_attuale = f.id_famiglia
                LEFT JOIN utenti2famiglie u2f ON u.id = u2f.id_utente
                LEFT JOIN famiglie f2 ON u2f.id_famiglia = f2.id_famiglia
                LEFT JOIN userlevels ul2 ON u2f.userlevelid = ul2.userlevelid
                WHERE 1=1";
        $params = [];
        $types = '';
        if ($search !== '') {
            $sql .= " AND (u.username LIKE ? OR u.nome LIKE ? OR u.cognome LIKE ? OR u.email LIKE ?)";
            $wild = "%$search%";
            $params[] = $wild; $params[] = $wild; $params[] = $wild; $params[] = $wild;
            $types .= 'ssss';
        }
        if ($userlevelid !== '') { $sql .= " AND u.userlevelid = ?"; $params[] = $userlevelid; $types .= 'i'; }
        if ($id_famiglia !== '') {
            $sql .= " AND (u.id_famiglia_attuale = ? OR EXISTS (SELECT 1 FROM utenti2famiglie u2 WHERE u2.id_utente = u.id AND u2.id_famiglia = ?))";
            $params[] = $id_famiglia; $params[] = $id_famiglia; $types .= 'ii';
        }
        $sql .= " GROUP BY u.id";
        $stmt = $conn->prepare($sql);
        if ($params) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $res = $stmt->get_result();
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
        break;
    case 'families':
        if (!has_permission($conn, 'table:utenti2famiglie', 'view')) { http_response_code(403); echo json_encode(['error'=>'Permesso negato']); exit; }
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT f.id_famiglia, f.nome_famiglia, u2f.userlevelid FROM famiglie f LEFT JOIN utenti2famiglie u2f ON f.id_famiglia = u2f.id_famiglia AND u2f.id_utente = ? ORDER BY f.nome_famiglia");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        echo json_encode($res->fetch_all(MYSQLI_ASSOC));
        break;
    case 'save_families':
        if (!has_permission($conn, 'table:utenti2famiglie', 'update')) { http_response_code(403); echo json_encode(['error'=>'Permesso negato']); exit; }
        $id = intval($_POST['id'] ?? 0);
        $famiglie = $_POST['famiglie'] ?? [];
        $userlevels = $_POST['userlevels'] ?? [];
        if (!is_array($famiglie) || !is_array($userlevels) || count($famiglie) !== count($userlevels)) { http_response_code(400); echo json_encode(['error'=>'Dati invalidi']); exit; }
        $conn->begin_transaction();
        $del = $conn->prepare("DELETE FROM utenti2famiglie WHERE id_utente=?");
        $del->bind_param('i', $id);
        $del->execute();
        if (!empty($famiglie)) {
            $ins = $conn->prepare("INSERT INTO utenti2famiglie (id_utente, id_famiglia, userlevelid) VALUES (?,?,?)");
            for ($i=0; $i<count($famiglie); $i++) {
                $fid = intval($famiglie[$i]);
                $ul = intval($userlevels[$i]);
                $ins->bind_param('iii', $id, $fid, $ul);
                $ins->execute();
            }
        }
        $conn->commit();
        echo json_encode(['success'=>true]);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error'=>'Azione non valida']);
}
