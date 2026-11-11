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
    // JSON Schema impone "properties" come oggetto JSON, anche quando e' vuoto.
    // In PHP [] serializzato direttamente diventerebbe un array JSON non valido.
    return ['type' => 'object', 'properties' => (object)$properties, 'required' => $required, 'additionalProperties' => false];
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
        mcp_tool('leggi_menu_settimanale', 'Leggi menù cene settimanale',
            'Legge i cinque pasti da lunedì a venerdì e le note delle ricette; distingue giorni salvati e previsioni master. Nessuna modifica. Senza week_start usa il prossimo lunedì. Confrontare con leggi_lista_spesa e chiedere conferma prima di aggiungere prodotti.',
            mcp_schema(['week_start'=>['type'=>'string','pattern'=>'^[0-9]{4}-[0-9]{2}-[0-9]{2}$','description'=>'Lunedì nel formato YYYY-MM-DD; omettere per il prossimo lunedì']]), true),
        mcp_tool('leggi_lista_spesa', 'Leggi lista spesa',
            'Legge i prodotti nella lista della spesa della famiglia configurata.',
            mcp_schema(), true),
        mcp_tool('anteprima_spesa', 'Anteprima inserimento spesa',
            'Valida senza salvare. Per ingredienti del menu usare payload.items con nome, quantita, associazioni:[{giorno,piatto}], note opzionali. piatto deve corrispondere al piatto del giorno letto dal menu. Non limitarsi a note in linguaggio naturale: la verifica menu necessita di giorno e piatto espliciti.',
            mcp_schema(['payload'=>$payload], ['payload']), true),
        mcp_tool('crea_spesa', 'Inserisci prodotti nella spesa',
            'Aggiunge prodotti REALMENTE solo dopo anteprima e conferma. Per ingredienti del menu usare associazioni:[{giorno,piatto}] oltre a nome e quantita; note puo contenere dettagli separati. Le associazioni vengono convertite nel formato della verifica menu.',
            mcp_schema(['payload'=>$payload,'confirm'=>$confirm,'request_id'=>$requestId],['payload','confirm','request_id']), false),
        mcp_tool('anteprima_associazioni_spesa', 'Anteprima collegamenti ingredienti a piatti',
            'NON modifica nulla. Per correggere prodotti esistenti: payload.items con id del prodotto e associazioni:[{giorno,piatto}]. Leggere prima il menu e la spesa, non indovinare i collegamenti. Mostra note originali e nuove prima di chiedere conferma.',
            mcp_schema(['payload'=>$payload], ['payload']), true),
        mcp_tool('aggiorna_associazioni_spesa', 'Collega ingredienti esistenti ai piatti',
            'Modifica SOLO il campo note dei prodotti indicati, preservando le note originali. Chiamare esclusivamente dopo anteprima e conferma esplicita dell utente sui singoli abbinamenti.',
            mcp_schema(['payload'=>$payload,'confirm'=>$confirm,'request_id'=>$requestId],['payload','confirm','request_id']), false),
        mcp_tool('anteprima_aggiornamento_scenari', 'Anteprima aggiornamento scenari',
            'NON salva. Prima leggere la vacanza. payload: id_viaggio, scenari:[{id_scenario,voci:[{categoria:trasporti|alloggi|pasti|altri_costi,id opzionale,campi:{...}}]}]. Restituisce prima/dopo ed expected_hash. Usare ID delle voci esistenti; per nuove voci fornire nome/descrizione stabile. Non usare per modificare prenotazioni o pagato.',
            mcp_schema(['payload'=>$payload],['payload']), true),
        mcp_tool('aggiorna_scenari', 'Aggiorna scenari esistenti',
            'Salva SOLO le voci esplicitate, mai cancella. Richiede anteprima, consenso esplicito e gli expected_hash restituiti in anteprima per ogni scenario. Supporta uno o piu scenari della stessa vacanza in un unico commit.',
            mcp_schema(['payload'=>$payload,'confirm'=>$confirm,'request_id'=>$requestId],['payload','confirm','request_id']), false),
        mcp_tool('anteprima_aggiornamento_scenario', 'Anteprima modifica scenario',
            'Alias anteprima per un solo scenario: stesso payload di anteprima_aggiornamento_scenari ma scenari contiene esattamente un elemento.',
            mcp_schema(['payload'=>$payload],['payload']), true),
        mcp_tool('aggiorna_scenario', 'Modifica scenario esistente',
            'Alias scrittura per un solo scenario: stesso payload di aggiorna_scenari ma scenari contiene esattamente un elemento; richiede conferma ed expected_hash della precedente anteprima.',
            mcp_schema(['payload'=>$payload,'confirm'=>$confirm,'request_id'=>$requestId],['payload','confirm','request_id']), false),
        mcp_tool('anteprima_modifica_date_vacanza', 'Anteprima modifica date generali di una vacanza',
            'NON salva. Leggere prima la vacanza. payload: id_viaggio, data_inizio e/o data_fine nel formato YYYY-MM-DD. Restituisce date e notti precedenti e nuove più expected_hash. Non modifica le date di alloggi, trasporti o prenotazioni.',
            mcp_schema(['payload'=>$payload],['payload']), true),
        mcp_tool('modifica_date_vacanza', 'Modifica le date generali di una vacanza esistente',
            'Aggiorna solo data_inizio, data_fine e notti della vacanza autorizzata. Richiede anteprima precedente, expected_hash, conferma esplicita e request_id. Non modifica scenari, prenotazioni, alloggi o relative date.',
            mcp_schema(['payload'=>$payload,'confirm'=>$confirm,'request_id'=>$requestId],['payload','confirm','request_id']), false),
        mcp_tool('anteprima_eliminazione_voce_scenario', 'Anteprima eliminazione voce di uno scenario',
            'NON elimina nulla. Prima leggere la vacanza tramite leggi_vacanza e individuare id_scenario, categoria e id_voce. payload: {id_viaggio,id_scenario,categoria:trasporti|alloggi|pasti|altri_costi,id_voce}. Mostra costo sottratto, scenari interessati e se verra rimosso solo il collegamento oppure eliminata la voce.',
            mcp_schema(['payload'=>$payload],['payload']), true),
        mcp_tool('elimina_voce_scenario', 'Elimina una voce da uno scenario dopo conferma',
            'Operazione distruttiva: mostrare l anteprima e chiedere consenso esplicito. payload con id_viaggio, id_scenario, categoria, id_voce e expected_hash ricevuto dalla precedente anteprima; richiede confirm e request_id. Una voce condivisa e scollegata solo dallo scenario scelto; una voce usata esclusivamente da esso viene cancellata. Voci pagate protette.',
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
$write = in_array($name,['crea_spesa','crea_vacanza','aggiorna_associazioni_spesa','aggiorna_scenario','aggiorna_scenari','modifica_date_vacanza','elimina_voce_scenario'],true);
if ($write && ($args['confirm'] ?? null) !== true) mcp_failure($id,-32602,'Conferma esplicita obbligatoria');
if ($write && (!is_string($args['request_id'] ?? null) ||
    !preg_match('/^[A-Za-z0-9_-]{16,64}$/D', $args['request_id']))) mcp_failure($id,-32602,'request_id mancante o non valido');
if (in_array($name,['crea_spesa','crea_vacanza','anteprima_spesa','anteprima_vacanza','anteprima_associazioni_spesa','aggiorna_associazioni_spesa','anteprima_aggiornamento_scenario','anteprima_aggiornamento_scenari','aggiorna_scenario','aggiorna_scenari','anteprima_modifica_date_vacanza','modifica_date_vacanza','anteprima_eliminazione_voce_scenario','elimina_voce_scenario'],true) &&
    (!is_array($args['payload'] ?? null) || array_is_list($args['payload']))) mcp_failure($id,-32602,'payload JSON non valido');

try {
    $file = in_array($name, ['leggi_lista_spesa','anteprima_spesa','crea_spesa'],true) ? 'spesa.php' : 'vacanze.php';
    if (in_array($name, ['anteprima_associazioni_spesa','aggiorna_associazioni_spesa'], true)) {
        $file = 'spesa_associazioni.php';
    }
    if (in_array($name, ['anteprima_aggiornamento_scenario','anteprima_aggiornamento_scenari','aggiorna_scenario','aggiorna_scenari'],true)) {
        $file='vacanze_scenari.php';
        $count=count($args['payload']['scenari'] ?? []);
        if (in_array($name,['anteprima_aggiornamento_scenario','aggiorna_scenario'],true) && $count !== 1) {
            mcp_failure($id,-32602,'Lo strumento singolo richiede esattamente uno scenario');
        }
    }
    if (in_array($name, ['anteprima_modifica_date_vacanza','modifica_date_vacanza'],true)) {
        $file='vacanze_date.php';
    }
    if (in_array($name,['anteprima_eliminazione_voce_scenario','elimina_voce_scenario'],true)) {
        $file='vacanze_voci_elimina.php';
    }
    if ($name === 'leggi_menu_settimanale') {
        $file = 'menu_cene.php';
        if (array_key_exists('week_start', $args)) {
            $week = $args['week_start'];
            if (!is_string($week) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $week)) {
                mcp_failure($id, -32602, 'week_start non valido: usare YYYY-MM-DD');
            }
            $file .= '?week_start=' . rawurlencode($week);
        }
    }
    $verb = in_array($name, ['anteprima_spesa','crea_spesa','anteprima_vacanza','crea_vacanza','anteprima_associazioni_spesa','aggiorna_associazioni_spesa','anteprima_aggiornamento_scenario','anteprima_aggiornamento_scenari','aggiorna_scenario','aggiorna_scenari','anteprima_modifica_date_vacanza','modifica_date_vacanza','anteprima_eliminazione_voce_scenario','elimina_voce_scenario'],true) ? 'POST' : 'GET';
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
