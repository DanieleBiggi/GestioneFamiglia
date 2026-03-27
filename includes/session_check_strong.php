<?php
include __DIR__ . '/session_check.php';

if (($_SESSION['auth_level'] ?? '') !== 'strong') {
    $target = $_SERVER['REQUEST_URI'] ?? '/Gestionale25/index.php';
    $redirect = '/Gestionale25/login_passcode.php?stepup=1&redirect=' . rawurlencode($target);
    header('Location: ' . $redirect);
    exit;
}
?>
