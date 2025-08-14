<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:invitati_cibo.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
require_once 'includes/render_cibo_evento.php';
include 'includes/header.php';
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$stmt = $conn->prepare('SELECT * FROM eventi_cibo WHERE id_famiglia=? AND attivo=1 ORDER BY piatto');
$stmt->bind_param("i", $idFamiglia);
$stmt->execute();
$res = $stmt->get_result();
$canInsert = has_permission($conn, 'table:eventi_cibo', 'insert');
$canUpdate = has_permission($conn, 'table:eventi_cibo', 'update');
$canDelete = has_permission($conn, 'table:eventi_cibo', 'delete');
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Cibo eventi</h4>
  <?php if ($canInsert): ?>
  <button type="button" class="btn btn-outline-light btn-sm" onclick="openCiboModal()">Aggiungi nuovo</button>
  <?php endif; ?>
</div>
<div class="d-flex mb-3 align-items-center">
  <input type="text" id="search" class="form-control bg-dark text-white border-secondary" placeholder="Cerca">
</div>
<div id="ciboList">
<?php while ($row = $res->fetch_assoc()): ?>
  <?php render_cibo_evento($row); ?>
<?php endwhile; ?>
</div>
<?php if ($canInsert || $canUpdate): ?>
<div class="modal fade" id="ciboModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="ciboForm">
      <div class="modal-header">
        <h5 class="modal-title">Cibo</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Piatto</label>
          <input type="text" name="piatto" class="form-control bg-secondary text-white" required>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="dolce" id="dolce">
          <label class="form-check-label" for="dolce">Dolce</label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="bere" id="bere">
          <label class="form-check-label" for="bere">Bere</label>
        </div>
        <div class="mb-3">
          <label class="form-label">Unità di misura</label>
          <select name="um" class="form-select bg-secondary text-white">
            <option value="etti">etti</option>
            <option value="quantita">quantità</option>
            <option value="porzioni">porzioni</option>
            <option value="litri">litri</option>
          </select>
        </div>
        <input type="hidden" name="id" id="cibo_id">
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-danger me-auto d-none" id="deleteCibo">Elimina</button>
        <button type="submit" class="btn btn-primary">Salva</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
<script src="js/invitati_cibo.js"></script>
<?php include 'includes/footer.php'; ?>
