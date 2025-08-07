<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/render_password.php';
include 'includes/header.php';

$idUtente = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$stmt = $conn->prepare("SELECT g.* FROM gestione_account_password g JOIN utenti2famiglie u2f ON g.id_famiglia = u2f.id_famiglia WHERE u2f.id_utente = ? AND g.id_famiglia = ?");
$stmt->bind_param('ii', $idUtente, $idFamiglia);
$stmt->execute();
$res = $stmt->get_result();
?>

<div class="d-flex mb-3 align-items-center">
  <input type="text" id="search" class="form-control bg-dark text-white border-secondary me-2" placeholder="Cerca">
  <div class="form-check form-switch text-nowrap">
    <input class="form-check-input" type="checkbox" id="showInactive">
    <label class="form-check-label" for="showInactive">Mostra non attive</label>
  </div>
</div>
<div class="mb-3">
  <a href="password_dettaglio.php" class="btn btn-success w-100">➕ Nuovo</a>
</div>
<div id="passwordList">
<?php while ($row = $res->fetch_assoc()): ?>
  <?php render_password($row); ?>
<?php endwhile; ?>
</div>

<script src="js/password.js"></script>
<?php include 'includes/footer.php'; ?>
