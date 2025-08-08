<?php
include 'includes/session_check.php';
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'table:utenti', 'update')) { http_response_code(403); exit('Accesso negato'); }

$config = include __DIR__ . '/includes/table_config.php';
$foreignMap = include __DIR__ . '/includes/foreign_keys.php';
$table = 'utenti';
$columns = $config[$table]['columns'];
$primaryKey = $config[$table]['primary_key'];
$displayColumns = array_values(array_filter($columns, fn($c) => $c !== $primaryKey));
$booleanColumns = ['attivo'];
$lookups = [];
foreach ($displayColumns as $col) {
    if (isset($foreignMap[$col])) {
        $fk = $foreignMap[$col];
        $tablefk = $fk['table'];
        $key = $fk['key'];
        $label = $fk['label'];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $tablefk) ||
            !preg_match('/^[A-Za-z0-9_]+$/', $key) ||
            !preg_match("/^[A-Za-z0-9_(),\\s']+$/", $label)) {
            continue;
        }
        $sql = "SELECT `$key` AS id, $label AS label FROM `$tablefk`";
        if ($res = $conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $lookups[$col][$row['id']] = trim((string)$row['label']);
            }
        }
    }
}
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$data = [];
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM utenti WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc() ?: [];
    $stmt->close();
}
$canDelete = has_permission($conn, 'table:utenti', 'delete');
include 'includes/header.php';
?>
<div class="container text-white">
  <a href="gestione_utenti.php" class="btn btn-outline-light mb-3">&larr; Indietro</a>
  <h4 class="mb-4"><?= $id ? 'Modifica' : 'Nuovo' ?> Utente</h4>
</div>
<form id="user-form" class="bg-dark text-white p-3 rounded">
  <?php foreach ($displayColumns as $col): ?>
    <div class="mb-3">
      <label class="form-label"><?= htmlspecialchars(str_replace('_',' ', $col)) ?></label>
      <?php if (isset($lookups[$col])): ?>
        <select name="<?= htmlspecialchars($col) ?>" class="form-select bg-dark text-white border-secondary">
          <?php foreach ($lookups[$col] as $idOpt => $label): ?>
            <option value="<?= htmlspecialchars($idOpt) ?>" <?= (($data[$col] ?? '') == $idOpt) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      <?php elseif (in_array($col, $booleanColumns)): ?>
        <div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="<?= htmlspecialchars($col) ?>" id="<?= htmlspecialchars($col) ?>_si" value="1" <?= (($data[$col] ?? '') == '1') ? 'checked' : '' ?>>
            <label class="form-check-label" for="<?= htmlspecialchars($col) ?>_si">Si</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="<?= htmlspecialchars($col) ?>" id="<?= htmlspecialchars($col) ?>_no" value="0" <?= (($data[$col] ?? '') == '0') ? 'checked' : '' ?>>
            <label class="form-check-label" for="<?= htmlspecialchars($col) ?>_no">No</label>
          </div>
        </div>
      <?php else: ?>
        <input type="text" name="<?= htmlspecialchars($col) ?>" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data[$col] ?? '') ?>">
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if ($id): ?>
    <input type="hidden" name="<?= htmlspecialchars($primaryKey) ?>" value="<?= (int)$id ?>">
  <?php endif; ?>
  <button type="submit" class="btn btn-primary w-100 mb-2">Salva</button>
</form>
<?php if ($id): ?>
  <button type="button" id="resetPasscodeBtn" class="btn btn-warning w-100 mb-2 mt-2">Reset passcode lock</button>
  <?php if ($canDelete): ?>
    <button type="button" id="deleteBtn" class="btn btn-danger w-100">Elimina</button>
  <?php endif; ?>
<?php endif; ?>
<script>
const id = <?= (int)$id ?>;
const form = document.getElementById('user-form');
form.addEventListener('submit', e => {
  e.preventDefault();
  const fd = new FormData(form);
  fd.append('table', '<?= $table ?>');
  fd.append('action', id ? 'update' : 'insert');
  fetch('ajax/table_crud.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(() => { window.location.href = 'gestione_utenti.php'; });
});
<?php if ($id): ?>
const resetBtn = document.getElementById('resetPasscodeBtn');
resetBtn.addEventListener('click', () => {
  const fd = new FormData();
  fd.append('action','unlock_passcode');
  fd.append('id', id);
  fetch('ajax/gestione_utenti.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(() => location.reload());
});
<?php if ($canDelete): ?>
const deleteBtn = document.getElementById('deleteBtn');
deleteBtn.addEventListener('click', () => {
  if (!confirm('Sei sicuro di voler eliminare questo utente?')) return;
  const fd = new FormData();
  fd.append('action','delete');
  fd.append('table','<?= $table ?>');
  fd.append('<?= $primaryKey ?>', id);
  fetch('ajax/table_crud.php', { method:'POST', body: fd })
    .then(r => r.json())
    .then(() => { window.location.href = 'gestione_utenti.php'; });
});
<?php endif; ?>
<?php endif; ?>
</script>
<?php include 'includes/footer.php'; ?>
