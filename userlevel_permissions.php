<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $userlevelid = (int)$_POST['userlevelid'];
    $resource_id = (int)$_POST['resource_id'];
    $can_view = isset($_POST['can_view']) ? 1 : 0;
    $can_insert = isset($_POST['can_insert']) ? 1 : 0;
    $can_update = isset($_POST['can_update']) ? 1 : 0;
    $can_delete = isset($_POST['can_delete']) ? 1 : 0;

    $stmt = $conn->prepare(
        "INSERT INTO userlevel_permissions (userlevelid, resource_id, can_view, can_insert, can_update, can_delete) VALUES (?, ?, ?, ?, ?, ?) " .
        "ON DUPLICATE KEY UPDATE can_view=VALUES(can_view), can_insert=VALUES(can_insert), can_update=VALUES(can_update), can_delete=VALUES(can_delete)"
    );
    $stmt->bind_param('iiiiii', $userlevelid, $resource_id, $can_view, $can_insert, $can_update, $can_delete);
    $stmt->execute();
    $stmt->close();
    header('Location: userlevel_permissions.php');
    exit;
}

// Fetch userlevels and resources for filters/forms
$userlevels = [];
if ($res = $conn->query("SELECT userlevelid, userlevelname FROM userlevels ORDER BY userlevelname")) {
    while ($row = $res->fetch_assoc()) {
        $userlevels[] = $row;
    }
    $res->free();
}

$resources = [];
if ($res = $conn->query("SELECT id, name FROM resources ORDER BY name")) {
    while ($row = $res->fetch_assoc()) {
        $resources[] = $row;
    }
    $res->free();
}

$sql = "SELECT up.*, ul.userlevelname, r.name FROM userlevel_permissions up JOIN userlevels ul ON up.userlevelid = ul.userlevelid JOIN resources r ON up.resource_id = r.id ORDER BY ul.userlevelname, r.name";
$permRes = $conn->query($sql);
?>

<div class="d-flex mb-3 justify-content-between">
  <h4>Permessi Userlevel</h4>
  <a href="#addPermission" class="btn btn-outline-light btn-sm" data-bs-toggle="collapse">Aggiungi nuovo</a>
</div>

<div class="row g-2 mb-3">
  <div class="col">
    <select id="filterUserlevel" class="form-select bg-dark text-white border-secondary">
      <option value="">Tutti i livelli</option>
      <?php foreach ($userlevels as $ul): ?>
      <option value="<?= (int)$ul['userlevelid'] ?>"><?= htmlspecialchars($ul['userlevelname']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col">
    <select id="filterResource" class="form-select bg-dark text-white border-secondary">
      <option value="">Tutte le risorse</option>
      <?php foreach ($resources as $resItem): ?>
      <option value="<?= (int)$resItem['id'] ?>"><?= htmlspecialchars($resItem['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div id="permissionsList">
  <?php while ($row = $permRes->fetch_assoc()): ?>
  <form method="post" class="permission-card movement d-flex justify-content-between align-items-start text-white text-decoration-none bg-dark p-3 mb-2 rounded" data-userlevel="<?= (int)$row['userlevelid'] ?>" data-resource="<?= (int)$row['resource_id'] ?>">
    <div class="flex-grow-1">
      <div class="fw-semibold"><?= htmlspecialchars($row['userlevelname']) ?></div>
      <div class="small"><?= htmlspecialchars($row['name']) ?></div>
      <div class="d-flex flex-wrap gap-3 mt-2">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="can_view" value="1" <?= $row['can_view'] ? 'checked' : '' ?>>
          <label class="form-check-label">View</label>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="can_insert" value="1" <?= $row['can_insert'] ? 'checked' : '' ?>>
          <label class="form-check-label">Insert</label>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="can_update" value="1" <?= $row['can_update'] ? 'checked' : '' ?>>
          <label class="form-check-label">Update</label>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="can_delete" value="1" <?= $row['can_delete'] ? 'checked' : '' ?>>
          <label class="form-check-label">Delete</label>
        </div>
      </div>
    </div>
    <!-- Auto-save on checkbox change; button removed -->
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="userlevelid" value="<?= (int)$row['userlevelid'] ?>">
    <input type="hidden" name="resource_id" value="<?= (int)$row['resource_id'] ?>">
  </form>
  <?php endwhile; $permRes->free(); ?>
</div>

<div class="collapse" id="addPermission">
  <form method="post" class="bg-dark text-white p-3 rounded mb-3">
    <input type="hidden" name="action" value="update">
    <div class="mb-2">
      <select name="userlevelid" class="form-select bg-dark text-white border-secondary" required>
        <option value="">Seleziona userlevel</option>
        <?php foreach ($userlevels as $ul): ?>
        <option value="<?= (int)$ul['userlevelid'] ?>"><?= htmlspecialchars($ul['userlevelname']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-2">
      <select name="resource_id" class="form-select bg-dark text-white border-secondary" required>
        <option value="">Seleziona risorsa</option>
        <?php foreach ($resources as $resItem): ?>
        <option value="<?= (int)$resItem['id'] ?>"><?= htmlspecialchars($resItem['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="d-flex flex-wrap gap-3">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="can_view" value="1">
        <label class="form-check-label">View</label>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="can_insert" value="1">
        <label class="form-check-label">Insert</label>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="can_update" value="1">
        <label class="form-check-label">Update</label>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="can_delete" value="1">
        <label class="form-check-label">Delete</label>
      </div>
    </div>
    <button type="submit" class="btn btn-outline-light btn-sm mt-3">Salva</button>
  </form>
</div>

<script src="js/userlevel_permissions.js"></script>
<?php include 'includes/footer.php'; ?>
