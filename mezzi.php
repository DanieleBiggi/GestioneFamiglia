<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/permissions.php';
require_once 'includes/render_mezzo.php';
if (!has_permission($conn, 'page:mezzi.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
include 'includes/header.php';

$idUtente = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM mezzi WHERE id_famiglia = ?");
$stmt->bind_param('i', $idFamiglia);
$stmt->execute();
$res = $stmt->get_result();
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Mezzi</h4>
  <?php if (has_permission($conn, 'table:mezzi', 'insert')): ?>
  <a href="mezzo_dettaglio.php" class="btn btn-outline-light btn-sm">Aggiungi nuovo</a>
  <?php endif; ?>
</div>
<div class="d-flex mb-3 align-items-center">
  <input type="text" id="search" class="form-control bg-dark text-white border-secondary me-2" placeholder="Cerca">
  <div class="form-check form-switch text-nowrap">
    <input class="form-check-input" type="checkbox" id="showInactive">
    <label class="form-check-label" for="showInactive">Mostra non attivi</label>
  </div>
</div>
<div id="mezziList">
<?php while ($row = $res->fetch_assoc()): ?>
  <?php render_mezzo($row); ?>
<?php endwhile; ?>
</div>
<script src="js/mezzi.js"></script>
<?php include 'includes/footer.php'; ?>
