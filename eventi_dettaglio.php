<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:eventi.php', 'view')) { http_response_code(403); exit('Accesso negato'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT e.*, t.tipo_evento, t.colore FROM eventi e LEFT JOIN eventi_tipi_eventi t ON e.id_tipo_evento = t.id WHERE e.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$evento = $res->fetch_assoc();
$stmt->close();

if (!$evento) {
    include 'includes/header.php';
    echo '<p class="text-danger">Record non trovato.</p>';
    include 'includes/footer.php';
    exit;
}

$invitati = [];
$stmtInv = $conn->prepare("SELECT i.nome, i.cognome FROM eventi_eventi2invitati e2i JOIN eventi_invitati i ON e2i.id_invitato = i.id WHERE e2i.id_evento = ? ORDER BY i.cognome, i.nome");
$stmtInv->bind_param('i', $id);
$stmtInv->execute();
$resInv = $stmtInv->get_result();
while ($row = $resInv->fetch_assoc()) { $invitati[] = $row; }
$stmtInv->close();

$cibi = [];
$stmtCibo = $conn->prepare("SELECT c.piatto, c.um, e2c.quantita FROM eventi_eventi2cibo e2c JOIN eventi_cibo c ON e2c.id_cibo = c.id WHERE e2c.id_evento = ? ORDER BY c.piatto");
$stmtCibo->bind_param('i', $id);
$stmtCibo->execute();
$resCibo = $stmtCibo->get_result();
while ($row = $resCibo->fetch_assoc()) { $cibi[] = $row; }
$stmtCibo->close();

include 'includes/header.php';
?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-3"><?= htmlspecialchars($evento['titolo'] ?? '') ?></h4>
  <?php if (!empty($evento['data_evento']) || !empty($evento['ora_evento'])): ?>
    <div class="mb-3"><?= htmlspecialchars(trim(($evento['data_evento'] ?? '') . ' ' . ($evento['ora_evento'] ?? ''))) ?></div>
  <?php endif; ?>
  <?php if (!empty($evento['descrizione'])): ?>
    <p><?= nl2br(htmlspecialchars($evento['descrizione'])) ?></p>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="d-flex align-items-center">
      <h5 class="mb-0 me-3">Invitati</h5>
      <?php if (count($invitati) > 3): ?>
        <button id="toggleInvitati" class="btn btn-link p-0">Mostra tutti</button>
      <?php endif; ?>
    </div>
  </div>
  <ul class="list-group list-group-flush bg-dark" id="invitatiList">
    <?php foreach ($invitati as $idx => $row): ?>
      <li class="list-group-item bg-dark text-white <?= $idx >= 3 ? 'd-none extra-row' : '' ?>">
        <?= htmlspecialchars($row['nome'] . ' ' . $row['cognome']) ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
    <div class="d-flex align-items-center">
      <h5 class="mb-0 me-3">Cibo</h5>
      <?php if (count($cibi) > 3): ?>
        <button id="toggleCibo" class="btn btn-link p-0">Mostra tutti</button>
      <?php endif; ?>
    </div>
  </div>
  <ul class="list-group list-group-flush bg-dark" id="ciboList">
    <?php foreach ($cibi as $idx => $row): ?>
      <li class="list-group-item bg-dark text-white <?= $idx >= 3 ? 'd-none extra-row' : '' ?>">
        <?= htmlspecialchars($row['piatto']) ?><?php if ($row['quantita'] !== null) echo ' - ' . htmlspecialchars($row['quantita']) . ' ' . htmlspecialchars($row['um']); ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<script>
document.getElementById('toggleInvitati')?.addEventListener('click', function(){
  document.querySelectorAll('#invitatiList .extra-row').forEach(el=>el.classList.toggle('d-none'));
  this.textContent = this.textContent === 'Mostra tutti' ? 'Mostra meno' : 'Mostra tutti';
});
document.getElementById('toggleCibo')?.addEventListener('click', function(){
  document.querySelectorAll('#ciboList .extra-row').forEach(el=>el.classList.toggle('d-none'));
  this.textContent = this.textContent === 'Mostra tutti' ? 'Mostra meno' : 'Mostra tutti';
});
</script>
<?php include 'includes/footer.php'; ?>
