<?php
declare(strict_types=1);

/**
 * MCP 2025-06-18 stateless JSON-RPC adapter.
 * Uses the same API key authentication as /api/v1/*.php.
 * Only expose through HTTPS; the REST API still enforces family access.
 */
define('GF_MCP_CONTEXT', true);
require_once __DIR__ . '/v1/bootstrap.php';

header('MCP-Protocol-Version: 2025-06-18');
api_method('POST');

function mcp_json($id, array $body): void {
    api_reply(200, ['jsonrpc' => '2.0', 'id' => $id] + $body);
}
function mcp_failure($id, int $code, string $message): void {
    mcp_json($id, ['error' => ['code' => $code, 'message' => $message]]);
}
function mcp_schema(array $properties = [], array $required = []): array {
    return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];
}
function mcp_tool(string $name, string $title, string $description, array $schema, bool $readOnly): array {
    return [
        'name' => $name,
        'title' => $title,
        'description' => $description,
        'inputSchema' => $schema,
        'securitySchemes' => [['type'=>'oauth2','scopes'=>[$readOnly ? 'family:read' : 'family:write']]],
        'annotations' => [
            'readOnlyHint' => $readOnly,
            'destructiveHint' => false,
            'idempotentHint' => $readOnly,
            'openWorldHint' => false
        ]
    ];
}
function mcp_tools(): array {
    $payload = ['type'=>'object', 'description'=>'Dati nel formato delle API REST Gestione Famiglia, senza il campo dry_run.'];
    $confirm = ['type'=>'boolean', 'description'=>'Impostare true solo dopo esplicita conferma dell’utente in chat.'];
    $requestId = ['type'=>'string', 'pattern'=>'^[A-Za-z0-9_-]{16,64}$',
        'description'=>'Identificativo univoco stabile per questa operazione. Riutilizzare lo stesso ID se la richiesta viene ripetuta.'];
    return [
        mcp_tool('leggi_lista_spesa', 'Leggi lista spesa',
            'Legge i prodotti nella lista della spesa della famiglia configurata.',
            mcp_schema(), true),
        mcp_tool('anteprima_spesa', 'Anteprima inserimento spesa',
            'Valida senza salvare. Passa payload.items con elementi nome, quantita e note. Mostra il risultato e chiedi conferma esplicita prima di creare.',
            mcp_schema(['payload'=>$payload], ['payload']), true),
        mcp_tool('crea_spesa', 'Inserisci prodotti nella spesa',
            'Aggiunge prodotti REALMENTE. Chiamare solo dopo anteprima e consenso esplicito. Non usare su richieste ipotetiche.',
            mcp_schema(['payload'=>$payload,'confirm'=>$confirm,'request_id'=>$requestId],['payload','confirm','request_id']), false),
        mcp_tool('elenca_vacanze', 'Elenca vacanze API',
            'Elenca vacanze associate alla famiglia, importate tramite API.',
            mcp_schema(), true),
        mcp_tool('leggi_vacanza', 'Dettagli vacanza API',
            'Legge vacanza e alternative tramite id_viaggio autorizzato.',
            mcp_schema(['id_viaggio'=>['type'=>'integer','minimum'=>1]],['id_viaggio']), true),
        mcp_tool('anteprima_vacanza', 'Anteprima nuova vacanza',
            'Valida senza scrivere. payload: titolo, data_inizio/data_fine AAAA-MM-GG, persone, scenari array; scenario.nome, trasporti (tipo, descrizione, origine, destinazione, costo_volo_eur, costo_traghetto_eur, altri_costi_eur, note), alloggi (nome, indirizzo, checkin, checkout, costo_notte_eur, note), altri_costi (descrizione, importo_eur, note). Non inventare dati assenti.',
            mcp_schema(['payload'=>$payload],['payload']), true),
        mcp_tool('crea_vacanza', 'Crea vacanza',
            'Crea REALMENTE una vacanza con scenari. Chiamare solo dopo anteprima, riepilogo costi e approvazione esplicita dell’utente. Non inviare informazioni non confermate come prenotate.',
            mcp_schema(['payload'=>$payload,'confirm'=>$confirm,'request_id'=>$requestId],['payload','confirm','request_id']), false)
    ];
}
function mcp_request(string $path, string $verb, string $token, ?array $payload = null, ?string $requestId = null): array {
    if (!function_exists('curl_init')) throw new RuntimeException('Estensione PHP cURL non disponibile');
    $url = 'https://www.gestionefamiglia.it/Gestionale25/api/v1/' . $path;
    $headers = ['Accept: application/json'];
    $jsonBody = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    if ($payload !== null) $headers[] = 'Content-Type: application/json; charset=utf-8';
    if ($requestId !== null) $headers[] = 'X-GF-Request-Id: ' . $requestId;
    if ($token !== '') {
        $headers[]='X-GF-Api-Key: ' . $token;
    } else {
        $key = $GLOBALS['apiConfig']['mcp_internal_key'] ?? '';
        if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/Di',$key)) throw new RuntimeException('Chiave interna MCP assente');
        $timestamp=(string)time();
        $canonicalPath='/Gestionale25/api/v1/' . parse_url($path, PHP_URL_PATH);
        $signed=$timestamp . "\n" . $verb . "\n" . $canonicalPath . "\n" . hash('sha256',$jsonBody);
        $headers[]='X-GF-Internal-Time: ' . $timestamp;
        $headers[]='X-GF-Internal-Signature: ' . hash_hmac('sha256',$signed,hex2bin($key));
    }
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $verb,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_HEADER => false
    ]);
    if ($payload !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonBody);
    $result = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($result === false || $status === 0) throw new RuntimeException('REST API non raggiungibile: ' . $error);
    $json = json_decode($result, true);
    if (!is_array($json)) throw new RuntimeException('Risposta REST non JSON');
    if ($status >= 400) return ['success'=>false,'status'=>$status,'error'=>$json['error'] ?? 'Errore API'];
    return $json;
}

$raw = file_get_contents('php://input');
if (!is_string($raw) || strlen($raw) > 131072) api_error(413, 'Messaggio troppo grande');
$input = json_decode($raw, true);
if (!is_array($input) || ($input['jsonrpc'] ?? null) !== '2.0' || !is_string($input['method'] ?? null)) {
    mcp_failure(null, -32600, 'Richiesta JSON-RPC non valida');
}
$id = $input['id'] ?? null;
$method = $input['method'];
if ($id === null && str_starts_with($method, 'notifications/')) {
    http_response_code(202);
    exit;
}
if ($method === 'initialize') {
    mcp_json($id, [
        'result'=>[
            'protocolVersion'=>'2025-06-18',
            'capabilities'=>['tools'=>['listChanged'=>false]],
            'serverInfo'=>['name'=>'gestione-famiglia','version'=>'0.1.0'],
            'instructions'=>'Leggi e prepara liberamente. Prima di ogni modifica reale, mostra un riepilogo e chiedi il consenso esplicito dell’utente. Le anteprime non salvano dati.'
        ]
    ]);
}
if ($method === 'ping') mcp_json($id, ['result'=>(object)[]]);
if ($method === 'tools/list') mcp_json($id, ['result'=>['tools'=>mcp_tools()]]);
if ($method !== 'tools/call') mcp_failure($id, -32601, 'Metodo non supportato');

$params = $input['params'] ?? [];
if (!is_array($params) || !is_string($params['name'] ?? null)) mcp_failure($id,-32602,'Parametri non validi');
$name = $params['name'];
$args = $params['arguments'] ?? [];
if (!is_array($args)) mcp_failure($id,-32602,'Argomenti non validi');
$allowed = array_column(mcp_tools(),'name');
if (!in_array($name, $allowed, true)) mcp_failure($id,-32602,'Strumento non disponibile');
$write = in_array($name,['crea_spesa','crea_vacanza'],true);
if ($write && ($args['confirm'] ?? null) !== true) mcp_failure($id,-32602,'Conferma esplicita obbligatoria');
if ($write && (!is_string($args['request_id'] ?? null) ||
    !preg_match('/^[A-Za-z0-9_-]{16,64}$/D', $args['request_id']))) mcp_failure($id,-32602,'request_id mancante o non valido');
if (in_array($name,['crea_spesa','crea_vacanza','anteprima_spesa','anteprima_vacanza'],true) &&
    (!is_array($args['payload'] ?? null) || array_is_list($args['payload']))) mcp_failure($id,-32602,'payload JSON non valido');

try {
    $file = in_array($name, ['leggi_lista_spesa','anteprima_spesa','crea_spesa'],true) ? 'spesa.php' : 'vacanze.php';
    $verb = in_array($name, ['anteprima_spesa','crea_spesa','anteprima_vacanza','crea_vacanza'],true) ? 'POST' : 'GET';
    if ($name === 'leggi_vacanza') {
        $vacId = filter_var($args['id_viaggio'] ?? null, FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$vacId) mcp_failure($id,-32602,'ID vacanza non valido');
        $file .= '?id=' . $vacId;
    }
    $payload = $verb === 'POST' ? $args['payload'] : null;
    if ($payload !== null) {
        unset($payload['dry_run']);
        $payload['dry_run'] = !$write;
    }
    if ($oauthAccess && !in_array($write ? 'family:write' : 'family:read', explode(' ', (string)$oauthAccess['scope']), true)) {
        mcp_failure($id, -32602, 'Permesso OAuth insufficiente');
    }
    $data = mcp_request($file, $verb, $_SERVER['HTTP_X_GF_API_KEY'] ?? '', $payload,
        $write ? $args['request_id'] : null);
    mcp_json($id,['result'=>['content'=>[['type'=>'text','text'=>json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
        'isError'=>($data['success'] ?? false) !== true]]);
} catch (Throwable $e) {
    error_log('MCP API error: ' . $e->getMessage());
    mcp_json($id,['result'=>['content'=>[['type'=>'text','text'=>'Operazione non riuscita: verifica API e log server.']], 'isError'=>true]]);
}
