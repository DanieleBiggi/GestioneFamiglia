<?php
$config = include __DIR__ . '/../includes/table_config.php';
$table = $_GET['table'] ?? '';
if (!isset($config[$table])) {
    die('Tabella non valida');
}
$columns = $config[$table]['columns'];
$primaryKey = $config[$table]['primary_key'];
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Gestione tabella <?php echo htmlspecialchars($table); ?></title>
</head>
<body>
<h1>Gestione tabella <?php echo htmlspecialchars($table); ?></h1>
<input type="text" id="search" placeholder="Cerca...">
<table border="1" id="data-table">
    <thead>
        <tr>
            <?php foreach ($columns as $col): ?>
                <th><?php echo htmlspecialchars($col); ?></th>
            <?php endforeach; ?>
            <th>Azioni</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>

<h2>Nuovo record</h2>
<form id="add-form">
    <?php foreach ($columns as $col): if ($col === $primaryKey) continue; ?>
        <label><?php echo htmlspecialchars($col); ?>: <input type="text" name="<?php echo htmlspecialchars($col); ?>"></label><br>
    <?php endforeach; ?>
    <button type="submit">Inserisci</button>
</form>

<script src="../js/table_crud.js"></script>
<script>
initTableManager('<?php echo $table; ?>', <?php echo json_encode($columns); ?>, '<?php echo $primaryKey; ?>');
</script>
</body>
</html>
