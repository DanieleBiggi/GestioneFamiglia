<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
include 'includes/header.php';

$q = trim($_GET['q'] ?? '');
$show_inactive = isset($_GET['show_inactive']);

$sql = "SELECT id_etichetta, descrizione, attivo FROM bilancio_etichette";
$params = [];
$types = '';
$conds = [];

if ($q !== '') {
    $conds[] = "descrizione LIKE ?";
    $params[] = "%$q%";
    $types .= 's';
} elseif (!$show_inactive) {
    $conds[] = "attivo = 1";
}

if ($conds) {
    $sql .= ' WHERE ' . implode(' AND ', $conds);
}
$sql .= ' ORDER BY descrizione ASC';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$etichette = $stmt->get_result();
$stmt->close();
?>

<div class="text-white">
  <h4 class="mb-3">Etichette</h4>
  <form method="get" class="mb-3">
    <div class="mb-2">
      <input type="text" name="q" class="form-control" placeholder="Cerca..." value="<?= htmlspecialchars($q) ?>">
    </div>
    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" value="1" id="show_inactive" name="show_inactive" <?= $show_inactive ? 'checked' : '' ?>>
      <label class="form-check-label" for="show_inactive">Includi etichette disattivate</label>
    </div>
    <button class="btn btn-outline-light w-100" type="submit">Filtra</button>
  </form>

  <div class="list-group">
    <?php while ($row = $etichette->fetch_assoc()): ?>
      <a href="etichetta.php?etichetta=<?= urlencode($row['descrizione']) ?>" class="list-group-item bg-dark text-white d-flex justify-content-between align-items-center text-decoration-none">
        <span><?= htmlspecialchars($row['descrizione']) ?></span>
        <?php if ($row['attivo']): ?>
          <i class="bi bi-check-circle-fill text-success"></i>
        <?php else: ?>
          <i class="bi bi-x-circle-fill text-danger"></i>
        <?php endif; ?>
      </a>
    <?php endwhile; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

