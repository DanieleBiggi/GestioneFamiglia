<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:turni.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
include 'includes/header.php';

$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$tipiRes = $conn->query("SELECT id, descrizione, colore_bg, colore_testo FROM turni_tipi WHERE attivo = 1 ORDER BY descrizione");
$tipi = $tipiRes ? $tipiRes->fetch_all(MYSQLI_ASSOC) : [];
$bambiniRes = $conn->query("SELECT u.id, COALESCE(NULLIF(u.soprannome,''), CONCAT(u.nome,' ',u.cognome)) AS nome FROM utenti u JOIN utenti2famiglie uf ON u.id = uf.id_utente WHERE uf.id_famiglia = $idFamiglia ORDER BY nome");
$bambini = $bambiniRes ? $bambiniRes->fetch_all(MYSQLI_ASSOC) : [];
$syncRes = $conn->query("SELECT COUNT(*) AS cnt FROM turni_calendario WHERE id_famiglia = $idFamiglia AND (data_ultima_sincronizzazione IS NULL OR aggiornato_il > data_ultima_sincronizzazione)");
$needSync = $syncRes ? ($syncRes->fetch_assoc()['cnt'] > 0) : false;
?>
<?php if ($needSync): ?>
<div class="alert alert-warning text-center m-0">Alcuni turni non sono sincronizzati con Google Calendar.</div>
<?php endif; ?>
<style>
  #calendarContainer .col {height: 100px; min-width:0; overflow:hidden;}
  #calendarContainer .day-cell {display:flex; flex-direction:column; padding:0;}
  #calendarContainer .turni-container {flex:1; display:flex; flex-direction:column;}
  #calendarContainer .turno {flex:1; display:flex; align-items:center; justify-content:center; font-size:.8rem; position:relative; overflow:hidden;}
  #calendarContainer .turno.event a {color:inherit; text-decoration:none; width:100%; display:block; white-space:nowrap; text-overflow:ellipsis; overflow:hidden;}
  #calendarContainer .turno .bambini {position:absolute; bottom:0; right:0; font-size:.6rem; padding:0 2px;}
  #pillContainer .pill.active {outline:2px solid #fff;}
  #calendarContainer .day-cell.multi-selected {outline:2px solid #0d6efd;}
</style>
<div id="shifter" class="d-flex flex-column min-vh-100 p-0">
  <div class="bg-dark text-white p-3 position-sticky top-0">
    <div class="d-flex justify-content-center align-items-center">
      <div class="d-flex align-items-center">
        <i class="bi bi-chevron-left me-2" id="prevMonth" role="button"></i>
        <span id="monthLabel" class="mx-2 text-uppercase"></span>
        <i class="bi bi-chevron-right ms-2" id="nextMonth" role="button"></i>
      </div>
    </div>
  </div>
  <div class="flex-grow-1 overflow-auto" id="calendarContainer"></div>
  <div id="bottomBar" class="bg-dark text-white p-2 position-sticky bottom-0">
    <div id="stateA" class="d-flex justify-content-around">
      <button class="btn btn-outline-light flex-fill mx-1" id="btnSingolo">SINGOLA</button>
      <button class="btn btn-outline-light flex-fill mx-1" id="btnMultipla">MULTIPLA</button>
      <button class="btn btn-outline-light flex-fill mx-1" id="btnGoogle">GOOGLE</button>
    </div>
    <div id="stateB" class="d-none">
      <div class="d-flex align-items-center">
        <i class="bi bi-chevron-down me-2" id="closeStateB" role="button"></i>
        <div class="flex-grow-1 overflow-auto">
          <div class="d-flex flex-nowrap" id="pillContainer">
            <button class="btn btn-sm btn-outline-light me-2 pill" data-type="delete">Eliminare</button>
            <?php foreach ($tipi as $t): ?>
            <button class="btn btn-sm me-2 pill" data-type="<?= (int)$t['id'] ?>" style="background: <?= htmlspecialchars($t['colore_bg']) ?>;color: <?= htmlspecialchars($t['colore_testo']) ?>"><?= htmlspecialchars($t['descrizione']) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="turnoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title">Dettaglio turno</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="turnoForm">
          <input type="hidden" id="turnoId" name="id">
          <div class="mb-3">
            <label class="form-label">Data</label>
            <div id="turnoDate" class="form-control bg-dark text-white border-secondary" readonly></div>
          </div>
          <div class="mb-3">
            <label for="turnoTipo" class="form-label">Tipo</label>
            <select class="form-select bg-dark text-white border-secondary" id="turnoTipo" name="id_tipo" required>
              <?php foreach ($tipi as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['descrizione']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="turnoOraInizio" class="form-label">Ora inizio</label>
            <input type="time" class="form-control bg-dark text-white border-secondary" id="turnoOraInizio" name="ora_inizio" required>
          </div>
          <div class="mb-3">
            <label for="turnoOraFine" class="form-label">Ora fine</label>
            <input type="time" class="form-control bg-dark text-white border-secondary" id="turnoOraFine" name="ora_fine" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Bambini</label>
            <div id="turnoBambini">
              <?php foreach ($bambini as $b): ?>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" id="bambino<?= (int)$b['id'] ?>" value="<?= (int)$b['id'] ?>">
                <label class="form-check-label" for="bambino<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['nome']) ?></label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mb-3">
            <label for="turnoNote" class="form-label">Note</label>
            <textarea class="form-control bg-dark text-white border-secondary" id="turnoNote" name="note"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
        <button type="button" class="btn btn-primary" id="saveTurno">Salva</button>
      </div>
    </div>
  </div>
</div>
<script>
  const turniTipi = <?= json_encode($tipi) ?>;
</script>
<script src="js/turni.js"></script>
<?php include 'includes/footer.php'; ?>
