<?php
// Try to read configuration from environment variables
$config = [
    'host' => getenv('DB_HOST'),
    'port' => getenv('DB_PORT'),
    'user' => getenv('DB_USER'),
    'pass' => getenv('DB_PASS'),
    'name' => getenv('DB_NAME')
];

// Optionally load configuration from an untracked file
if (file_exists(__DIR__ . '/db_config.php')) {
    $fileConfig = include __DIR__ . '/db_config.php';
    if (is_array($fileConfig)) {
        $config = array_merge($config, $fileConfig);
    }
}
if (file_exists(__DIR__ . '/mail_config.php')) {
    $fileConfig = include __DIR__ . '/mail_config.php';
    if (is_array($fileConfig)) {
        $config = array_merge($config, $fileConfig);
    }
}
if (file_exists(__DIR__ . '/api_config.php')) {
    $fileConfig = include __DIR__ . '/api_config.php';
    if (is_array($fileConfig)) {
        $config = array_merge($config, $fileConfig);
    }
}


function debugBindParams($sql, $types, $params) {
    $placeholders = substr_count($sql, '?');
    $types_len = strlen($types);
    $params_len = count($params);

    if ($placeholders !== $types_len) {
        echo "ERRORE: numero di '?' ($placeholders) diverso da lunghezza stringa types ($types_len)\n";
    }
    if ($types_len !== $params_len) {
        echo "ERRORE: numero di tipi ($types_len) diverso da numero di variabili passate ($params_len)\n";
    }
    if ($placeholders === $types_len && $types_len === $params_len) {
        //echo "✅ Tutto combacia!\n";
    }
}

$host = $config['host'] ?? 'localhost';
$port = $config['port'] ?? '3306';
$user = $config['user'] ?? '';
$password = $config['pass'] ?? '';
$database = $config['name'] ?? '';

$conn = new mysqli($host, $user, $password, $database, $port);

if ($conn->connect_error) {
    die('Connessione fallita: ' . $conn->connect_error);
}
?>
