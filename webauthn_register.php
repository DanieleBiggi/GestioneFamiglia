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
    $userId = base64_encode(pack('N', (int)$_SESSION['utente_id']));
    $options = [
        'challenge' => base64_encode($challenge),
        'rp' => ['name' => 'Gestione Famiglia'],
        'user' => [
            'id' => $userId,
            'name' => $_SESSION['utente_username'] ?? 'user',
            'displayName' => $_SESSION['utente_username'] ?? 'user'
        ],
        'pubKeyCredParams' => [ ['type' => 'public-key', 'alg' => -7] ],
        'timeout' => 60000,
        'attestation' => 'none'
    ];
    echo json_encode($options);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false]);
    exit;
}

$credentialId = $input['rawId'] ?? '';
$publicKey = $input['response']['attestationObject'] ?? '';
$stmt = $conn->prepare("INSERT INTO webauthn_credentials (user_id, credential_id, public_key) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE public_key=VALUES(public_key)");
$stmt->bind_param('iss', $_SESSION['utente_id'], $credentialId, $publicKey);
$ok = $stmt->execute();

echo json_encode(['success' => $ok]);
