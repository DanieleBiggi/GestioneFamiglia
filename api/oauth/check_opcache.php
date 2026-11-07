<?php
declare(strict_types=1);
// TEMPORANEO: cancellare dal branch e da Aruba dopo il test.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function respond(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (($_SERVER['HTTPS'] ?? '') === 'off' || empty($_SERVER['HTTPS'])) {
    respond(403, ['error'=>'HTTPS richiesto']);
}
if (!defined('GF_API_INTERNAL')) define('GF_API_INTERNAL', true);
$cfgFile=__DIR__.'/../../includes/api_v1_config.php';
if (!is_file($cfgFile)) respond(503,['error'=>'Config assente']);
$cfg=require $cfgFile;
$key=$_SERVER['HTTP_X_GF_API_KEY'] ?? '';
$hash=$cfg['token_sha256'] ?? '';
if (($cfg['enabled'] ?? false) !== true ||
    !is_string($key) || !preg_match('/^[a-zA-Z0-9_-]{32,256}$/D',$key) ||
    !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/Di',$hash) ||
    !hash_equals(strtolower($hash),hash('sha256',$key))) {
    respond(401,['error'=>'Non autorizzato']);
}
$familyValid=(int)($cfg['family_id'] ?? 0)>0;
unset($key,$hash,$cfg);
$target=realpath(__DIR__.'/token.php');
if ($target === false) respond(500,['error'=>'File token.php assente']);
$opcacheEnabled=function_exists('opcache_get_status') && (bool)(opcache_get_status(false)['opcache_enabled'] ?? false);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    respond(200,[
        'success'=>true,
        'opcache_enabled'=>$opcacheEnabled,
        'invalidate_available'=>function_exists('opcache_invalidate'),
        'token_file_present'=>is_file($target),
        'token_has_build_2'=>str_contains((string)file_get_contents($target),"'token_build_2'"),
        'family_id_valid'=>$familyValid
    ]);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') respond(405,['error'=>'Metodo non consentito']);
if (!function_exists('opcache_invalidate')) respond(501,['error'=>'OPcache invalidate non disponibile']);
$ok=opcache_invalidate($target,true);
respond($ok?200:500,['success'=>$ok,'opcache_invalidate_result'=>$ok]);
