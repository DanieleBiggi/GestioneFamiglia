<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

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
while ($row = $totRes->fetch_assoc()) { $totali[$row['gruppo_alternativa']] = $row; }

// Checklist
$chkStmt = $conn->prepare('SELECT id_checklist, voce, completata FROM viaggi_checklist WHERE id_viaggio=? ORDER BY id_checklist');
$chkStmt->bind_param('i', $id);
$chkStmt->execute();
$chkRes = $chkStmt->get_result();

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
  <h4 class="mb-3"><?= htmlspecialchars($viaggio['titolo']) ?>
    <a href="vacanze_modifica.php?id=<?= $id ?>" class="text-white ms-2"><i class="bi bi-pencil"></i></a>
  </h4>
  <ul class="nav nav-tabs" id="vacTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabAlt">Alternative</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabCheck">Checklist</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabFeed">Feedback</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabDoc">Documenti</button></li>
  </ul>
  <div class="tab-content border border-top-0 p-3">
    <div class="tab-pane fade show active" id="tabAlt">
      <div class="d-flex justify-content-between mb-3">
        <h6 class="m-0">Alternative</h6>
        <a href="table_manager.php?table=viaggi_tratte&id_viaggio=<?= $id ?>" class="btn btn-sm btn-outline-light">Aggiungi</a>
      </div>
      <?php if (empty($totali)): ?>
        <p class="text-muted">Nessuna alternativa.</p>
      <?php else: ?>
        <?php foreach ($totali as $grp => $t): ?>
          <a href="table_manager.php?table=viaggi_tratte&search=<?= urlencode($grp) ?>&id_viaggio=<?= $id ?>" class="text-decoration-none text-white">
            <div class="mb-3 p-2 border rounded">
              <h6 class="mb-1"><?= htmlspecialchars($grp) ?></h6>
              <div class="small">Trasporti: €<?= number_format($t['totale_trasporti'], 2, ',', '.') ?></div>
              <div class="small">Alloggi: €<?= number_format($t['totale_alloggi'], 2, ',', '.') ?></div>
              <div class="fw-bold">Totale: €<?= number_format($t['totale_viaggio'], 2, ',', '.') ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="tab-pane fade" id="tabCheck">
      <div class="d-flex justify-content-between mb-3">
        <h6 class="m-0">Checklist</h6>
        <a href="table_manager.php?table=viaggi_checklist&id_viaggio=<?= $id ?>" class="btn btn-sm btn-outline-light">Aggiungi</a>
      </div>
      <?php if ($chkRes->num_rows === 0): ?>
        <p class="text-muted">Nessuna voce.</p>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php while ($row = $chkRes->fetch_assoc()): ?>
            <li class="list-group-item bg-dark text-white p-0">
              <a href="table_manager.php?table=viaggi_checklist&search=<?= (int)$row['id_checklist'] ?>&id_viaggio=<?= $id ?>" class="d-flex justify-content-between align-items-center text-white text-decoration-none p-2">
                <span><?= htmlspecialchars($row['voce']) ?></span>
                <?php if ($row['completata']): ?><i class="bi bi-check2"></i><?php endif; ?>
              </a>
            </li>
          <?php endwhile; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="tab-pane fade" id="tabFeed">
      <div class="d-flex justify-content-between mb-3">
        <h6 class="m-0">Feedback</h6>
        <a href="table_manager.php?table=viaggi_feedback&id_viaggio=<?= $id ?>" class="btn btn-sm btn-outline-light">Aggiungi</a>
      </div>
      <?php if ($fbRes->num_rows === 0): ?>
        <p class="text-muted">Nessun feedback.</p>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php while ($row = $fbRes->fetch_assoc()): ?>
            <li class="list-group-item bg-dark text-white p-0">
              <a href="table_manager.php?table=viaggi_feedback&search=<?= (int)$row['id_feedback'] ?>&id_viaggio=<?= $id ?>" class="text-white text-decoration-none d-block p-2">
                <div><strong><?= htmlspecialchars($row['username'] ?? 'Anonimo') ?></strong> - voto <?= (int)$row['voto'] ?></div>
                <?php if ($row['commento']): ?><div class="small"><?= htmlspecialchars($row['commento']) ?></div><?php endif; ?>
              </a>
            </li>
          <?php endwhile; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="tab-pane fade" id="tabDoc">
      <div class="d-flex justify-content-between mb-3">
        <h6 class="m-0">Documenti</h6>
        <a href="table_manager.php?table=viaggi2caricamenti&id_viaggio=<?= $id ?>" class="btn btn-sm btn-outline-light">Aggiungi</a>
      </div>
      <?php if ($docRes->num_rows === 0): ?>
        <p class="text-muted">Nessun documento allegato.</p>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php while ($row = $docRes->fetch_assoc()): ?>
            <li class="list-group-item bg-dark text-white p-0">
              <a href="table_manager.php?table=viaggi2caricamenti&search=<?= (int)$row['id_caricamento'] ?>&id_viaggio=<?= $id ?>" class="text-white text-decoration-none d-block p-2">
                <?= htmlspecialchars($row['nome_file']) ?>
              </a>
            </li>
          <?php endwhile; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
