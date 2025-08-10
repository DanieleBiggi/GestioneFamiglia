<?php
session_start();
if (isset($_POST['id_tema'])) {
    $_SESSION['theme_id'] = (int)$_POST['id_tema'];
}
$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $referer);
exit;
?>
