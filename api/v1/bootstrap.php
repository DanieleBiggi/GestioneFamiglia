<?php
declare(strict_types=1);

// API indipendenti dalla sessione web. Non impostare mai CORS "*".
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function api_reply(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function api_error(int $status, string $message): void {
    api_reply($status, ['success' => false, 'error' => $message]);
}
function api_method(string ...$allowed): void {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        api_error(405, 'Metodo non supportato');
    }
}
function api_body(): array {
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 131072) api_error(413, 'Richiesta troppo grande');
    $raw = file_get_contents('php://input', false, null, 0, 131073);
    if ($raw === false || strlen($raw) > 131072) api_error(413, 'Richiesta troppo grande');
    $data = json_decode($raw, true);
    if (!is_array($data) || array_values($data) === $data) api_error(400, 'Inviare un oggetto JSON');
    return $data;
}
function api_str($value, int $max = 255, bool $required = false): ?string {
    if ($value === null) {
        if ($required) api_error(422, 'Campo obbligatorio mancante');
        return null;
    }
    if (!is_string($value)) api_error(422, 'Testo non valido');
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $max) api_error(422, 'Testo vuoto o troppo lungo');
    return $value === '' ? null : $value;
}
function api_number($value): ?float {
    if ($value === null) return null;
    if (!is_int($value) && !is_float($value)) api_error(422, 'Importo non valido');
    if (!is_finite((float)$value) || $value < 0 || $value > 10000000) api_error(422, 'Importo fuori intervallo');
    return (float)$value;
}
function api_date($value): ?string {
    if ($value === null) return null;
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) api_error(422, 'Data non valida');
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$d || $d->format('Y-m-d') !== $value) api_error(422, 'Data non valida');
    return $value;
}
function api_list($value, int $max = 50): array {
    if (!is_array($value) || array_values($value) !== $value || count($value) > $max) api_error(422, 'Elenco non valido');
    return $value;
}
function api_assoc($value): array {
    if (!is_array($value) || array_values($value) === $value) api_error(422, 'Elemento non valido');
    return $value;
}
function api_json_error(Throwable $e): void {
    error_log('GF API error: ' . $e->getMessage());
    api_error(500, 'Errore interno');
}

// Le credenziali sono solo sul server Aruba, mai nel repository.
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    api_error(403, 'HTTPS richiesto');
}
$configFile = __DIR__ . '/../../includes/api_v1_config.php';
if (!is_file($configFile)) api_error(503, 'API non configurata');
define('GF_API_INTERNAL', true);
$apiConfig = require $configFile;
if (!is_array($apiConfig) || ($apiConfig['enabled'] ?? false) !== true) {
    api_error(503, 'API disabilitata');
}
$familyId = filter_var($apiConfig['family_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!$familyId) api_error(503, 'Famiglia API non configurata');
$allowVacations = ($apiConfig['allow_vacations'] ?? false) === true;

$expected = $apiConfig['token_sha256'] ?? '';
$provided = $_SERVER['HTTP_X_GF_API_KEY'] ?? '';
$apiKeyValid = is_string($provided) && preg_match('/^[A-Za-z0-9_-]{32,256}$/D', $provided)
    && is_string($expected) && preg_match('/^[a-f0-9]{64}$/Di', $expected)
    && hash_equals(strtolower($expected), hash('sha256',$provided));
unset($expected,$provided);

$internalValid = false;
$internalKey = $apiConfig['mcp_internal_key'] ?? '';
$timeHeader = $_SERVER['HTTP_X_GF_INTERNAL_TIME'] ?? '';
$signature = $_SERVER['HTTP_X_GF_INTERNAL_SIGNATURE'] ?? '';
if (is_string($internalKey) && preg_match('/^[a-f0-9]{64}$/Di',$internalKey)
    && is_string($timeHeader) && preg_match('/^[0-9]{10}$/D',$timeHeader)
    && abs(time()-(int)$timeHeader) <= 30 && is_string($signature)
    && preg_match('/^[a-f0-9]{64}$/Di',$signature)) {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $raw = file_get_contents('php://input');
    $base = $timeHeader . "\n" . ($_SERVER['REQUEST_METHOD'] ?? '') . "\n" . $uriPath . "\n" . hash('sha256', $raw === false ? '' : $raw);
    $expectedSignature=hash_hmac('sha256',$base,hex2bin($internalKey));
    $internalValid=hash_equals($expectedSignature,strtolower($signature));
    unset($raw,$base,$expectedSignature);
}
unset($internalKey,$timeHeader,$signature);
require_once __DIR__ . '/../../includes/db.php';
$conn->set_charset('utf8mb4');

$oauthAccess = null;
if (!$apiKeyValid && !$internalValid && defined('GF_MCP_CONTEXT') && GF_MCP_CONTEXT
    && ($apiConfig['mcp_oauth_enabled'] ?? false) === true) {
    require_once __DIR__ . '/../oauth/common.php';
    $oauthAccess = gf_oauth_access($conn,gf_oauth_bearer(),(int)$familyId);
}
if (!$apiKeyValid && !$internalValid && !$oauthAccess) {
    if (defined('GF_MCP_CONTEXT') && GF_MCP_CONTEXT && ($apiConfig['mcp_oauth_enabled'] ?? false) === true) {
        gf_oauth_challenge();
    }
    api_error(401, 'Token non valido');
}
function api_request_id(): string {
    $requestId = $_SERVER['HTTP_X_GF_REQUEST_ID'] ?? '';
    if (!is_string($requestId) || !preg_match('/^[A-Za-z0-9_-]{16,64}$/D', $requestId)) {
        api_error(422, 'Inviare X-GF-Request-Id unico per ogni salvataggio');
    }
    return $requestId;
}
function api_claim_request(mysqli $conn, int $familyId, string $resource, string $requestId): void {
    try {
        $q = $conn->prepare('INSERT INTO api_v1_richieste (id_famiglia, risorsa, richiesta_id) VALUES (?,?,?)');
        $q->bind_param('iss', $familyId, $resource, $requestId);
        $q->execute();
        $q->close();
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() === 1062) {
            $conn->rollback();
            api_error(409, 'Richiesta già inviata, non ripetere il salvataggio');
        }
        throw $e;
    }
}
