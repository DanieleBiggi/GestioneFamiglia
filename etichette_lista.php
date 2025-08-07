<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
include 'includes/header.php';

$sql = "SELECT id_etichetta, descrizione, attivo FROM bilancio_etichette ORDER BY descrizione ASC";
$etichette = $conn->query($sql);
?>

<div class="text-white">
  <div class="d-flex mb-3 justify-content-between"><h4>Etichette</h4><a href="etichetta_dettaglio.php" class="btn btn-outline-light btn-sm">Aggiungi nuova</a></div>
  <div class="d-flex mb-3 align-items-center">
    <input type="text" id="search" class="form-control bg-dark text-white border-secondary me-2" placeholder="Cerca">
    <div class="form-check form-switch text-nowrap">
      <input class="form-check-input" type="checkbox" id="showInactive">
      <label class="form-check-label" for="showInactive">Mostra non attive</label>
    </div>
  </div>

  <div class="list-group" id="labelList">
    <?php while ($row = $etichette->fetch_assoc()): ?>
      <?php $isActive = (int)($row['attivo'] ?? 0) === 1; ?>
      <a href="etichetta.php?id_etichetta=<?= urlencode($row['id_etichetta']) ?>" class="list-group-item bg-dark text-white d-flex justify-content-between align-items-center text-decoration-none label-card<?= $isActive ? '' : ' inactive' ?>" data-search="<?= strtolower($row['descrizione']) ?>" style="<?= $isActive ? '' : 'display:none;' ?>">
        <span><?= htmlspecialchars($row['descrizione']) ?></span>
        <?php if ($isActive): ?>
          <i class="bi bi-check-circle-fill text-success"></i>
        <?php else: ?>
          <i class="bi bi-x-circle-fill text-danger"></i>
        <?php endif; ?>
      </a>
    <?php endwhile; ?>
  </div>
</div>

<script src="js/etichette.js"></script>
<?php include 'includes/footer.php'; ?>

