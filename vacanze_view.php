<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$canUpdateViaggio = has_permission($conn, 'table:viaggi', 'update');
$canAddAlternativa = has_permission($conn, 'ajax:add_viaggi_alternativa', 'insert');
$canAddDocumento = has_permission($conn, 'ajax:add_viaggi_documento', 'insert');
$canAddChecklist = has_permission($conn, 'ajax:add_viaggi_checklist', 'insert');

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT * FROM viaggi WHERE id_viaggio=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$viaggio = $res->fetch_assoc();
if (!$viaggio) {
    echo '<p class="text-danger">Viaggio non trovato</p>';
    include 'includes/footer.php';
    exit;
}


$totStmt = $conn->prepare('SELECT * FROM v_totali_alternative WHERE id_viaggio=?');
$totStmt->bind_param('i', $id);
$totStmt->execute();
$totRes = $totStmt->get_result();
$totali = [];
while ($row = $totRes->fetch_assoc()) { $totali[$row['id_viaggio_alternativa']] = $row; }


// Checklist
$chkStmt = $conn->prepare('SELECT id_checklist, voce, completata FROM viaggi_checklist WHERE id_viaggio=? ORDER BY id_checklist');
$chkStmt->bind_param('i', $id);
$chkStmt->execute();
$chkRes = $chkStmt->get_result();
$chkRows = $chkRes->fetch_all(MYSQLI_ASSOC);
$totChecklist = count($chkRows);
$totChecklistDone = 0;
foreach ($chkRows as $r) {
    if (!empty($r['completata'])) { $totChecklistDone++; }
}

// Feedback
$fbStmt = $conn->prepare('SELECT vf.id_feedback, vf.voto, vf.commento, u.username FROM viaggi_feedback vf LEFT JOIN utenti u ON vf.id_utente=u.id WHERE vf.id_viaggio=? ORDER BY vf.id_feedback');
$fbStmt->bind_param('i', $id);
$fbStmt->execute();
$fbRes = $fbStmt->get_result();

// Documenti
$docStmt = $conn->prepare('SELECT vc.id_caricamento, oc.nome_file FROM viaggi2caricamenti vc JOIN ocr_caricamenti oc ON vc.id_caricamento=oc.id_caricamento WHERE vc.id_viaggio=? ORDER BY vc.id_caricamento');
$docStmt->bind_param('i', $id);
$docStmt->execute();
$docRes = $docStmt->get_result();
?>
<div class="container text-white">
  <a href="vacanze.php" class="btn btn-outline-light mb-3">← Indietro</a>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="vacanze.php">Vacanze</a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($viaggio['titolo']) ?></li>
    </ol>
  </nav>
  <h4 class="mb-3"><?= htmlspecialchars($viaggio['titolo']) ?>
    <?php if ($canUpdateViaggio): ?>
      <a href="vacanze_modifica.php?id=<?= $id ?>" class="text-white ms-2"><i class="bi bi-pencil"></i></a>
    <?php endif; ?>
  </h4>

  <div class="mb-4">
    <div class="d-flex justify-content-between mb-3 align-items-center">
      <h5 class="m-0">Alternative</h5>
      <?php if ($canAddAlternativa): ?>
        <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#altModal">Aggiungi</button>
      <?php endif; ?>
    </div>
    <?php if (empty($totali)): ?>
      <p class="text-muted">Nessuna alternativa.</p>
    <?php else: ?>
      <div class="row row-cols-1 row-cols-md-2 g-3">
        <?php foreach ($totali as $t): ?>
          <div class="col">
            <a href="vacanze_tratte.php?id=<?= $id ?>&alt=<?= (int)$t['id_viaggio_alternativa'] ?>" class="text-decoration-none text-white">
              <div class="p-2 border rounded h-100">
                <h6 class="mb-1"><?= htmlspecialchars($t['breve_descrizione']) ?></h6>
                <div class="small">Trasporti: €<?= number_format($t['totale_trasporti'], 2, ',', '.') ?></div>
                <div class="small">Alloggi: €<?= number_format($t['totale_alloggi'], 2, ',', '.') ?></div>
                <div class="fw-bold">Totale: €<?= number_format($t['totale_viaggio'], 2, ',', '.') ?></div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($canAddAlternativa): ?>
  <div class="modal fade" id="altModal" tabindex="-1">
    <div class="modal-dialog">
      <form class="modal-content" id="altForm">
        <div class="modal-header">
          <h5 class="modal-title">Nuova alternativa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Descrizione breve</label>
            <input type="text" name="breve_descrizione" class="form-control" required>
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

  <div class="mb-4">
    <h5 class="mb-3">Checklist</h5>
    <?php if ($totChecklist === 0): ?>
      <?php if ($canAddChecklist): ?>
        <a href="vacanze_checklist.php?id=<?= $id ?>" class="btn btn-sm btn-outline-light">Nuova checklist</a>
      <?php else: ?>
        <p class="text-muted">Nessuna checklist.</p>
      <?php endif; ?>
    <?php else: ?>
      <a href="vacanze_checklist.php?id=<?= $id ?>" class="text-decoration-none text-white">
        <div class="p-2 border rounded">
          <div class="small">Voci totali: <?= $totChecklist ?></div>
          <div class="small">Completate: <?= $totChecklistDone ?></div>
        </div>
      </a>
    <?php endif; ?>
  </div>

  <div class="mb-4">
    <h5 class="mb-3">Feedback</h5>
    <?php if ($fbRes->num_rows === 0): ?>
      <p class="text-muted">Nessun feedback.</p>
    <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php while ($row = $fbRes->fetch_assoc()): ?>
          <li class="list-group-item bg-dark text-white">
            <div><strong><?= htmlspecialchars($row['username'] ?? 'Anonimo') ?></strong> - voto <?= (int)$row['voto'] ?></div>
            <?php if ($row['commento']): ?><div class="small"><?= htmlspecialchars($row['commento']) ?></div><?php endif; ?>
          </li>
        <?php endwhile; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="mb-4">
    <div class="d-flex justify-content-between mb-3 align-items-center">
      <h5 class="m-0">Documenti</h5>
      <?php if ($canAddDocumento): ?>
        <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#docModal">Aggiungi</button>
      <?php endif; ?>
    </div>
    <?php if ($docRes->num_rows === 0): ?>
      <p class="text-muted">Nessun documento allegato.</p>
    <?php else: ?>
      <div class="row row-cols-1 row-cols-md-2 g-3">
        <?php while ($row = $docRes->fetch_assoc()): ?>
          <div class="col">
            <div class="card bg-dark text-white h-100">
              <div class="card-body d-flex flex-column">
                <p class="card-text flex-grow-1 mb-2"><?= htmlspecialchars($row['nome_file']) ?></p>
                <a href="files/vacanze/<?= urlencode($row['nome_file']) ?>" class="btn btn-sm btn-outline-light mt-auto" target="_blank">Apri</a>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($canAddDocumento): ?>
  <div class="modal fade" id="docModal" tabindex="-1">
    <div class="modal-dialog">
      <form class="modal-content" id="docForm" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Carica documento</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <input type="file" name="file" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary">Carica</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <script>const viaggioId = <?= $id ?>;</script>
  <script src="js/vacanze_view.js"></script>
</div>
<?php include 'includes/footer.php'; ?>
