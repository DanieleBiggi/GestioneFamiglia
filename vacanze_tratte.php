<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$alt = (int)($_GET['alt'] ?? 0);

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

// Recupera alternativa
$altStmt = $conn->prepare('SELECT breve_descrizione FROM viaggi_alternative WHERE id_viaggio_alternativa=? AND id_viaggio=?');
$altStmt->bind_param('ii', $alt, $id);
$altStmt->execute();
$altRow = $altStmt->get_result()->fetch_assoc();
if (!$altRow) {
    echo '<p class="text-danger">Alternativa non trovata</p>';
    include 'includes/footer.php';
    exit;
}
$alt_desc = $altRow['breve_descrizione'];

// Recupera tratte
$trStmt = $conn->prepare('SELECT *, ((COALESCE(distanza_km,0)*COALESCE(consumo_litri_100km,0)/100)*COALESCE(prezzo_carburante_eur_litro,0) + COALESCE(pedaggi_eur,0) + COALESCE(costo_traghetto_eur,0) + COALESCE(costo_volo_eur,0) + COALESCE(costo_noleggio_eur,0) + COALESCE(altri_costi_eur,0)) AS totale FROM viaggi_tratte WHERE id_viaggio=? AND id_viaggio_alternativa=? ORDER BY id_tratta');
$trStmt->bind_param('ii', $id, $alt);
$trStmt->execute();
$trRes = $trStmt->get_result();

// Recupera alloggi
$allStmt = $conn->prepare('SELECT *, DATEDIFF(data_checkout, data_checkin) * COALESCE(costo_notte_eur,0) AS totale FROM viaggi_alloggi WHERE id_viaggio=? AND id_viaggio_alternativa=? ORDER BY id_alloggio');
$allStmt->bind_param('ii', $id, $alt);
$allStmt->execute();
$allRes = $allStmt->get_result();
?>
<div class="container text-white">
  <a href="vacanze_view.php?id=<?= $id ?>" class="btn btn-outline-light mb-3">← Indietro</a>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="vacanze.php">Vacanze</a></li>
        <li class="breadcrumb-item"><a href="vacanze_view.php?id=<?= $id ?>"><?= htmlspecialchars($viaggio['titolo']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($alt_desc) ?></li>
    </ol>
  </nav>
  <div class="d-flex justify-content-between mb-3">
      <h4 class="m-0">Tratte - <?= htmlspecialchars($alt_desc) ?> <a href="#" class="text-white ms-2" data-bs-toggle="modal" data-bs-target="#altEditModal"><i class="bi bi-pencil"></i></a></h4>
      <a class="btn btn-sm btn-outline-light" href="vacanze_tratte_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>">Aggiungi</a>
  </div>

  <?php if ($trRes->num_rows === 0): ?>
    <p class="text-muted">Nessuna tratta.</p>
  <?php else: ?>
    <div class="list-group">
        <?php while ($row = $trRes->fetch_assoc()): ?>
          <a href="vacanze_tratte_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>&id_tratta=<?= (int)$row['id_tratta'] ?>" class="list-group-item list-group-item-action bg-dark text-white">
          <div class="d-flex justify-content-between">
            <div>
              <div><?= htmlspecialchars($row['descrizione'] ?: $row['tipo_tratta']) ?></div>
              <div class="small text-muted"><?= ucfirst($row['tipo_tratta']) ?></div>
            </div>
            <div>€<?= number_format($row['totale'], 2, ',', '.') ?> <i class="bi bi-pencil ms-2"></i></div>
          </div>
        </a>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between mb-3 mt-4">
      <h4 class="m-0">Alloggi</h4>
      <a class="btn btn-sm btn-outline-light" href="vacanze_alloggi_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>">Aggiungi</a>
  </div>

  <?php if ($allRes->num_rows === 0): ?>
    <p class="text-muted">Nessun alloggio.</p>
  <?php else: ?>
    <div class="list-group">
      <?php while ($row = $allRes->fetch_assoc()): ?>
        <a href="vacanze_alloggi_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>&id_alloggio=<?= (int)$row['id_alloggio'] ?>" class="list-group-item list-group-item-action bg-dark text-white">
          <div class="d-flex justify-content-between">
            <span><?= htmlspecialchars($row['nome_alloggio'] ?: 'Alloggio') ?></span>
            <span>€<?= number_format($row['totale'], 2, ',', '.') ?> <i class="bi bi-pencil"></i></span>
          </div>
        </a>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>

  <div class="modal fade" id="altEditModal" tabindex="-1">
    <div class="modal-dialog">
      <form class="modal-content" id="altEditForm">
        <div class="modal-header">
          <h5 class="modal-title">Modifica alternativa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Descrizione breve</label>
            <input type="text" name="breve_descrizione" class="form-control" value="<?= htmlspecialchars($alt_desc) ?>" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary">Salva</button>
        </div>
      </form>
    </div>
  </div>

  <script>const altId = <?= $alt ?>;</script>
  <script src="js/vacanze_tratte.js"></script>
</div>
<?php include 'includes/footer.php'; ?>
