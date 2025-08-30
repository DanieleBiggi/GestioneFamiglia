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
$tratte = $trRes->fetch_all(MYSQLI_ASSOC);

// Recupera alloggi
$allStmt = $conn->prepare('SELECT *, DATEDIFF(data_checkout, data_checkin) * COALESCE(costo_notte_eur,0) AS totale FROM viaggi_alloggi WHERE id_viaggio=? AND id_viaggio_alternativa=? ORDER BY id_alloggio');
$allStmt->bind_param('ii', $id, $alt);
$allStmt->execute();
$allRes = $allStmt->get_result();
$alloggi = $allRes->fetch_all(MYSQLI_ASSOC);

$canEditAlt = has_permission($conn, 'ajax:update_viaggi_alternativa', 'update');
$canInsertTratta = has_permission($conn, 'table:viaggi_tratte', 'insert');
$canUpdateTratta = has_permission($conn, 'table:viaggi_tratte', 'update');
$canInsertAlloggio = has_permission($conn, 'table:viaggi_alloggi', 'insert');
$canUpdateAlloggio = has_permission($conn, 'table:viaggi_alloggi', 'update');
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
      <h4 class="m-0">Tratte - <?= htmlspecialchars($alt_desc) ?>
        <?php if ($canEditAlt): ?>
          <a href="#" class="text-white ms-2" data-bs-toggle="modal" data-bs-target="#altEditModal"><i class="bi bi-pencil"></i></a>
        <?php endif; ?>
      </h4>
      <?php if ($canInsertTratta): ?>
      <a class="btn btn-sm btn-outline-light" href="vacanze_tratte_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>">Aggiungi</a>
      <?php endif; ?>
  </div>

  <?php if (empty($tratte)): ?>
    <p class="text-muted">Nessuna tratta.</p>
  <?php else: ?>
    <div class="list-group">
        <?php foreach ($tratte as $row): ?>
          <?php if ($canUpdateTratta): ?>
          <a href="vacanze_tratte_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>&id_tratta=<?= (int)$row['id_tratta'] ?>" class="list-group-item list-group-item-action bg-dark text-white">
          <?php else: ?>
          <div class="list-group-item bg-dark text-white">
          <?php endif; ?>
          <div class="d-flex justify-content-between">
            <div>
              <div><?= htmlspecialchars($row['descrizione'] ?: $row['tipo_tratta']) ?></div>
              <div class="small text-muted"><?= ucfirst($row['tipo_tratta']) ?></div>
            </div>
            <div>€<?= number_format($row['totale'], 2, ',', '.') ?>
              <?php if ($canUpdateTratta): ?><i class="bi bi-pencil ms-2"></i><?php endif; ?>
              <?php if ($canInsertTratta): ?><i class="bi bi-files ms-2 text-info duplicate" data-href="vacanze_tratte_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>&id_tratta=<?= (int)$row['id_tratta'] ?>&duplica=1"></i><?php endif; ?>
            </div>
          </div>
          <?php if ($canUpdateTratta): ?>
          </a>
          <?php else: ?>
          </div>
          <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between mb-3 mt-4">
      <h4 class="m-0">Alloggi</h4>
      <?php if ($canInsertAlloggio): ?>
      <a class="btn btn-sm btn-outline-light" href="vacanze_alloggi_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>">Aggiungi</a>
      <?php endif; ?>
  </div>

  <?php if (empty($alloggi)): ?>
    <p class="text-muted">Nessun alloggio.</p>
  <?php else: ?>
    <div class="list-group">
      <?php foreach ($alloggi as $row): ?>
        <?php if ($canUpdateAlloggio): ?>
        <a href="vacanze_alloggi_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>&id_alloggio=<?= (int)$row['id_alloggio'] ?>" class="list-group-item list-group-item-action bg-dark text-white">
        <?php else: ?>
        <div class="list-group-item bg-dark text-white">
        <?php endif; ?>
          <div class="d-flex justify-content-between">
            <span><?= htmlspecialchars($row['nome_alloggio'] ?: 'Alloggio') ?></span>
            <span>€<?= number_format($row['totale'], 2, ',', '.') ?>
              <?php if ($canUpdateAlloggio): ?><i class="bi bi-pencil"></i><?php endif; ?>
              <?php if ($canInsertAlloggio): ?><i class="bi bi-files ms-2 text-info duplicate" data-href="vacanze_alloggi_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>&id_alloggio=<?= (int)$row['id_alloggio'] ?>&duplica=1"></i><?php endif; ?>
            </span>
          </div>
        <?php if ($canUpdateAlloggio): ?>
        </a>
        <?php else: ?>
        </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h4 class="mb-3 mt-4">Mappa</h4>
  <div id="map" style="height:500px"></div>

  <?php if ($canEditAlt): ?>
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
  <?php endif; ?>

  <script>
    const altId = <?= $alt ?>;
    const alloggi = <?= json_encode($alloggi) ?>;
    const tratte = <?= json_encode($tratte) ?>;
  </script>
  <script src="js/vacanze_tratte.js"></script>
  <script src="https://maps.googleapis.com/maps/api/js?key=<?= $config['GOOGLE_MAPS_API'] ?? '' ?>&callback=initMap&loading=async" async defer></script>
</div>
<?php include 'includes/footer.php'; ?>
