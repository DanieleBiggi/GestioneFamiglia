<?php
declare(strict_types=1);
require_once __DIR__ . '/common.php';
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'");
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') { http_response_code(403); exit('HTTPS richiesto'); }
session_start();
$configFile = __DIR__ . '/../../includes/api_v1_config.php';
if (!is_file($configFile)) { http_response_code(503); exit('API non configurata'); }
if (!defined('GF_API_INTERNAL')) define('GF_API_INTERNAL', true);
$config = require $configFile;
if (($config['enabled'] ?? false) !== true || ($config['mcp_oauth_enabled'] ?? false) !== true) {
    http_response_code(503); exit('Collegamento ChatGPT non attivo');
}
function gf_auth_page(string $title, string $body, int $status=200): never {
    http_response_code($status);
    echo '<!DOCTYPE html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title><body style="font-family:system-ui;max-width:560px;margin:60px auto;padding:20px"><h2>'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>' . $body . '</body></html>';
    exit;
}
$family = filter_var($config['family_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$family) gf_auth_page('Errore','<p>Famiglia non configurata</p>',503);
if (empty($_SESSION['utente_id']) || (int)($_SESSION['id_famiglia_gestione'] ?? 0) !== (int)$family) {
    gf_auth_page('Accedi a Gestione Famiglia',
        '<p>Apri <a href="/Gestionale25/login.php" target="_blank" rel="noopener">Gestione Famiglia</a> in una nuova scheda ed effettua il login completo (incluso passcode o 2FA).</p>'
        . '<p>Poi torna su questa scheda e aggiorna la pagina per autorizzare ChatGPT.</p>',401);
}
if (isset($config['oauth_allowed_user_id']) && (int)$config['oauth_allowed_user_id'] > 0 &&
    (int)$config['oauth_allowed_user_id'] !== (int)$_SESSION['utente_id']) {
    gf_auth_page('Non autorizzato','<p>Questo utente non può collegare ChatGPT.</p>',403);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $client = $_GET['client_id'] ?? '';
    $redirect = $_GET['redirect_uri'] ?? '';
    $resource = $_GET['resource'] ?? '';
    $challenge = $_GET['code_challenge'] ?? '';
    $state = $_GET['state'] ?? '';
    $scope = $_GET['scope'] ?? 'family:read family:write';
    if (!is_string($client)||!is_string($redirect)||!is_string($resource)||!is_string($challenge)||
        !is_string($state)||!is_string($scope)||
        ($_GET['response_type'] ?? '') !== 'code' ||
        ($_GET['code_challenge_method'] ?? '') !== 'S256' ||
        !preg_match('/^[A-Za-z0-9_-]{43}$/D',$challenge) ||
        !hash_equals(GF_OAUTH_RESOURCE,$resource) ||
        !gf_oauth_scope($scope) ||
        !gf_oauth_client($client,$redirect)) {
        gf_auth_page('Richiesta non valida','<p>Il collegamento OAuth è stato rifiutato. Ricontrolla la configurazione in ChatGPT.</p>',400);
    }
    // Ogni pagina di consenso mantiene il proprio identificativo. Un'altra scheda
    // o un secondo tentativo OAuth non può sovrascrivere la prima richiesta.
    $now = time();
    if (!isset($_SESSION['gf_oauth_pending_requests']) || !is_array($_SESSION['gf_oauth_pending_requests'])) {
        $_SESSION['gf_oauth_pending_requests'] = [];
    }
    foreach ($_SESSION['gf_oauth_pending_requests'] as $key => $entry) {
        if (!is_array($entry) || (int)($entry['created_at'] ?? 0) + 600 < $now) {
            unset($_SESSION['gf_oauth_pending_requests'][$key]);
        }
    }
    if (count($_SESSION['gf_oauth_pending_requests']) >= 5) {
        gf_auth_page('Troppe richieste','<p>Chiudi le altre schede di autorizzazione e riprova da ChatGPT.</p>',429);
    }
    $requestKey = bin2hex(random_bytes(24));
    $_SESSION['gf_oauth_pending_requests'][$requestKey] = compact('client','redirect','resource','challenge','state','scope') + ['created_at'=>$now];
    $nonce = htmlspecialchars($requestKey, ENT_QUOTES, 'UTF-8');
    gf_auth_page('Autorizza ChatGPT',
        '<p>ChatGPT richiede accesso ai dati della famiglia configurata in Gestione Famiglia.</p>'
        . '<p>Permessi richiesti: <strong>' . htmlspecialchars($scope,ENT_QUOTES,'UTF-8') . '</strong>.</p>'
        . '<p>Questo può includere lettura delle vacanze e della lista della spesa e, dopo conferma, inserimento di nuovi dati.</p>'
        . '<form method="post"><input type="hidden" name="csrf" value="' . $nonce . '">'
        . '<button name="decision" value="approve" type="submit">Autorizza ChatGPT</button> '
        . '<button name="decision" value="deny" type="submit">Annulla</button></form>');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') gf_auth_page('Metodo non valido','',405);
$requestKey = $_POST['csrf'] ?? '';
$pendingRequests = $_SESSION['gf_oauth_pending_requests'] ?? [];
$pending = is_string($requestKey) && preg_match('/^[a-f0-9]{48}$/D', $requestKey)
    && is_array($pendingRequests) ? ($pendingRequests[$requestKey] ?? null) : null;
if (!is_array($pending) || (int)($pending['created_at'] ?? 0) + 600 < time()) {
    gf_auth_page('Richiesta scaduta','<p>La richiesta è scaduta o è già stata utilizzata. Chiudi questa scheda e riavvia il collegamento da ChatGPT.</p>',403);
}
// Non consumare il consenso finché il codice OAuth non è stato salvato con successo.
// In caso di errore DB deve essere possibile riprovare, senza perdere la richiesta.
if (($_POST['decision'] ?? '') !== 'approve') {
    $url=$pending['redirect'].'?'.http_build_query(['error'=>'access_denied','state'=>$pending['state'],'iss'=>GF_OAUTH_ISSUER],'','&',PHP_QUERY_RFC3986);
    header('Location: '.$url, true,302); exit;
}
try {
    require_once __DIR__ . '/../../includes/db.php';
    $code = gf_oauth_random();
    $hash = gf_oauth_hash($code);
    $uid = (int)$_SESSION['utente_id'];
    $stmt = $conn->prepare("INSERT INTO gf_oauth_codes (code_hash,id_utente,id_famiglia,client_id,redirect_uri,resource,scope,code_challenge,expires_at) VALUES (?,?,?,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE))");
    $stmt->bind_param('siisssss', $hash, $uid, $family, $pending['client'], $pending['redirect'], $pending['resource'], $pending['scope'], $pending['challenge']);
    $stmt->execute();
    $stmt->close();
} catch (Throwable $e) {
    $errorId = bin2hex(random_bytes(4));
    // Log tecnico lato server: mai includere codice OAuth, token, state o redirect URI.
    error_log('GF OAuth consent ' . $errorId . ': ' . get_class($e) . ' code=' . (int)$e->getCode());
    gf_auth_page('Errore di autorizzazione',
        '<p>Non è stato possibile registrare il consenso. Nessuna autorizzazione è stata completata.</p>'
        . '<p>Codice errore: <strong>' . $errorId . '</strong></p>'
        . '<p>Verifica che le tabelle OAuth siano presenti, poi riprova con il pulsante.</p>',500);
}
// Il consenso è consumato solo dopo un INSERT riuscito.
unset($_SESSION['gf_oauth_pending_requests'][$requestKey]);
session_write_close();
$url = $pending['redirect'] . '?' . http_build_query([
    'code'=>$code, 'state'=>$pending['state'], 'iss'=>GF_OAUTH_ISSUER
], '', '&', PHP_QUERY_RFC3986);
header('Location: ' . $url, true, 302);
exit;
