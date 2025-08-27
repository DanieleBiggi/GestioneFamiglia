<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/render_query.php';
include 'includes/header.php';

$stmt = $conn->prepare('SELECT * FROM dati_remoti');
$stmt->execute();
$res = $stmt->get_result();
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Query</h4><a href="query_dettaglio.php" class="btn btn-outline-light btn-sm">Aggiungi nuovo</a>
</div>
<div class="d-flex mb-3 align-items-center">
  <input type="text" id="search" class="form-control bg-dark text-white border-secondary me-2" placeholder="Cerca">
  <div class="form-check form-switch text-nowrap">
    <input class="form-check-input" type="checkbox" id="showArchived">
    <label class="form-check-label" for="showArchived">Mostra archiviati</label>
  </div>
</div>
<div id="queryList">
<?php while ($row = $res->fetch_assoc()): ?>
  <?php render_query($row); ?>
<?php endwhile; ?>
</div>
<script src="js/query.js"></script>
<?php include 'includes/footer.php'; ?>
