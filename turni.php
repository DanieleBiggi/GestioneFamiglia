<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:turni.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
include 'includes/header.php';

$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$tipiRes = $conn->query("SELECT id, descrizione, colore_bg, colore_testo FROM turni_tipi WHERE attivo = 1 ORDER BY descrizione");
$tipi = $tipiRes ? $tipiRes->fetch_all(MYSQLI_ASSOC) : [];
?>
<style>
  #calendarContainer .col {height: 100px;}
  #pillContainer .pill.active {outline:2px solid #fff;}
</style>
<div id="shifter" class="d-flex flex-column min-vh-100 p-0">
  <div class="bg-dark text-white p-3 position-sticky top-0">
    <div class="d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <span class="fw-bold me-3">Shifter</span>
        <span class="text-muted">Turni neuro</span>
      </div>
      <div class="d-flex align-items-center">
        <i class="bi bi-chevron-left me-2" id="prevMonth" role="button"></i>
        <span id="monthLabel" class="mx-2 text-uppercase"></span>
        <i class="bi bi-chevron-right ms-2" id="nextMonth" role="button"></i>
      </div>
    </div>
    <div class="mt-2 text-center">
      <span class="me-3">SETTEMBRE</span>
      <span class="me-3">AGOSTO</span>
      <span>RIEPILOGO</span>
    </div>
  </div>
  <div class="flex-grow-1 overflow-auto" id="calendarContainer"></div>
  <div id="bottomBar" class="bg-dark text-white p-2 position-sticky bottom-0">
    <div id="stateA" class="d-flex justify-content-around">
      <button class="btn btn-outline-light flex-fill mx-1" id="btnSingolo">SINGOLO</button>
      <button class="btn btn-outline-light flex-fill mx-1" disabled>MULTIPLA</button>
      <button class="btn btn-outline-light flex-fill mx-1" disabled>TURNI</button>
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
<script>
  const turniTipi = <?= json_encode($tipi) ?>;
</script>
<script src="js/turni.js"></script>
<?php include 'includes/footer.php'; ?>
