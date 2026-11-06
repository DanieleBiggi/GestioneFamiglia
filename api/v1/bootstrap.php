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

// Token statico configurato su hosting. Memorizzare SOLO SHA-256 e ruotarlo se compromesso.
$expected = getenv('GF_API_TOKEN_SHA256') ?: '';
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($authorization === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $key => $value) {
        if (strcasecmp($key, 'Authorization') === 0) $authorization = $value;
    }
}
if (!preg_match('/^Bearer ([A-Za-z0-9_\-]{32,256})$/', $authorization, $matches)
    || !preg_match('/^[a-f0-9]{64}$/i', $expected)
    || !hash_equals(strtolower($expected), hash('sha256', $matches[1]))) {
    header('WWW-Authenticate: Bearer');
    api_error(401, 'Token non valido');
}
unset($matches, $authorization, $expected);

require_once __DIR__ . '/../../includes/db.php';
$conn->set_charset('utf8mb4');
$familyId = filter_var(getenv('GF_API_FAMILY_ID'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$familyId) api_error(503, 'Famiglia API non configurata');
$allowVacations = getenv('GF_API_ALLOW_VACATIONS') === '1';
