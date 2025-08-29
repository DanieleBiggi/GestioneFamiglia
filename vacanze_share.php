<?php
include 'includes/db.php';
include 'includes/header.php';

$token = $_GET['t'] ?? '';
$stmt = $conn->prepare('SELECT * FROM viaggi WHERE token_condivisione=? AND visibilita="shared"');
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
$viaggio = $res->fetch_assoc();
if (!$viaggio) {
    echo '<p class="text-danger">Viaggio non trovato</p>';
    include 'includes/footer.php';
    exit;
}
$id = $viaggio['id_viaggio'];
$totStmt = $conn->prepare('SELECT * FROM v_totali_alternative WHERE id_viaggio=?');
$totStmt->bind_param('i', $id);
$totStmt->execute();
$totRes = $totStmt->get_result();
$totali = [];
while ($row = $totRes->fetch_assoc()) { $totali[$row['gruppo_alternativa']] = $row; }
?>
<div class="container text-white">
  <h4 class="mb-3"><?= htmlspecialchars($viaggio['titolo']) ?></h4>
  <?php foreach ($totali as $grp => $t): ?>
    <div class="mb-3">
      <h6><?= htmlspecialchars($grp) ?></h6>
      <div class="small">Trasporti: €<?= number_format($t['totale_trasporti'], 2, ',', '.') ?></div>
      <div class="small">Alloggi: €<?= number_format($t['totale_alloggi'], 2, ',', '.') ?></div>
      <div class="fw-bold">Totale: €<?= number_format($t['totale_viaggio'], 2, ',', '.') ?></div>
    </div>
  <?php endforeach; ?>
</div>
<?php include 'includes/footer.php'; ?>
