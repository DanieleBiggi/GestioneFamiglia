<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

ob_start();

$syncFatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

register_shutdown_function(function () use ($syncFatalTypes) {
    $error = error_get_last();
    if (!$error || !in_array($error['type'], $syncFatalTypes, true)) {
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }

    $file = basename($error['file']);
    $line = (int)$error['line'];
    $detail = trim((string)$error['message']);
    $message = 'Errore PHP fatale durante la sincronizzazione';
    if ($detail !== '') {
        $message .= ': ' . $detail;
    }
    $message .= ' [File: ' . $file . ', riga: ' . $line . ']';

    echo json_encode([
        'success' => false,
        'message' => $message,
        'details' => $detail,
        'file' => $file,
        'line' => $line,
        'error_type' => $error['type']
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

try {
    require __DIR__ . '/turni_sync_google_core.php';
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }

    $file = basename($e->getFile());
    $line = $e->getLine();
    $detail = trim($e->getMessage());
    $message = 'Eccezione PHP durante la sincronizzazione';
    if ($detail !== '') {
        $message .= ': ' . $detail;
    }
    $message .= ' [' . get_class($e) . ', file: ' . $file . ', riga: ' . $line . ']';

    echo json_encode([
        'success' => false,
        'message' => $message,
        'details' => $detail,
        'exception' => get_class($e),
        'file' => $file,
        'line' => $line
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
