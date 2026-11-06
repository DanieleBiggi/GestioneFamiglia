<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') gf_oauth_error('invalid_request',405);
gf_oauth_json(200, [
    'resource' => GF_OAUTH_RESOURCE,
    'authorization_servers' => [GF_OAUTH_ISSUER],
    'scopes_supported'=>['family:read','family:write'],
    'bearer_methods_supported'=>['header']
]);
