<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
include 'includes/header.php';

$sql = "SELECT e.id_etichetta, e.descrizione, e.attivo, e.da_dividere,
                EXISTS (
                  SELECT 1
                  FROM bilancio_etichette2operazioni eo
                  LEFT JOIN bilancio_utenti2operazioni_etichettate uo ON eo.id_operazione = uo.id_operazione
                  WHERE eo.id_etichetta = e.id_etichetta AND uo.id_operazione IS NULL
                ) AS has_unassigned
         FROM bilancio_etichette e
         ORDER BY e.descrizione ASC";
$etichette = $conn->query($sql);
?>

<div class="text-white">
  <div class="d-flex mb-3 justify-content-between"><h4>Etichette</h4><button type="button" class="btn btn-outline-light btn-sm" onclick="openEtichettaModal()">Aggiungi nuova</button></div>
  <div class="d-flex mb-3 align-items-center">
    <input type="text" id="search" class="form-control bg-dark text-white border-secondary me-2" placeholder="Cerca">
    <div class="form-check form-switch text-nowrap">
      <input class="form-check-input" type="checkbox" id="showInactive">
      <label class="form-check-label" for="showInactive">Mostra non attive</label>
    </div>
  </div>

  <div class="list-group" id="labelList">
    <?php while ($row = $etichette->fetch_assoc()): ?>
      <?php $isActive = (int)($row['attivo'] ?? 0) === 1; ?>
      <a href="etichetta.php?id_etichetta=<?= urlencode($row['id_etichetta']) ?>" class="list-group-item bg-dark text-white d-flex justify-content-between align-items-center text-decoration-none label-card<?= $isActive ? '' : ' inactive' ?>" data-search="<?= strtolower($row['descrizione']) ?>" style="<?= $isActive ? '' : 'display:none;' ?>">
        <span><?= htmlspecialchars($row['descrizione']) ?></span>
        <?php if ($isActive): ?>
          <i class="bi bi-check-circle-fill text-success"></i>
        <?php else: ?>
          <i class="bi bi-x-circle-fill text-danger"></i>
        <?php endif; ?>
        <?php if (($row['da_dividere'] ?? 0) == 1 && ($row['has_unassigned'] ?? 0) == 1): ?>
          <i class="bi bi-exclamation-circle-fill text-warning ms-1"></i>
        <?php endif; ?>
      </a>
    <?php endwhile; ?>
  </div>
</div>

<div class="modal fade" id="editEtichettaModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" onsubmit="saveEtichetta(event)">
      <div class="modal-header">
        <h5 class="modal-title">Nuova etichetta</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="descrizione" class="form-label">Descrizione</label>
          <input type="text" class="form-control bg-secondary text-white" id="descrizione" required>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="attivo" checked>
          <label class="form-check-label" for="attivo">Attivo</label>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="da_dividere">
          <label class="form-check-label" for="da_dividere">Da dividere</label>
        </div>
        <div class="mb-3">
          <label for="utenti_tra_cui_dividere" class="form-label">Utenti tra cui dividere</label>
          <input type="text" class="form-control bg-secondary text-white" id="utenti_tra_cui_dividere">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>

<script src="js/etichette.js"></script>
<?php include 'includes/footer.php'; ?>

