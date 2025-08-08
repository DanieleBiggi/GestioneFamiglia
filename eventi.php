<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:eventi.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
require_once 'includes/render_evento.php';
include 'includes/header.php';

$stmt = $conn->prepare("SELECT e.*, t.tipo_evento, t.colore FROM eventi e LEFT JOIN eventi_tipi_eventi t ON e.id_tipo_evento = t.id ORDER BY e.data_evento DESC, e.ora_evento DESC");
$stmt->execute();
$res = $stmt->get_result();
$canInsert = has_permission($conn, 'table:eventi', 'insert');
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Eventi</h4>
  <?php if ($canInsert): ?>
  <a href="eventi_dettaglio.php" class="btn btn-outline-light btn-sm">Aggiungi nuovo</a>
  <?php endif; ?>
</div>
<div class="d-flex mb-3 align-items-center">
  <input type="text" id="search" class="form-control bg-dark text-white border-secondary" placeholder="Cerca">
</div>
<div id="eventiList">
<?php while ($row = $res->fetch_assoc()): ?>
  <?php render_evento($row); ?>
<?php endwhile; ?>
</div>
<script src="js/eventi.js"></script>
<?php include 'includes/footer.php'; ?>
