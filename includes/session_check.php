<?php
session_start();
if (!isset($_SESSION["utente_id"])) {
    header("Location: login.php");
    exit;
}
?>