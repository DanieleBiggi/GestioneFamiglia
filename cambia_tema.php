<?php
session_start();
if (isset($_POST['id_tema'])) {
    $_SESSION['theme_id'] = (int)$_POST['id_tema'];
    if (isset($_SESSION['utente_id'])) {
        require_once 'includes/db.php';
        $stmt = $conn->prepare('UPDATE utenti SET id_tema = ? WHERE id = ?');
        $stmt->bind_param('ii', $_SESSION['theme_id'], $_SESSION['utente_id']);
        $stmt->execute();
        $stmt->close();
    }
}
$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $referer);
exit;
?>
