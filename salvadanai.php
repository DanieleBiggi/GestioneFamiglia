<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/permissions.php';
require_once 'includes/render_salvadanaio.php';
if (!has_permission($conn, 'page:salvadanai.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
include 'includes/header.php';

$stmt = $conn->prepare('SELECT * FROM salvadanai');
$stmt->execute();
$res = $stmt->get_result();
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Salvadanai</h4>
  <?php if (has_permission($conn, 'table:salvadanai', 'insert')): ?>
  <a href="salvadanaio_dettaglio.php" class="btn btn-outline-light btn-sm">Aggiungi nuovo</a>
  <?php endif; ?>
</div>
<div class="mb-3 d-flex align-items-center">
  <input type="text" id="search" class="form-control bg-dark text-white border-secondary me-3" placeholder="Cerca">
  <div class="form-check">
    <input class="form-check-input" type="checkbox" id="showExpired">
    <label class="form-check-label" for="showExpired">Mostra scaduti</label>
  </div>
</div>
<div id="salvadanaiList">
<?php while ($row = $res->fetch_assoc()): ?>
  <?php render_salvadanaio($row); ?>
<?php endwhile; ?>
</div>
<script src="js/salvadanai.js"></script>
<?php include 'includes/footer.php'; ?>
