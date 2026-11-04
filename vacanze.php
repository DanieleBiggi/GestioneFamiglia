<?php
// Unico elenco vacanze: manteniamo questo URL come alias per i link esistenti.
include 'includes/session_check.php';
$allowed=['q','stato','notti','prezzo_min','prezzo_max','budget_max','ordine'];
$query=[];
foreach($allowed as $key) {
 if(isset($_GET[$key]) && is_scalar($_GET[$key])) $query[$key]=(string)$_GET[$key];
}
header('Location: vacanze_lista.php'.($query?'?'.http_build_query($query):''),true,302);
exit;
