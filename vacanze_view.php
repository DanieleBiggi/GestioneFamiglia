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
while ($row = $totRes->fetch_assoc()) { $totali[$row['id_viaggio_alternativa']] = $row; }

// Tratte per dettaglio tipo_tratta
$trStmt = $conn->prepare('SELECT id_viaggio_alternativa, tipo_tratta, origine_testo, destinazione_testo FROM viaggi_tratte WHERE id_viaggio=? ORDER BY id_viaggio_alternativa, id_tratta');
$trStmt->bind_param('i', $id);
$trStmt->execute();
$trRes = $trStmt->get_result();
$tratte = [];
while ($row = $trRes->fetch_assoc()) {
    $tratte[$row['id_viaggio_alternativa']][] = $row;
}

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
    <a href="vacanze_modifica.php?id=<?= $id ?>" class="text-white ms-2"><i class="bi bi-pencil"></i></a>
  </h4>

  <div class="mb-4">
    <h5>Alternative</h5>
    <?php if (empty($totali)): ?>
      <p class="text-muted">Nessuna alternativa.</p>
    <?php else: ?>
      <div class="row row-cols-1 row-cols-md-2 g-3">
        <?php foreach ($totali as $t): ?>
          <div class="col">
            <a href="vacanze_tratte.php?id=<?= $id ?>&alt=<?= (int)$t['id_viaggio_alternativa'] ?>" class="text-decoration-none text-white">
              <div class="p-2 border rounded h-100">
                <h6 class="mb-1"><?= htmlspecialchars($t['breve_descrizione']) ?></h6>
                <?php foreach (($tratte[$t['id_viaggio_alternativa']] ?? []) as $tr): ?>
                  <?php if ($tr['origine_testo'] || $tr['destinazione_testo']): ?>
                    <div class="small text-muted">
                      <?= htmlspecialchars($tr['origine_testo'] ?? '') ?> → <?= htmlspecialchars($tr['destinazione_testo'] ?? '') ?>
                    </div>
                  <?php endif; ?>
                <?php endforeach; ?>
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

  <div class="mb-4">
    <div class="d-flex justify-content-between mb-3">
      <h5 class="m-0">Checklist</h5>
      <a href="table_manager.php?table=viaggi_checklist&id_viaggio=<?= $id ?>" class="btn btn-sm btn-outline-light">Aggiungi</a>
    </div>
    <?php if ($totChecklist === 0): ?>
      <p class="text-muted">Nessuna voce.</p>
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
    <div class="d-flex justify-content-between mb-3">
      <h5 class="m-0">Feedback</h5>
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

  <div class="mb-4">
    <div class="d-flex justify-content-between mb-3">
      <h5 class="m-0">Documenti</h5>
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
<?php include 'includes/footer.php'; ?>
