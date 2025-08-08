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
$tipiRes = $conn->query('SELECT id, tipo_evento FROM eventi_tipi_eventi ORDER BY tipo_evento');
$tipi = $tipiRes ? $tipiRes->fetch_all(MYSQLI_ASSOC) : [];
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Eventi</h4>
  <?php if ($canInsert): ?>
  <button type="button" class="btn btn-outline-light btn-sm" onclick="openEventoModal()">Aggiungi nuovo</button>
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
<?php if ($canInsert): ?>
<div class="modal fade" id="eventoModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="eventoForm">
      <div class="modal-header">
        <h5 class="modal-title">Nuovo evento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Titolo</label>
          <input type="text" name="titolo" class="form-control bg-secondary text-white" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Descrizione</label>
          <textarea name="descrizione" class="form-control bg-secondary text-white"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Data</label>
          <input type="date" name="data_evento" class="form-control bg-secondary text-white">
        </div>
        <div class="mb-3">
          <label class="form-label">Ora</label>
          <input type="time" name="ora_evento" class="form-control bg-secondary text-white">
        </div>
        <div class="mb-3">
          <label class="form-label">Tipo evento</label>
          <select name="id_tipo_evento" class="form-select bg-secondary text-white">
            <option value="">-- nessuno --</option>
            <?php foreach ($tipi as $tipo): ?>
              <option value="<?= (int)$tipo['id'] ?>"><?= htmlspecialchars($tipo['tipo_evento']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
<script src="js/eventi.js"></script>
<?php include 'includes/footer.php'; ?>
