<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') gf_oauth_error('invalid_request',405);
gf_oauth_json(200, [
    'issuer'=>GF_OAUTH_ISSUER,
    'authorization_endpoint'=>'https://www.gestionefamiglia.it/Gestionale25/api/oauth/authorize.php',
    'token_endpoint'=>'https://www.gestionefamiglia.it/Gestionale25/api/oauth/token.php',
    'authorization_response_iss_parameter_supported'=>true,
    'response_types_supported'=>['code'],
    'grant_types_supported'=>['authorization_code','refresh_token'],
    'code_challenge_methods_supported'=>['S256'],
    'token_endpoint_auth_methods_supported'=>['none'],
    'client_id_metadata_document_supported'=>true,
    'scopes_supported'=>['family:read','family:write']
]);
