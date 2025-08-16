<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/permissions.php';
require_once 'includes/render_turno_tipo.php';
if (!has_permission($conn, 'page:turni_tipi.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
include 'includes/header.php';

$stmt = $conn->prepare('SELECT * FROM turni_tipi ORDER BY descrizione');
$stmt->execute();
$res = $stmt->get_result();
$canInsert = has_permission($conn, 'table:turni_tipi', 'insert');
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Tipi turno</h4>
  <?php if ($canInsert): ?>
  <a href="turno_tipo_dettaglio.php" class="btn btn-outline-light btn-sm">Aggiungi nuovo</a>
  <?php endif; ?>
</div>
<div class="d-flex mb-3 align-items-center">
  <input type="text" id="search" class="form-control bg-dark text-white border-secondary me-2" placeholder="Cerca">
  <div class="form-check form-switch text-nowrap">
    <input class="form-check-input" type="checkbox" id="showInactive">
    <label class="form-check-label" for="showInactive">Mostra non attivi</label>
  </div>
</div>
<div id="tipiList">
<?php while ($row = $res->fetch_assoc()): ?>
  <?php render_turno_tipo($row); ?>
<?php endwhile; ?>
</div>
<script src="js/turni_tipi.js"></script>
<?php include 'includes/footer.php'; ?>
