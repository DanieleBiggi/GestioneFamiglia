<?php
// Compatibilità dei vecchi URL: la sola interfaccia attiva è Vacanze V2.
include 'includes/session_check.php';
$id=(int)($_GET['id'] ?? 0);
if($id<=0){header('Location: vacanze_lista.php',true,302);exit;}
$alt=(int)($_GET['alt'] ?? 0);
$url='vacanze_workspace.php?id='.$id.'&tab=overview';
if($alt>0) $url.='&alt='.$alt;
header('Location: '.$url,true,302);
exit;
