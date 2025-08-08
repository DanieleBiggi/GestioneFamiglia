<?php
include 'includes/session_check.php';
require_once 'includes/db.php';

if (isset($_SESSION['impersonator_id'])) {
    $originalId = (int)$_SESSION['impersonator_id'];
    unset($_SESSION['impersonator_id']);

    $stmt = $conn->prepare('SELECT id, nome, id_famiglia_gestione FROM utenti WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $originalId);
    $stmt->execute();
    if ($user = $stmt->get_result()->fetch_assoc()) {
        $_SESSION['utente_id'] = $user['id'];
        $_SESSION['utente_nome'] = $user['nome'];
        $_SESSION['id_famiglia_gestione'] = $user['id_famiglia_gestione'] ?? 0;
    }
    $stmt->close();
}

header('Location: index.php');
exit;
?>
