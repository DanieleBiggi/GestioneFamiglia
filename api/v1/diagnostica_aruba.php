<?php
declare(strict_types=1);

// Diagnostica temporanea. Nessun accesso a database, sessioni o credenziali.
// ELIMINARE il file dal server al termine della verifica.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo non consentito']);
    exit;
}

$auth = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? null;
$source = $auth !== null ? 'server' : 'none';

if ($auth === null && function_exists('getallheaders')) {
    foreach (getallheaders() as $key => $value) {
        if (strcasecmp((string)$key, 'Authorization') === 0 && is_string($value)) {
            $auth = $value;
            $source = 'getallheaders';
            break;
        }
    }
}

// Non mostrare mai il valore ricevuto, neppure in caso di errore.
echo json_encode([
    'ok' => true,
    'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION,
    'requirements' => [
        'mysqli' => extension_loaded('mysqli'),
        'json' => extension_loaded('json'),
        'hash_equals' => function_exists('hash_equals'),
        'getenv' => function_exists('getenv')
    ],
    'authorization_header_received' => is_string($auth) && $auth !== '',
    'authorization_source' => $source,
    // Si controlla solo la presenza, non il contenuto del valore.
    'custom_header_received' => isset($_SERVER['HTTP_X_GF_API_KEY']) && $_SERVER['HTTP_X_GF_API_KEY'] !== ''
], JSON_UNESCAPED_SLASHES);
