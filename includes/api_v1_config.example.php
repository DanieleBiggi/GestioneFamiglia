<?php
// Copiare in api_v1_config.php sul server (NON su GitHub).
// Bloccare sempre l'accesso HTTP al file prima di abilitare le API.
if (!defined('GF_API_INTERNAL')) { http_response_code(404); exit; }

return [
    'enabled' => false,
    'token_sha256' => 'SOSTITUIRE_CON_SHA256_DEL_TOKEN',
    'family_id' => 0,
    'allow_vacations' => false,
    // OAuth si attiva solo dopo il test su Aruba e la creazione delle tabelle SQL.
    'mcp_oauth_enabled' => false,
    // Segreto differente dal token API, 32 byte casuali in esadecimale, mai su GitHub.
    'mcp_internal_key' => '',
    // Facoltativo: limitare il consenso a un singolo id_utente.
    'oauth_allowed_user_id' => 0
];
