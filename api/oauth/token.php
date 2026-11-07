<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') gf_oauth_error('invalid_request',405);
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') gf_oauth_error('invalid_request',403);
header('Cache-Control: no-store');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: none');
if (!defined('GF_API_INTERNAL')) define('GF_API_INTERNAL',true);
$configFile=__DIR__.'/../../includes/api_v1_config.php';
$oauthConfig=is_file($configFile) ? require $configFile : [];
if (($oauthConfig['enabled'] ?? false) !== true || ($oauthConfig['mcp_oauth_enabled'] ?? false) !== true) gf_oauth_error('temporarily_unavailable',503);
// Calcolare l'ID prima di includere db.php (che definisce variabili globali).
$family=(int)($oauthConfig['family_id'] ?? 0);
require_once __DIR__.'/../../includes/db.php';
$grant=$_POST['grant_type'] ?? '';
$client=$_POST['client_id'] ?? '';
$resource=$_POST['resource'] ?? '';

if (!is_string($grant)) {
    gf_oauth_error('invalid_request');
}
if (!is_string($client)) {
    gf_oauth_error('invalid_request');
}
if (!is_string($resource)) {
    gf_oauth_error('invalid_request');
}
if ($family < 1) {
    gf_oauth_error('invalid_request');
}
if (!hash_equals(GF_OAUTH_RESOURCE, $resource)) {
    gf_oauth_error('invalid_target');
}
if ($client === '') {
    gf_oauth_error('invalid_request');
}
try {
    $conn->begin_transaction();
    if ($grant === 'authorization_code') {
        $code=$_POST['code'] ?? '';
        $verifier=$_POST['code_verifier'] ?? '';
        $redirect=$_POST['redirect_uri'] ?? '';
        if (!is_string($code)||!is_string($verifier)||!is_string($redirect) ||
            !preg_match('/^[A-Za-z0-9_-]{43,128}$/D',$verifier)) throw new DomainException('invalid_grant');
        $hash=gf_oauth_hash($code);
        $q=$conn->prepare("SELECT * FROM gf_oauth_codes WHERE code_hash=? AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE");
        $q->bind_param('s',$hash);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();
        if (!$row || !hash_equals((string)$row['client_id'],$client) ||
            !hash_equals((string)$row['redirect_uri'],$redirect) ||
            !hash_equals((string)$row['resource'],$resource) ||
            (int)$row['id_famiglia']!==$family) throw new DomainException('invalid_grant');
        $challenge=rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'=');
        if (!hash_equals((string)$row['code_challenge'],$challenge)) throw new DomainException('invalid_grant');
        $q=$conn->prepare("UPDATE gf_oauth_codes SET used_at=UTC_TIMESTAMP() WHERE code_hash=? AND used_at IS NULL");
        $q->bind_param('s',$hash);$q->execute();$q->close();
        $tokens=gf_oauth_issue($conn,(int)$row['id_utente'],$family,$client,$resource,(string)$row['scope']);
    } elseif ($grant === 'refresh_token') {
        $refresh=$_POST['refresh_token'] ?? '';
        if (!is_string($refresh)||!preg_match('/^[A-Za-z0-9_-]{32,128}$/D',$refresh)) throw new DomainException('invalid_grant');
        $hash=gf_oauth_hash($refresh);
        $q=$conn->prepare("SELECT * FROM gf_oauth_refresh_tokens WHERE token_hash=? AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE");
        $q->bind_param('s',$hash);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();
        if (!$row || !hash_equals((string)$row['client_id'],$client) ||
            !hash_equals((string)$row['resource'],$resource) ||
            (int)$row['id_famiglia']!==$family) gf_oauth_error('invalid_grant');
        $q=$conn->prepare("UPDATE gf_oauth_refresh_tokens SET revoked_at=UTC_TIMESTAMP() WHERE token_hash=? AND revoked_at IS NULL");
        $q->bind_param('s',$hash);$q->execute();$q->close();
        $tokens=gf_oauth_issue($conn,(int)$row['id_utente'],$family,$client,$resource,(string)$row['scope']);
    } else throw new DomainException('unsupported_grant_type');
    $conn->commit();
    gf_oauth_json(200,$tokens);
} catch (DomainException $e) {
    $conn->rollback();
    // Nessun dato sensibile; una richiesta rifiutata non deve lasciare transazioni aperte.
    gf_oauth_error($e->getMessage());
} catch (Throwable $e) {
    $conn->rollback();
    error_log('GF OAuth token endpoint: server_error (' . get_class($e) . ')');
    gf_oauth_error('server_error',500);
}

