<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:lista_spesa.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
require_once 'includes/render_lista_spesa.php';
include 'includes/header.php';

$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$stmt = $conn->prepare("SELECT id, nome, quantita, note, checked FROM lista_spesa WHERE id_famiglia = ? ORDER BY checked ASC, created_at DESC");
$stmt->bind_param('i', $idFamiglia);
$stmt->execute();
$res = $stmt->get_result();
$canAdd = has_permission($conn, 'ajax:add_lista_spesa', 'insert');
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Lista spesa</h4>
  <?php if ($canAdd): ?>
  <div>
    <button type="button" class="btn btn-outline-light btn-sm me-2" onclick="openImportModal()">Importa</button>
    <button type="button" class="btn btn-outline-light btn-sm me-2" id="clearListaBtn">Svuota lista</button>
    <button type="button" class="btn btn-outline-light btn-sm" onclick="openListaModal()">Aggiungi nuovo</button>
    <button type="button" class="btn btn-outline-light btn-sm" onclick="location.href='menu_cene.php'">Menu</button>
  </div>
  <?php endif; ?>
</div>
<ul class="nav nav-tabs mb-3" role="tablist">\n  <li class="nav-item"><button class="nav-link active" id="listaSpesaTab" data-bs-toggle="tab" data-bs-target="#listaSpesaPane" type="button" role="tab">Lista della spesa</button></li>\n  <li class="nav-item"><button class="nav-link" id="verificaMenuTab" data-bs-toggle="tab" data-bs-target="#verificaMenuPane" type="button" role="tab">Verifica menù</button></li>\n</ul>\n<div class="tab-content"><div class="tab-pane fade show active" id="listaSpesaPane" role="tabpanel">\n<div id="listaSpesaList">
<?php while ($row = $res->fetch_assoc()): ?>
  <?php render_lista_spesa($row); ?>
<?php endwhile; ?>
</div>
<?php if ($canAdd): ?>
<div class="modal fade" id="listaModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="listaForm">
      <div class="modal-header">
        <h5 class="modal-title">Nuovo elemento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id">
        <div class="mb-3">
          <label class="form-label">Nome</label>
          <input type="text" name="nome" class="form-control bg-secondary text-white" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Quantit&agrave;</label>
          <input type="text" name="quantita" class="form-control bg-secondary text-white">
        </div>
        <div class="mb-3">
          <label class="form-label">Note</label>
          <textarea name="note" class="form-control bg-secondary text-white" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="importModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="importForm">
      <div class="modal-header">
        <h5 class="modal-title">Importa elementi</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Elementi (uno per riga - quantit&agrave; opzionale tra parentesi tonde e note opzionali tra parentesi quadre)</label>
          <textarea name="items" class="form-control bg-secondary text-white" rows="5"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Importa</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
<script src="js/lista_spesa.js"></script>\n<script src="js/verifica_menu_spesa.js"></script>
<?php include 'includes/footer.php'; ?>
