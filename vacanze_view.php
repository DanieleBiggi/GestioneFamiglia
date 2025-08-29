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
      <?php if (empty($totali)): ?>
        <p class="text-muted">Nessuna alternativa.</p>
      <?php else: ?>
        <?php foreach ($totali as $grp => $t): ?>
          <div class="mb-3">
            <h6><?= htmlspecialchars($grp) ?></h6>
            <div class="small">Trasporti: €<?= number_format($t['totale_trasporti'], 2, ',', '.') ?></div>
            <div class="small">Alloggi: €<?= number_format($t['totale_alloggi'], 2, ',', '.') ?></div>
            <div class="fw-bold">Totale: €<?= number_format($t['totale_viaggio'], 2, ',', '.') ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="tab-pane fade" id="tabCheck">
      <p class="text-muted">Checklist in arrivo.</p>
    </div>
    <div class="tab-pane fade" id="tabFeed">
      <p class="text-muted">Feedback non disponibili.</p>
    </div>
    <div class="tab-pane fade" id="tabDoc">
      <p class="text-muted">Nessun documento allegato.</p>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
