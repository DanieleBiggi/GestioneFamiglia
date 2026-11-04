<?php
// Gli URL precedenti restano utilizzabili ma aprono sempre la nuova interfaccia.
include 'includes/session_check.php';
$id=(int)($_GET['id'] ?? 0);
$alt=(int)($_GET['alt'] ?? 0);
if($id<=0){header('Location: vacanze_lista.php',true,302);exit;}
$url='vacanze_workspace.php?id='.$id.'&tab='.($alt>0?'scenario&alt='.$alt:'overview');
header('Location: '.$url,true,302);
exit;
