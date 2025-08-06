<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';
?>

<input type="text" id="search" class="form-control bg-dark text-white border-secondary mb-3" placeholder="Cerca nei movimenti">
<div id="searchResults"></div>

<?php
$sql = "SELECT * FROM v_movimenti_revolut ORDER BY started_date DESC LIMIT 5";
$result = $conn->query($sql);

if ($result->num_rows > 0): ?>
  <div id="recentMovimenti" class="list-group">
    <?php while($row = $result->fetch_assoc()): ?>
      <a href="dettaglio.php?id=<?= $row['id_movimento_revolut'] ?>" class="list-group-item shadow-sm text-white text-decoration-none">
        <div class="d-flex justify-content-between">
          <div>
            <strong><?= htmlspecialchars($row['descrizione_extra'] ?: $row['description']) ?></strong><br>
            <small><?= date('d/m/Y H:i', strtotime($row['started_date'])) ?></small><br>
            <?php if (!empty($row['etichette'])): ?>
              <span class="badge-etichetta"><?= htmlspecialchars($row['etichette']) ?></span>
            <?php endif; ?>
          </div>
          <div class="fs-5 text-end">
            <?= number_format($row['amount'], 2, ',', '.') ?> €
          </div>
        </div>
      </a>
    <?php endwhile; ?>
  </div>

  <div class="text-center mt-3">
    <a href="tutti_movimenti.php" class="btn btn-outline-light btn-sm">Visualizza tutti</a>
  </div>
<?php else: ?>
  <p class="text-center text-muted">Nessun movimento presente.</p>
<?php endif; ?>

<script src="js/index.js"></script>
<?php include 'includes/footer.php'; ?>