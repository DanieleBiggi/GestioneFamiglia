<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:eventi_google_rules.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
require_once 'includes/render_eventi_google_rule.php';

$stmt = $conn->prepare('SELECT * FROM eventi_google_rules');
$stmt->execute();
$res = $stmt->get_result();
include 'includes/header.php';
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Regole Google Eventi</h4>
  <?php if (has_permission($conn, 'table:eventi_google_rules', 'insert')): ?>
  <a href="eventi_google_rule_dettaglio.php" class="btn btn-outline-light btn-sm">Aggiungi nuovo</a>
  <?php endif; ?>
</div>
<div class="d-flex mb-3">
  <input type="text" id="search" class="form-control bg-dark text-white border-secondary me-2" placeholder="Cerca">
  <div class="form-check">
    <input type="checkbox" class="form-check-input" id="onlyActive" checked>
    <label class="form-check-label" for="onlyActive">Solo attive</label>
  </div>
</div>
<div id="rulesList">
<?php while ($row = $res->fetch_assoc()): ?>
  <?php render_eventi_google_rule($row); ?>
<?php endwhile; ?>
</div>
<script src="js/eventi_google_rules.js"></script>
<?php include 'includes/footer.php'; ?>
