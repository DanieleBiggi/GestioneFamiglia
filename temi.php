<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/permissions.php';
require_once 'includes/render_tema.php';
if (!has_permission($conn, 'page:temi.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
include 'includes/header.php';

$stmt = $conn->prepare('SELECT * FROM temi');
$stmt->execute();
$res = $stmt->get_result();
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Temi</h4><a href="tema_dettaglio.php" class="btn btn-outline-light btn-sm">Aggiungi nuovo</a>
</div>
<div class="mb-3">
  <input type="text" id="search" class="form-control bg-dark text-white border-secondary" placeholder="Cerca">
</div>
<div id="temiList">
<?php while ($row = $res->fetch_assoc()): ?>
  <?php render_tema($row); ?>
<?php endwhile; ?>
</div>
<script src="js/temi.js"></script>
<?php include 'includes/footer.php'; ?>
