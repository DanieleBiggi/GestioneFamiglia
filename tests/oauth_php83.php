<?php
require_once __DIR__.'/../api/oauth/common.php';
function check(bool $ok, string $what): void {
    if (!$ok) { fwrite(STDERR, "ERRORE: ".$what.PHP_EOL); exit(1); }
}
check(gf_oauth_scope('family:read'), 'scope lettura');
check(gf_oauth_scope('family:read family:write'), 'scope doppio');
check(!gf_oauth_scope('family:admin'), 'no scope sconosciuto');
check(!gf_oauth_scope(''), 'no scope vuoto');
check(GF_OAUTH_RESOURCE === 'https://www.gestionefamiglia.it/Gestionale25/api/mcp.php','resource');
$a=gf_oauth_random(); $b=gf_oauth_random();
check($a !== $b && strlen($a)>=32 && strlen($b)>=32, 'token casuali');
check(strlen(gf_oauth_hash($a))===64,'hash SHA-256');
check(gf_oauth_bearer()==='', 'header mancante');
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$a;
check(gf_oauth_bearer()===$a, 'header Bearer');
unset($_SERVER['HTTP_AUTHORIZATION']);
echo "Test helper OAuth OK".PHP_EOL;
