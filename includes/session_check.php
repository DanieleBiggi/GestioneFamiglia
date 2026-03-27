<?php
session_start();

if (!isset($_SESSION['utente_id'])) {
    require_once __DIR__ . '/db.php';

    $token = $_COOKIE['device_token'] ?? '';
    if ($token !== '') {
        $stmt = $conn->prepare('SELECT id_utente, user_agent FROM dispositivi_riconosciuti WHERE token_dispositivo = ? AND scadenza >= NOW() LIMIT 1');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $device = $res->fetch_assoc();
            if (($device['user_agent'] ?? '') === ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
                $userStmt = $conn->prepare('SELECT id, nome, id_famiglia_gestione, id_tema FROM utenti WHERE id = ? AND attivo = 1 LIMIT 1');
                $userStmt->bind_param('i', $device['id_utente']);
                $userStmt->execute();
                $userRes = $userStmt->get_result();

                if ($userRes->num_rows === 1) {
                    $user = $userRes->fetch_assoc();

                    $_SESSION['utente_id'] = (int)$user['id'];
                    $_SESSION['utente_nome'] = $user['nome'] ?? '';
                    $_SESSION['id_famiglia_gestione'] = (int)($user['id_famiglia_gestione'] ?? 0);
                    $_SESSION['theme_id'] = (int)($user['id_tema'] ?? 1);
                    $_SESSION['auth_level'] = 'device';

                    $lvlStmt = $conn->prepare('SELECT userlevelid FROM utenti2famiglie WHERE id_utente = ? AND id_famiglia = ? LIMIT 1');
                    $lvlStmt->bind_param('ii', $_SESSION['utente_id'], $_SESSION['id_famiglia_gestione']);
                    $lvlStmt->execute();
                    $lvlRes = $lvlStmt->get_result();
                    $_SESSION['userlevelid'] = ($lvlRes->num_rows === 1) ? (int)$lvlRes->fetch_assoc()['userlevelid'] : 0;
                    $lvlStmt->close();
                }

                $userStmt->close();
            }
        }

        $stmt->close();
    }
}

if (!isset($_SESSION['utente_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['auth_level'])) {
    $_SESSION['auth_level'] = 'strong';
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
if ($_SESSION['auth_level'] !== 'strong' && preg_match('#/(index\.php)?$#', $requestPath)) {
    header('Location: /Gestionale25/turni.php');
    exit;
}
?>
