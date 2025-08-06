<?php
require_once '../includes/db.php';

$id = $_POST['id'];
$campo = $_POST['campo'];
$valore = $_POST['valore'];

$allowed = ['note', 'descrizione_extra', 'id_gruppo_transazione'];
if (!in_array($campo, $allowed)) exit;

$stmt = $conn->prepare("UPDATE movimenti_revolut SET $campo = ? WHERE id_movimento_revolut = ?");
$stmt->bind_param("si", $valore, $id);
$stmt->execute();