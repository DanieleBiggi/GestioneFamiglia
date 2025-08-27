<?php include 'includes/session_check.php'; ?>
<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'includes/db.php';
require_once 'includes/utility.php';
include 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['error' => 'ID mancante']);
    exit;
}

$stmt = $conn->prepare('SELECT stringa_da_completare, parametri FROM dati_remoti WHERE id_dato_remoto = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['error' => 'Record non trovato']);
    exit;
}

$SQLinv = $row['stringa_da_completare'] ?? '';
$parametri = json_decode($row['parametri'] ?? '', true);
if (is_array($parametri)) {
    foreach ($parametri as $chiave => $valore) {
        $SQLinv = str_replace('[[' . $chiave . ']]', $valore, $SQLinv);
    }
}

$utility = new Utility();

$ret = $utility->getDati($SQLinv);
//print_r($ret);
$risultati = $ret;

$ar = [];
$ar['C99'] = "IMPORTO";
$ar['C06'] = "IMPORTO";
$ar['019'] = "IMPORTO";
$ar['Z50'] = "QUANTITA";
$ar['Z51'] = "QUANTITA";
foreach($risultati as $ris)
{
	if(array_key_exists($ris['CODVOCE'],$ar))
	{
		echo
		"<div class='d-flex mb-2'>".
			"<div class='font-weight-bold w-50'>".$ris['DESCRIZ']."</div><div class='text-right pl-2 w-50'>".$ris[$ar[$ris['CODVOCE']]]."</div>".
		"</div>";
	}
}
?>
<?php
echo
"<div id='div_details' style='display:none'>";
foreach($risultati as $ris)
{
	foreach($ris as $chiave => $valore)
	{
		if(($chiave!="ANNO" && $chiave!="MESE") || CurrentPage()->id_dato_remoto->CurrentValue!=10)
		{
			echo $chiave.": ".$valore."<br>";
		}
	}
	echo "<hr>";
}
echo
"</div>";
?>
</div>
</div>

