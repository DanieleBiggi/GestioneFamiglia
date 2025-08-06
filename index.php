<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

require_once 'includes/render_movimento.php';

?>

<input type="text" id="search" class="form-control bg-dark text-white border-secondary mb-3" placeholder="Cerca nei movimenti">
<div id="searchResults"></div>

<?php

$sql = "SELECT id_movimento_revolut, started_date, amount, COALESCE(NULLIF(descrizione_extra, ''), description) AS descrizione, etichette FROM v_movimenti_revolut ORDER BY started_date DESC LIMIT 5";
$result = $conn->query($sql);

if ($result->num_rows > 0): ?>
  <div id="recentMovimenti" class="text-white">
    <?php while ($row = $result->fetch_assoc()):
        render_movimento($row);
    endwhile; ?>

  </div>

  <div class="text-center mt-3">
    <a href="tutti_movimenti.php" class="btn btn-outline-light btn-sm">Visualizza tutti</a>
  </div>
<?php else: ?>
  <p class="text-center text-muted">Nessun movimento presente.</p>
<?php endif; ?>

<script src="js/index.js"></script>
<?php include 'includes/footer.php'; ?>