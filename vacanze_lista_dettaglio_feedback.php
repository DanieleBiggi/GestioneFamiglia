<?php
// La gestione recensioni è ora integrata nella pagina Vacanze V2.
include 'includes/session_check.php';
$id=(int)($_GET['id'] ?? 0);
if($id<=0){header('Location: vacanze_lista.php',true,302);exit;}
header('Location: vacanze_workspace.php?id='.$id.'&tab=sharing#recensioni',true,302);
exit;
