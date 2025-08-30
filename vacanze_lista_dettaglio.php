<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare('SELECT v.*, l.nome AS luogo_nome, l.lat, l.lng FROM viaggi v LEFT JOIN viaggi_luoghi l ON v.id_luogo=l.id_luogo WHERE v.id_viaggio=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$viaggio = $res->fetch_assoc();
if (!$viaggio) {
    echo '<p class="text-danger">Viaggio non trovato</p>';
    include 'includes/footer.php';
    exit;
}

$fotoStmt = $conn->prepare('SELECT photo_reference FROM viaggi_luogo_foto WHERE id_luogo=?');
$fotoStmt->bind_param('i', $viaggio['id_luogo']);
$fotoStmt->execute();
$fotoRes = $fotoStmt->get_result();

$altStmt = $conn->prepare('SELECT * FROM v_totali_alternative WHERE id_viaggio=?');
$altStmt->bind_param('i', $id);
$altStmt->execute();
$altRes = $altStmt->get_result();

$fbStmt = $conn->prepare('SELECT AVG(voto) AS media, COUNT(*) AS num FROM viaggi_feedback WHERE id_viaggio=?');
$fbStmt->bind_param('i', $id);
$fbStmt->execute();
$fbStats = $fbStmt->get_result()->fetch_assoc();

$chkStmt = $conn->prepare('SELECT voce, completata FROM viaggi_checklist WHERE id_viaggio=? ORDER BY id_checklist');
$chkStmt->bind_param('i', $id);
$chkStmt->execute();
$chkRes = $chkStmt->get_result();

$docStmt = $conn->prepare('SELECT oc.nome_file FROM viaggi2caricamenti vc JOIN ocr_caricamenti oc ON vc.id_caricamento=oc.id_caricamento WHERE vc.id_viaggio=? ORDER BY vc.id_caricamento');
$docStmt->bind_param('i', $id);
$docStmt->execute();
$docRes = $docStmt->get_result();
?>
<div class="container my-3">
  <a href="vacanze_lista.php" class="btn btn-outline-secondary mb-3">&larr; Indietro</a>
  <h4 class="mb-2"><?= htmlspecialchars($viaggio['titolo']) ?></h4>
  <?php if ($viaggio['breve_descrizione']): ?>
  <p class="text-muted mb-3">
    <?= htmlspecialchars($viaggio['breve_descrizione']) ?>
  </p>
  <?php endif; ?>

  <?php if ($fotoRes->num_rows > 0): ?>
  <div id="fotoCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
    <div class="carousel-inner">
      <?php $i=0; while($f = $fotoRes->fetch_assoc()): $url = 'https://maps.googleapis.com/maps/api/place/photo?maxwidth=800&photoreference=' . urlencode($f['photo_reference']) . '&key=' . ($config['GOOGLE_PLACES_FOTO_API'] ?? ''); ?>
      <div class="carousel-item <?= $i===0?'active':'' ?>">
        <img src="<?= htmlspecialchars($url) ?>" class="d-block w-100" alt="" style="height:522px;object-fit:cover;">
      </div>
      <?php $i++; endwhile; ?>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#fotoCarousel" data-bs-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#fotoCarousel" data-bs-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Next</span>
    </button>
  </div>
  <?php endif; ?>

  <p><a href="vacanze_lista_dettaglio_mappa.php?id=<?= $id ?>" class="text-decoration-none"><i class="bi bi-geo-alt"></i> Vedi mappa</a></p>

  <?php if ($fbStats['num'] > 0): ?>
  <p><a href="vacanze_lista_dettaglio_feedback.php?id=<?= $id ?>" class="text-decoration-none">Voto medio <?= number_format($fbStats['media'],1,',','.') ?> (<?= (int)$fbStats['num'] ?> recensioni)</a></p>
  <?php else: ?>
  <p><a href="vacanze_lista_dettaglio_feedback.php?id=<?= $id ?>" class="text-decoration-none">Nessuna recensione</a></p>
  <?php endif; ?>

  <div class="mb-4">
    <h5 class="mb-3">Alternative</h5>
    <?php if ($altRes->num_rows === 0): ?>
      <p class="text-muted">Nessuna alternativa.</p>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 g-3">
      <?php while($alt = $altRes->fetch_assoc()): ?>
      <div class="col">
        <a href="#" data-alt="<?= (int)$alt['id_viaggio_alternativa'] ?>" class="text-decoration-none text-dark alt-card">
          <div class="card">
            <div class="card-body d-flex justify-content-between">
              <div><?= htmlspecialchars($alt['breve_descrizione']) ?></div>
              <div class="text-end"><small class="text-muted">a partire da</small><br><span class="fw-bold">€<?= number_format($alt['totale_viaggio'],2,',','.') ?></span></div>
            </div>
          </div>
        </a>
      </div>
      <?php endwhile; ?>
    </div>
    <?php endif; ?>
  </div>

  <div id="altDettagli" class="mb-4"></div>

  <ul class="nav nav-tabs nav-fill" id="detailTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="checklist-tab" data-bs-toggle="tab" data-bs-target="#checklist" type="button" role="tab">Checklist</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="documenti-tab" data-bs-toggle="tab" data-bs-target="#documenti" type="button" role="tab">Documenti</button>
    </li>
  </ul>
  <div class="tab-content border border-top-0 p-3" id="detailTabsContent">
    <div class="tab-pane fade show active" id="checklist" role="tabpanel" aria-labelledby="checklist-tab">
      <?php if ($chkRes->num_rows === 0): ?>
        <p class="text-muted">Nessuna voce.</p>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php while($chk = $chkRes->fetch_assoc()): ?>
          <li class="list-group-item">
            <input class="form-check-input me-2" type="checkbox" disabled <?= $chk['completata']?'checked':'' ?>>
            <?= htmlspecialchars($chk['voce']) ?>
          </li>
          <?php endwhile; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="tab-pane fade" id="documenti" role="tabpanel" aria-labelledby="documenti-tab">
      <?php if ($docRes->num_rows === 0): ?>
        <p class="text-muted">Nessun documento.</p>
      <?php else: ?>
        <ul class="list-group list-group-flush">
        <?php while($doc = $docRes->fetch_assoc()): ?>
          <li class="list-group-item"><a href="files/vacanze/<?= urlencode($doc['nome_file']) ?>" target="_blank"><?= htmlspecialchars($doc['nome_file']) ?></a></li>
        <?php endwhile; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
  <script>const viaggioId = <?= $id ?>;</script>
  <script src="js/vacanze_lista_dettaglio.js"></script>
</div>
<?php include 'includes/footer.php'; ?>

