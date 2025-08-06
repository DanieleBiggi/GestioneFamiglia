<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
include 'includes/header.php';
setlocale(LC_TIME, 'it_IT.UTF-8');

$etichetta = $_GET['etichetta'] ?? '';
$mese = $_GET['mese'] ?? '';

if ($etichetta === '') {
    echo '<p class="text-center text-muted">Nessuna etichetta selezionata.</p>';
    include 'includes/footer.php';
    return;
}

// Lista mesi disponibili per questa etichetta
$mesi = [];
$stmtM = $conn->prepare("SELECT DATE_FORMAT(started_date,'%Y-%m') AS ym, DATE_FORMAT(started_date,'%M %Y') AS label FROM v_movimenti_revolut WHERE FIND_IN_SET(?, etichette) GROUP BY ym ORDER BY ym DESC");
$stmtM->bind_param('s', $etichetta);
$stmtM->execute();
$resM = $stmtM->get_result();
while ($row = $resM->fetch_assoc()) {
    $mesi[] = $row;
}
$stmtM->close();

// Calcolo dei totali entrate/uscite
if ($mese !== '') {
    $stmtTot = $conn->prepare("SELECT SUM(CASE WHEN amount>=0 THEN amount ELSE 0 END) AS entrate, SUM(CASE WHEN amount<0 THEN amount ELSE 0 END) AS uscite FROM v_movimenti_revolut WHERE FIND_IN_SET(?, etichette) AND DATE_FORMAT(started_date,'%Y-%m')=?");
    $stmtTot->bind_param('ss', $etichetta, $mese);
} else {
    $stmtTot = $conn->prepare("SELECT SUM(CASE WHEN amount>=0 THEN amount ELSE 0 END) AS entrate, SUM(CASE WHEN amount<0 THEN amount ELSE 0 END) AS uscite FROM v_movimenti_revolut WHERE FIND_IN_SET(?, etichette)");
    $stmtTot->bind_param('s', $etichetta);
}
$stmtTot->execute();
$totali = $stmtTot->get_result()->fetch_assoc();
$stmtTot->close();

// Movimenti dell'etichetta
if ($mese !== '') {
    $stmtMov = $conn->prepare("SELECT id_movimento_revolut, started_date, amount, COALESCE(NULLIF(descrizione_extra,''), description) AS descrizione, etichette FROM v_movimenti_revolut WHERE FIND_IN_SET(?, etichette) AND DATE_FORMAT(started_date,'%Y-%m')=? ORDER BY started_date DESC");
    $stmtMov->bind_param('ss', $etichetta, $mese);
} else {
    $stmtMov = $conn->prepare("SELECT id_movimento_revolut, started_date, amount, COALESCE(NULLIF(descrizione_extra,''), description) AS descrizione, etichette FROM v_movimenti_revolut WHERE FIND_IN_SET(?, etichette) ORDER BY started_date DESC");
    $stmtMov->bind_param('s', $etichetta);
}
$stmtMov->execute();
$movimenti = $stmtMov->get_result();
$stmtMov->close();

// Dettaglio per gruppo
if ($mese !== '') {
    $stmtGrp = $conn->prepare("SELECT m.id_gruppo_transazione, g.descrizione AS gruppo, SUM(CASE WHEN m.amount>=0 THEN m.amount ELSE 0 END) AS entrate, SUM(CASE WHEN m.amount<0 THEN m.amount ELSE 0 END) AS uscite FROM v_movimenti_revolut m LEFT JOIN bilancio_gruppi_transazione g ON m.id_gruppo_transazione = g.id_gruppo_transazione WHERE FIND_IN_SET(?, m.etichette) AND DATE_FORMAT(m.started_date,'%Y-%m')=? GROUP BY m.id_gruppo_transazione, g.descrizione ORDER BY g.descrizione");
    $stmtGrp->bind_param('ss', $etichetta, $mese);
} else {
    $stmtGrp = $conn->prepare("SELECT m.id_gruppo_transazione, g.descrizione AS gruppo, SUM(CASE WHEN m.amount>=0 THEN m.amount ELSE 0 END) AS entrate, SUM(CASE WHEN m.amount<0 THEN m.amount ELSE 0 END) AS uscite FROM v_movimenti_revolut m LEFT JOIN bilancio_gruppi_transazione g ON m.id_gruppo_transazione = g.id_gruppo_transazione WHERE FIND_IN_SET(?, m.etichette) GROUP BY m.id_gruppo_transazione, g.descrizione ORDER BY g.descrizione");
    $stmtGrp->bind_param('s', $etichetta);
}
$stmtGrp->execute();
$resGrp = $stmtGrp->get_result();
$gruppi = [];
while ($r = $resGrp->fetch_assoc()) {
    $gruppi[] = $r;
}
$stmtGrp->close();
?>

<div class="text-white">
  <h4 class="mb-3">Movimenti per etichetta: <?= htmlspecialchars($etichetta) ?></h4>

  <form method="get" class="mb-3">
    <input type="hidden" name="etichetta" value="<?= htmlspecialchars($etichetta) ?>">
    <div class="d-flex gap-2 align-items-center">
      <label for="mese" class="form-label mb-0">Mese:</label>
      <select name="mese" id="mese" class="form-select w-auto" onchange="this.form.submit()">
        <option value="" <?= $mese === '' ? 'selected' : '' ?>>Tutti i mesi</option>
        <?php foreach ($mesi as $m): ?>
          <option value="<?= htmlspecialchars($m['ym']) ?>" <?= $mese === $m['ym'] ? 'selected' : '' ?>><?= ucfirst($m['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <div class="d-flex gap-4 mb-4">
    <div>Entrate: <span class="text-success"><?= number_format($totali['entrate'] ?? 0, 2, ',', '.') ?> €</span></div>
    <div>Uscite: <span class="text-danger"><?= number_format(abs($totali['uscite'] ?? 0), 2, ',', '.') ?> €</span></div>
  </div>

  <?php if ($movimenti->num_rows > 0): ?>
    <?php while ($mov = $movimenti->fetch_assoc()): ?>
      <?php
        $importo = number_format($mov['amount'], 2, ',', '.');
        $classe_importo = $mov['amount'] >= 0 ? 'text-success' : 'text-danger';
      ?>
      <div class="movement d-flex align-items-center py-2">
        <div class="icon me-3"><i class="bi bi-arrow-left-right fs-4"></i></div>
        <div class="flex-grow-1">
          <div class="descr"><?= htmlspecialchars($mov['descrizione']) ?></div>
          <div class="text-muted small"><?= date('d/m/Y H:i', strtotime($mov['started_date'])) ?></div>
        </div>
        <div class="amount ms-2 <?= $classe_importo ?>"><?= ($mov['amount'] >= 0 ? '+' : '') . $importo ?> €</div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <p class="text-center text-muted">Nessun movimento per questa etichetta.</p>
  <?php endif; ?>

  <?php if (!empty($gruppi)): ?>
    <h5 class="mt-4">Dettaglio per gruppo</h5>
    <ul class="list-group list-group-flush">
      <?php foreach ($gruppi as $g): ?>
        <li class="list-group-item bg-dark text-white d-flex justify-content-between">
          <span><?= htmlspecialchars($g['gruppo'] ?? $g['id_gruppo_transazione']) ?></span>
          <span>
            <span class="text-success me-3"><?= number_format($g['entrate'], 2, ',', '.') ?> €</span>
            <span class="text-danger"><?= number_format(abs($g['uscite']), 2, ',', '.') ?> €</span>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

