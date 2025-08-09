<?php
include 'includes/session_check.php';
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:gestione_utenti.php', 'view')) { http_response_code(403); exit('Accesso negato'); }

$config = include __DIR__ . '/includes/table_config.php';
$foreignMap = include __DIR__ . '/includes/foreign_keys.php';
$table = 'utenti';
$columns = $config[$table]['columns'];
$primaryKey = $config[$table]['primary_key'];
$displayColumns = array_values(array_filter($columns, function($c) use ($primaryKey) {
    return $c !== $primaryKey;
}));
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
$canInsert = has_permission($conn, 'table:utenti', 'insert');
$canUpdate = has_permission($conn, 'table:utenti', 'update');
$canManageFamilies = has_permission($conn, 'table:utenti2famiglie', 'update');
include 'includes/header.php';
?>
<div class="d-flex flex-column flex-sm-row mb-3 justify-content-between align-items-sm-center">
  <h4 class="mb-2 mb-sm-0">Gestione Utenti</h4>
  <button id="addBtn" class="btn btn-outline-light btn-sm align-self-start align-self-sm-auto">Aggiungi nuovo</button>
</div>
<div class="row g-2 mb-3">
  <div class="col-12 col-md">
    <input type="text" id="search" class="form-control bg-dark text-white border-secondary" placeholder="Cerca utente">
  </div>
  <div class="col-12 col-md">
    <select id="userlevelFilter" class="form-select bg-dark text-white border-secondary">
      <option value="">Tutti i livelli</option>
      <?php if(isset($lookups['userlevelid'])): foreach($lookups['userlevelid'] as $id => $label): ?>
      <option value="<?= (int)$id ?>"><?= htmlspecialchars($label) ?></option>
      <?php endforeach; endif; ?>
    </select>
  </div>
  <div class="col-12 col-md">
    <select id="familyFilter" class="form-select bg-dark text-white border-secondary">
      <option value="">Tutte le famiglie</option>
      <?php
      $resFam = $conn->query("SELECT id_famiglia, nome_famiglia FROM famiglie ORDER BY nome_famiglia");
      while($fam = $resFam->fetch_assoc()): ?>
      <option value="<?= (int)$fam['id_famiglia'] ?>"><?= htmlspecialchars($fam['nome_famiglia']) ?></option>
      <?php endwhile; ?>
    </select>
  </div>
  <div class="col-12 col-md-auto d-flex align-items-center">
    <div class="form-check form-switch text-nowrap">
      <input class="form-check-input" type="checkbox" id="showInactive">
      <label class="form-check-label" for="showInactive">Mostra disattivati</label>
    </div>
  </div>
</div>
<div id="userList"></div>
<div class="modal fade" id="familiesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white">
      <form id="families-form">
        <div class="modal-header">
          <h5 class="modal-title">Assegna famiglie</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
        </div>
        <div class="modal-body" id="familiesList"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary">Salva</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="js/gestione_utenti.js"></script>
<script>
initUserManager('<?= $table ?>', <?= json_encode($displayColumns) ?>, '<?= $primaryKey ?>', <?= json_encode($lookups) ?>, <?= json_encode($booleanColumns) ?>, {canInsert: <?= $canInsert ? 'true':'false' ?>, canUpdate: <?= $canUpdate ? 'true':'false' ?>, canManageFamilies: <?= $canManageFamilies ? 'true':'false' ?>});
</script>
<?php include 'includes/footer.php'; ?>
