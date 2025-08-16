<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['utente_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $challenge = random_bytes(32);
    $_SESSION['webauthn_challenge'] = base64_encode($challenge);
    $stmt = $conn->prepare('SELECT credential_id FROM webauthn_credentials WHERE user_id = ?');
    $stmt->bind_param('i', $_SESSION['utente_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $creds = [];
    while ($row = $res->fetch_assoc()) {
        $creds[] = ['type' => 'public-key', 'id' => base64_encode($row['credential_id'])];
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

$credentialId = $input['id'] ?? '';
$stmt = $conn->prepare('SELECT public_key FROM webauthn_credentials WHERE user_id = ? AND credential_id = ?');
$stmt->bind_param('is', $_SESSION['utente_id'], $credentialId);
$stmt->execute();
$pub = $stmt->get_result()->fetch_assoc();
if (!$pub) {
    echo json_encode(['success' => false]);
    exit;
}

// TODO: verify signature using $pub['public_key'] and $_SESSION['webauthn_challenge']

echo json_encode(['success' => true]);
