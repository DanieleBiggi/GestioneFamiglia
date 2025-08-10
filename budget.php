<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/render_budget.php';
include 'includes/header.php';

$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$stmt = $conn->prepare('SELECT b.*, s.nome_salvadanaio FROM budget b LEFT JOIN salvadanai s ON b.id_salvadanaio = s.id_salvadanaio WHERE b.id_famiglia = ? ORDER BY b.data_inizio');
$stmt->bind_param('i', $idFamiglia);
$stmt->execute();
$res = $stmt->get_result();
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Budget</h4>
  <button type="button" class="btn btn-outline-light btn-sm" id="addBudgetBtn">Aggiungi</button>
</div>
<div class="d-flex mb-3 align-items-center">
  <input type="text" id="search" class="form-control bg-dark text-white border-secondary me-2" placeholder="Cerca">
  <button type="button" class="btn btn-outline-light" id="filterBtn">
    <i class="bi bi-funnel"></i>
  </button>
</div>
<div id="budgetList" class="list-group">
<?php while ($row = $res->fetch_assoc()): ?>
  <?php render_budget($row); ?>
<?php endwhile; ?>
</div>

<!-- Modal Filtri -->
<div class="modal fade" id="filterModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title">Filtri</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Tipologia</label>
          <select id="filterTipologia" class="form-select bg-secondary text-white">
            <option value="">Tutte</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Salvadanaio</label>
          <select id="filterSalvadanaio" class="form-select bg-secondary text-white">
            <option value="">Tutti</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Tipologia spesa</label>
          <select id="filterTipologiaSpesa" class="form-select bg-secondary text-white">
            <option value="">Tutte</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Data inizio</label>
          <input type="date" id="filterDataInizio" class="form-control bg-secondary text-white">
        </div>
        <div class="mb-3">
          <label class="form-label">Data fine</label>
          <input type="date" id="filterDataFine" class="form-control bg-secondary text-white">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
        <button type="button" class="btn btn-primary" id="applyFilters">Applica</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Budget -->
<div class="modal fade" id="budgetModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="budgetForm">
      <div class="modal-header">
        <h5 class="modal-title">Nuovo budget</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="budgetId">
        <div class="mb-3">
          <label class="form-label">Descrizione</label>
          <input type="text" name="descrizione" id="budgetDescrizione" class="form-control bg-secondary text-white" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Tipologia</label>
          <select name="tipologia" id="budgetTipologia" class="form-select bg-secondary text-white">
            <option value=""></option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Tipologia spesa</label>
          <select name="tipologia_spesa" id="budgetTipologiaSpesa" class="form-select bg-secondary text-white">
            <option value=""></option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Importo</label>
          <input type="number" step="0.01" name="importo" id="budgetImporto" class="form-control bg-secondary text-white" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Data inizio</label>
          <input type="date" name="data_inizio" id="budgetDataInizio" class="form-control bg-secondary text-white">
        </div>
        <div class="mb-3">
          <label class="form-label">Data fine</label>
          <input type="date" name="data_fine" id="budgetDataFine" class="form-control bg-secondary text-white">
        </div>
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-danger" id="deleteBudget">Elimina</button>
        <button type="button" class="btn btn-secondary" id="duplicateBudget">Duplica</button>
        <button type="submit" class="btn btn-primary">Salva</button>
      </div>
    </form>
  </div>
</div>

<script src="js/budget.js"></script>
<?php include 'includes/footer.php'; ?>
