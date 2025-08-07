<?php
include 'includes/session_check.php';
include 'includes/db.php';

$idUtente = $_SESSION['utente_id'] ?? 0;
$idFamiglia = isset($_POST['id_famiglia_gestione']) ? (int)$_POST['id_famiglia_gestione'] : 0;

if ($idUtente && $idFamiglia) {
    $stmt = $conn->prepare('SELECT 1 FROM utenti2famiglie WHERE id_utente = ? AND id_famiglia = ?');
    $stmt->bind_param('ii', $idUtente, $idFamiglia);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $_SESSION['id_famiglia_gestione'] = $idFamiglia;
        $upd = $conn->prepare('UPDATE utenti SET id_famiglia_gestione = ? WHERE id = ?');
        $upd->bind_param('ii', $idFamiglia, $idUtente);
        $upd->execute();
    }
}

$redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $redirect);
exit;
?>
