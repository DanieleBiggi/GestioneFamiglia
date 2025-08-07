<?php
include '../includes/session_check.php';
require_once '../includes/db.php';
$config = include __DIR__ . '/../includes/table_config.php';
$foreignMap = include __DIR__ . '/../includes/foreign_keys.php';

$table = $_GET['table'] ?? '';
if (!isset($config[$table])) {
    die('Tabella non valida');
}
$columns = $config[$table]['columns'];
$primaryKey = $config[$table]['primary_key'];
$displayColumns = array_values(array_filter($columns, fn($c) => $c !== $primaryKey));

$lookups = [];
foreach ($displayColumns as $col) {
    if (isset($foreignMap[$col])) {
        $fk = $foreignMap[$col];
        $sql = "SELECT {$fk['key']} AS id, {$fk['label']} AS label FROM {$fk['table']}";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $lookups[$col][$row['id']] = $row['label'];
            }
        }
    }
}

include '../includes/header.php';
?>
<div class="d-flex mb-3 justify-content-between">
  <h4><?= htmlspecialchars($table) ?></h4>
  <button id="addBtn" class="btn btn-outline-light btn-sm">Aggiungi nuovo</button>
</div>
<input type="text" id="search" class="form-control bg-dark text-white border-secondary mb-3" placeholder="Cerca...">
<table class="table table-dark table-striped" id="data-table">
  <thead>
    <tr>
      <?php foreach ($displayColumns as $col): ?>
      <th><?= htmlspecialchars($col) ?></th>
      <?php endforeach; ?>
      <th>Azioni</th>
    </tr>
  </thead>
  <tbody></tbody>
</table>

<form id="record-form" class="d-none mt-4">
  <input type="hidden" name="<?= htmlspecialchars($primaryKey) ?>">
  <?php foreach ($displayColumns as $col): ?>
    <div class="mb-3">
      <label class="form-label"><?= htmlspecialchars($col) ?></label>
      <?php if (isset($lookups[$col])): ?>
        <select name="<?= htmlspecialchars($col) ?>" class="form-select bg-dark text-white border-secondary">
          <?php foreach ($lookups[$col] as $id => $label): ?>
            <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <input type="text" name="<?= htmlspecialchars($col) ?>" class="form-control bg-dark text-white border-secondary">
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <button type="submit" class="btn btn-primary">Salva</button>
  <button type="button" id="cancelBtn" class="btn btn-secondary">Annulla</button>
</form>

<script src="../js/table_crud.js"></script>
<script>
initTableManager('<?= $table ?>', <?= json_encode($displayColumns) ?>, '<?= $primaryKey ?>', <?= json_encode($lookups) ?>);
</script>
<?php include '../includes/footer.php'; ?>
