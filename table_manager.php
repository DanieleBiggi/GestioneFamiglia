<?php
include 'includes/session_check.php';
require_once 'includes/db.php';
$config = include __DIR__ . '/includes/table_config.php';
$foreignMap = include __DIR__ . '/includes/foreign_keys.php';

$table = $_GET['table'] ?? '';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'table:' . $table, 'view')) { http_response_code(403); exit('Accesso negato'); }
$canInsert = has_permission($conn, 'table:' . $table, 'insert');
$canUpdate = has_permission($conn, 'table:' . $table, 'update');
$canDelete = has_permission($conn, 'table:' . $table, 'delete');
if (!isset($config[$table])) {
    die('Tabella non valida');
}
$columns = $config[$table]['columns'];
$primaryKey = $config[$table]['primary_key'];

// Colonne da non visualizzare per tabella
$excludeColumns = [];
if ($table === 'utenti') {
    $excludeColumns[] = 'profile';
}
$displayColumns = array_values(array_filter($columns, function($c) use ($primaryKey, $excludeColumns) {
    if ($c === $primaryKey) return false;
    if (in_array($c, $excludeColumns)) return false;
    return true;
}));

// Gestione colonne booleane
$booleanColumns = [];
if ($table === 'famiglie') {
    $booleanColumns = ['in_gestione'];
} elseif ($table === 'utenti') {
    $booleanColumns = ['attivo'];
}

function format_label($col) {
    $col = str_replace("id_","",$col);
    $label = ucwords(str_replace('_', ' ', $col));
    return $label;
}

$lookups = [];
foreach ($displayColumns as $col) {
    if (isset($foreignMap[$col])) {
        $fk = $foreignMap[$col];
        $tablefk = $fk['table'];
        $key   = $fk['key'];
        $label = $fk['label'];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $tablefk) ||
            !preg_match('/^[A-Za-z0-9_]+$/', $key) ||
            !preg_match("/^[A-Za-z0-9_(),\\s']+$/", $label)) {
            continue;
        }
        $tablefk = $conn->real_escape_string($tablefk);
        $key   = $conn->real_escape_string($key);
        $label = $conn->real_escape_string($label);
        $sql = "SELECT `{$key}` AS id, {$label} AS label FROM `{$tablefk}`";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {                
                $value = trim((string) $row['label']);
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                $lookups[$col][$row['id']] = $value;
            }
        }
    }
}

include 'includes/header.php';
?>
<div class="d-flex mb-3 justify-content-between">
  <h4><?= htmlspecialchars($table) ?></h4>
  <button id="addBtn" class="btn btn-outline-light btn-sm <?= $canInsert ? '' : 'd-none' ?>">Aggiungi nuovo</button>
</div>
<input type="text" id="search" class="form-control bg-dark text-white border-secondary mb-3" placeholder="Cerca...">
<table class="table table-dark table-striped" id="data-table">
  <thead>
    <tr>
      <?php foreach ($displayColumns as $col): ?>
      <th><?= htmlspecialchars(format_label($col)) ?></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody></tbody>
</table>

<div class="modal fade" id="recordModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content bg-dark text-white">
      <form id="record-form">
        <div class="modal-header">
          <h5 class="modal-title">Record</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="<?= htmlspecialchars($primaryKey) ?>">
          <?php foreach ($displayColumns as $col): ?>
            <div class="mb-3">
              <label class="form-label"><?= htmlspecialchars(format_label($col)) ?></label>
              <?php if (isset($lookups[$col])): ?>
                <select name="<?= htmlspecialchars($col) ?>" class="form-select bg-dark text-white border-secondary">
                  <?php foreach ($lookups[$col] as $id => $label): ?>
                    <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($label) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php elseif (in_array($col, $booleanColumns)): ?>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="<?= htmlspecialchars($col) ?>" id="<?= htmlspecialchars($col) ?>_si" value="1">
                    <label class="form-check-label" for="<?= htmlspecialchars($col) ?>_si">Si</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="<?= htmlspecialchars($col) ?>" id="<?= htmlspecialchars($col) ?>_no" value="0">
                    <label class="form-check-label" for="<?= htmlspecialchars($col) ?>_no">No</label>
                  </div>
                </div>
              <?php else: ?>
                <input type="text" name="<?= htmlspecialchars($col) ?>" class="form-control bg-dark text-white border-secondary">
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="modal-footer">
          <button type="button" id="deleteBtn" class="btn btn-danger me-auto <?= $canDelete ? '' : 'd-none' ?>">Elimina</button>
          <button type="button" id="cancelBtn" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary">Salva</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title">Conferma eliminazione</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body">
        Sei sicuro di voler eliminare questo record?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Elimina</button>
      </div>
    </div>
  </div>
</div>

<script src="js/table_crud.js"></script>
<script>
initTableManager('<?= $table ?>', <?= json_encode($displayColumns) ?>, '<?= $primaryKey ?>', <?= json_encode($lookups) ?>, <?= json_encode($booleanColumns) ?>, {canInsert: <?= $canInsert ? 'true' : 'false' ?>, canUpdate: <?= $canUpdate ? 'true' : 'false' ?>, canDelete: <?= $canDelete ? 'true' : 'false' ?>});
</script>
<?php include 'includes/footer.php'; ?>
