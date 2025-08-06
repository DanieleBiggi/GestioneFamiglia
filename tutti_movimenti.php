<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$mese = $_GET['mese'] ?? date('Y-m');
$start = "$mese-01";
$end = date("Y-m-t", strtotime($start));

$sql = "SELECT * FROM v_movimenti_revolut WHERE completed_date BETWEEN ? AND ? ORDER BY completed_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$result = $stmt->get_result();

$movimenti = [];
while ($row = $result->fetch_assoc()) {
  $giorno = date("Y-m-d", strtotime($row['completed_date']));
  $movimenti[$giorno][] = $row;
}
?>

<div class="container">
  <form method="get" class="mb-4">
    <label for="mese" class="form-label">Seleziona mese:</label>
    <input type="month" id="mese" name="mese" value="<?= $mese ?>" class="form-control" onchange="this.form.submit()">
  </form>

  <?php if (!empty($movimenti)): ?>
    <?php foreach ($movimenti as $giorno => $items): ?>
      <h5 class="mt-4 mb-2"><?= date("d F Y", strtotime($giorno)) ?></h5>
      <div class="list-group">
        <?php foreach ($items as $row): ?>
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
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="text-center text-muted">Nessun movimento per il mese selezionato.</p>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>