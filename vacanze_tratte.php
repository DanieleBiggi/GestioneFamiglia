<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$grp = $_GET['grp'] ?? '';

// Recupera info viaggio per breadcrumb
$stmt = $conn->prepare('SELECT titolo FROM viaggi WHERE id_viaggio=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$viaggio = $stmt->get_result()->fetch_assoc();
if (!$viaggio) {
    echo '<p class="text-danger">Viaggio non trovato</p>';
    include 'includes/footer.php';
    exit;
}

// Recupera tratte
$trStmt = $conn->prepare('SELECT * FROM viaggi_tratte WHERE id_viaggio=? AND gruppo_alternativa=? ORDER BY id_tratta');
$trStmt->bind_param('is', $id, $grp);
$trStmt->execute();
$trRes = $trStmt->get_result();
?>
<div class="container text-white">
  <a href="vacanze_view.php?id=<?= $id ?>" class="btn btn-outline-light mb-3">← Indietro</a>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="vacanze.php">Vacanze</a></li>
      <li class="breadcrumb-item"><a href="vacanze_view.php?id=<?= $id ?>"><?= htmlspecialchars($viaggio['titolo']) ?></a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($grp) ?></li>
    </ol>
  </nav>
  <div class="d-flex justify-content-between mb-3">
    <h4 class="m-0">Tratte - <?= htmlspecialchars($grp) ?></h4>
    <a class="btn btn-sm btn-outline-light" href="vacanze_tratte_dettaglio.php?id=<?= $id ?>&grp=<?= urlencode($grp) ?>">Aggiungi</a>
  </div>

  <?php if ($trRes->num_rows === 0): ?>
    <p class="text-muted">Nessuna tratta.</p>
  <?php else: ?>
    <div class="list-group">
      <?php while ($row = $trRes->fetch_assoc()): ?>
        <a href="vacanze_tratte_dettaglio.php?id=<?= $id ?>&grp=<?= urlencode($grp) ?>&id_tratta=<?= (int)$row['id_tratta'] ?>" class="list-group-item list-group-item-action bg-dark text-white">
          <div class="d-flex justify-content-between">
            <span><?= htmlspecialchars($row['descrizione'] ?: $row['tipo_tratta']) ?></span>
            <i class="bi bi-pencil"></i>
          </div>
        </a>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
