<?php
declare(strict_types=1);
// Shared OAuth helpers. This is a small, single-family OAuth service: keep private.
const GF_OAUTH_ISSUER = 'https://www.gestionefamiglia.it';
const GF_OAUTH_RESOURCE = 'https://www.gestionefamiglia.it/Gestionale25/api/mcp.php';
const GF_OAUTH_RESOURCE_METADATA = 'https://www.gestionefamiglia.it/Gestionale25/api/oauth/resource.php';

function gf_oauth_json(int $status, array $data): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function gf_oauth_error(string $code, int $status = 400): never {
    gf_oauth_json($status, ['error'=>$code]);
}
function gf_oauth_hash(string $secret): string { return hash('sha256', $secret); }
function gf_oauth_random(): string { return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='); }
function gf_oauth_client(string $id, string $redirect): bool {
    // Fixed trusted origin: don't let untrusted client_id URLs cause SSRF.
    if ($id !== 'https://chatgpt.com/oauth/client.json' &&
        !preg_match('~^https://chatgpt\.com/oauth/[a-zA-Z0-9_-]{6,96}/client\.json$~D', $id)) return false;
    if (!preg_match('~^https://chatgpt\.com/(connector_platform_oauth_redirect|connector/oauth/[a-zA-Z0-9_-]{6,96})$~D', $redirect)) return false;
    if (!function_exists('curl_init')) return false;
    $curl = curl_init($id);
    $body = '';
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER=>false, CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>4, CURLOPT_TIMEOUT=>8, CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_HTTPHEADER=>['Accept: application/json'],
        CURLOPT_WRITEFUNCTION=>static function($ch, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 32768) return 0;
            $body .= $chunk;
            return strlen($chunk);
        }
    ]);
    $ok = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if (!$ok || $status !== 200) return false;
    $meta = json_decode($body,true);
    return is_array($meta)
        && ($meta['client_id'] ?? null) === $id
        && in_array($redirect, $meta['redirect_uris'] ?? [], true)
        && in_array('authorization_code', $meta['grant_types'] ?? ['authorization_code'], true);
}
function gf_oauth_scope(string $scope): bool {
    $parts = preg_split('/\s+/', trim($scope));
    return is_array($parts) && count($parts) > 0 && !array_diff($parts,['family:read','family:write']);
}
function gf_oauth_access(mysqli $conn, string $token, int $familyId): ?array {
    if (!preg_match('/^[a-zA-Z0-9_-]{32,128}$/D', $token)) return null;
    $hash = gf_oauth_hash($token);
    $stmt = $conn->prepare("SELECT id_utente, id_famiglia, resource, scope, expires_at FROM gf_oauth_access_tokens WHERE token_hash=? AND revoked_at IS NULL AND expires_at>UTC_TIMESTAMP() LIMIT 1");
    $stmt->bind_param('s',$hash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || !hash_equals(GF_OAUTH_RESOURCE,(string)$row['resource']) || (int)$row['id_famiglia'] !== $familyId) return null;
    return $row;
}
function gf_oauth_issue(mysqli $conn, int $userId, int $familyId, string $clientId, string $resource, string $scope): array {
    $access = gf_oauth_random();
    $refresh = gf_oauth_random();
    $accessHash = gf_oauth_hash($access);
    $refreshHash = gf_oauth_hash($refresh);
    $q = $conn->prepare("INSERT INTO gf_oauth_access_tokens (token_hash,id_utente,id_famiglia,client_id,resource,scope,expires_at) VALUES (?,?,?,?,?,?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 3600 SECOND))");
    $q->bind_param('siisss',$accessHash,$userId,$familyId,$clientId,$resource,$scope);
    $q->execute(); $q->close();
    $q = $conn->prepare("INSERT INTO gf_oauth_refresh_tokens (token_hash,id_utente,id_famiglia,client_id,resource,scope,expires_at) VALUES (?,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY))");
    $q->bind_param('siisss',$refreshHash,$userId,$familyId,$clientId,$resource,$scope);
    $q->execute(); $q->close();
    return ['access_token'=>$access,'token_type'=>'Bearer','expires_in'=>3600,'refresh_token'=>$refresh,'scope'=>$scope];
}
function gf_oauth_bearer(): string {
    $a = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($a === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $key=>$value) {
            if (strcasecmp($key,'Authorization') === 0) $a=$value;
        }
    }
    return is_string($a) && preg_match('/^Bearer ([A-Za-z0-9_-]{32,128})$/D',$a,$matches) ? $matches[1] : '';
}
function gf_oauth_challenge(): void {
    header('WWW-Authenticate: Bearer resource_metadata="' . GF_OAUTH_RESOURCE_METADATA . '", scope="family:read family:write"');
}
