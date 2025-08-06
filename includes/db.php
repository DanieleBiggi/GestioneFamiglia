<?php
$host = "89.46.111.63";
$port = "3306";
$user = "Sql1203781";
$password = "127450176p";
$database = "Sql1203781_2";

$conn = new mysqli($host, $user, $password, $database, $port);

if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}
?>