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
