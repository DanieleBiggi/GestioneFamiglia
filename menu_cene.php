<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:menu_cene.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
include 'includes/header.php';

$canEdit = has_permission($conn, 'ajax:update_menu_cena', 'update');
$canImport = has_permission($conn, 'ajax:import_menu_cene', 'insert');
?>
<div class="d-flex mb-3 justify-content-between align-items-start flex-wrap gap-2">
  <div>
    <h4 class="mb-1">Menù cene</h4>
    <div class="input-group input-group-sm" style="width: 230px;">
      <button class="btn btn-outline-light" type="button" id="prevWeekBtn"><i class="bi bi-chevron-left"></i></button>
      <input type="week" class="form-control bg-dark text-white" id="weekPicker" aria-label="Seleziona settimana">
      <button class="btn btn-outline-light" type="button" id="nextWeekBtn"><i class="bi bi-chevron-right"></i></button>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    
    <?php if ($canImport): ?>
    <button type="button" class="btn btn-outline-light btn-sm" onclick="openImportMenuModal()">Importa</button>
    <?php endif; ?>
    <button type="button" class="btn btn-outline-light btn-sm" id="exportMenuBtn">Esporta menù</button>
    <a href="lista_spesa.php" class="btn btn-outline-light btn-sm">Lista spesa</a>
    <button type="button" class="btn btn-outline-light btn-sm" id="generatePromptBtn">Suggerisci menù</button>
  </div>
</div>

<div id="menuGrid" class="weekly-grid"></div>

<?php if ($canEdit): ?>
<div class="modal fade" id="editMenuModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="editMenuForm">
      <div class="modal-header">
        <h5 class="modal-title">Modifica piatto</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id">
        <div class="mb-3">
          <label class="form-label">Giorno</label>
          <input type="text" name="giorno" class="form-control bg-secondary text-white" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label">Piatto</label>
          <textarea name="piatto" class="form-control bg-secondary text-white" rows="3" placeholder="Inserisci il piatto per questo giorno"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="promptModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title">Prompt per generare il menù della prossima settimana</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <textarea id="generatedPrompt" class="form-control bg-secondary text-white" rows="8" readonly></textarea>
        <button class="btn btn-outline-light btn-sm mt-3" type="button" id="copyPromptBtn">Copia prompt</button>
      </div>
    </div>
  </div>
</div>

<?php if ($canImport): ?>
<div class="modal fade" id="importMenuModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="importMenuForm">
      <div class="modal-header">
        <h5 class="modal-title">Importa menù</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Pasti (uno per riga in ordine da Lunedì a Domenica)</label>
          <textarea name="items" class="form-control bg-secondary text-white" rows="7" placeholder="Incolla l'output di ChatGPT"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Importa</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
  const MENU_CENE_CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
  const MENU_CENE_CAN_IMPORT = <?= $canImport ? 'true' : 'false' ?>;
</script>
<script src="js/menu_cene.js"></script>
<?php include 'includes/footer.php'; ?>
