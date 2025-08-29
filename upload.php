<?php
include 'includes/session_check.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Carica scontrino</title>
</head>
<body>
<form action="ajax/save_caricamento.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="id_supermercato" value="0">
    <div>
        <label>File scontrino: <input type="file" name="nome_file" required></label>
    </div>
    <div>
        <label>Data scontrino: <input type="date" name="data_scontrino"></label>
    </div>
    <div>
        <label>Totale scontrino: <input type="number" step="0.01" name="totale_scontrino"></label>
    </div>
    <div>
        <label>Descrizione: <input type="text" name="descrizione"></label>
    </div>
    <div>
        <button type="submit">Carica</button>
    </div>
</form>
</body>
</html>
