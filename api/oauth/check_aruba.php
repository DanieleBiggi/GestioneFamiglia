<?php
declare(strict_types=1);
// DIAGNOSTICA TEMPORANEA. Rimuovere prima del merge.
// Richiede il token API già valido in X-GF-Api-Key, non espone segreti.
require_once __DIR__ . '/../v1/bootstrap.php';
api_method('GET');
$header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($header === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $name=>$value) {
        if (strcasecmp($name,'Authorization')===0) $header=$value;
    }
}
api_reply(200, [
    'success'=>true,
    'authorization_header_received'=>is_string($header) && $header !== '',
    'php_version'=>PHP_VERSION,
    'curl_available'=>function_exists('curl_init')
]);
