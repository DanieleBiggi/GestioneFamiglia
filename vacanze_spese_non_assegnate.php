<?php
// La lista delle spese non assegnate è ora un filtro del catalogo V2.
include 'includes/session_check.php';
$id=(int)($_GET['id'] ?? 0);
if($id<=0){header('Location: vacanze_lista.php',true,302);exit;}
header('Location: vacanze_workspace.php?id='.$id.'&tab=catalog&filter=unassigned',true,302);
exit;
