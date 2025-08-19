<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db.php';

$token = $_COOKIE['device_token'] ?? '';
if (!$token) {
    http_response_code(403);
    echo json_encode(['error' => 'not authenticated']);
    exit;
}

$stmt = $conn->prepare('SELECT id_utente FROM dispositivi_riconosciuti WHERE token_dispositivo = ? AND scadenza >= NOW() LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows !== 1) {
    http_response_code(403);
    echo json_encode(['error' => 'not authenticated']);
    exit;
}
$userId = (int)$res->fetch_assoc()['id_utente'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $challenge = random_bytes(32);
    $_SESSION['webauthn_challenge'] = base64_encode($challenge);
    $_SESSION['webauthn_user'] = $userId;
    $stmt = $conn->prepare('SELECT credential_id FROM webauthn_credentials WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $creds = [];
    while ($row = $res->fetch_assoc()) {
        $creds[] = ['type' => 'public-key', 'id' => $row['credential_id']];
    }
    echo json_encode([
        'challenge' => base64_encode($challenge),
        'allowCredentials' => $creds,
        'timeout' => 60000
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false]);
    exit;
}

if (!isset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_user']) || $_SESSION['webauthn_user'] !== $userId) {
    http_response_code(403);
    echo json_encode(['error' => 'not authenticated']);
    exit;
}

$credentialId = $input['rawId'] ?? '';
$stmt = $conn->prepare('SELECT public_key FROM webauthn_credentials WHERE user_id = ? AND credential_id = ?');
$stmt->bind_param('is', $userId, $credentialId);
$stmt->execute();
$pub = $stmt->get_result()->fetch_assoc();
if (!$pub) {
    echo json_encode(['success' => false]);
    exit;
}

// TODO: verify signature using $pub['public_key'] and $_SESSION['webauthn_challenge']

// After successful verification, establish the session for the user
$userStmt = $conn->prepare('SELECT nome, id_famiglia_gestione, id_tema FROM utenti WHERE id = ?');
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

$_SESSION['utente_id'] = $userId;
$_SESSION['utente_nome'] = $user['nome'] ?? '';
$_SESSION['id_famiglia_gestione'] = $user['id_famiglia_gestione'] ?? 0;
$_SESSION['theme_id'] = (int)($user['id_tema'] ?? 1);

$lvlStmt = $conn->prepare('SELECT userlevelid FROM utenti2famiglie WHERE id_utente = ? AND id_famiglia = ? LIMIT 1');
$lvlStmt->bind_param('ii', $_SESSION['utente_id'], $_SESSION['id_famiglia_gestione']);
$lvlStmt->execute();
$lvlRes = $lvlStmt->get_result();
$_SESSION['userlevelid'] = ($lvlRes->num_rows === 1) ? intval($lvlRes->fetch_assoc()['userlevelid']) : 0;

$newExp = date('Y-m-d H:i:s', time() + 60*60*24*30);
$upd = $conn->prepare('UPDATE dispositivi_riconosciuti SET scadenza = ? WHERE token_dispositivo = ?');
$upd->bind_param('ss', $newExp, $token);
$upd->execute();

unset($_SESSION['webauthn_user'], $_SESSION['webauthn_challenge']);

echo json_encode(['success' => true]);
