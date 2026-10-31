<?php
include 'includes/session_check.php';
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:menu_cene_config.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
$canSave = has_permission($conn, 'page:menu_cene_config.php', 'update');
if (empty($_SESSION['menu_config_csrf'])) $_SESSION['menu_config_csrf'] = bin2hex(random_bytes(32));
include 'includes/header.php';
?>
<h4>Configurazione · Menù cene</h4>
<p class="text-secondary">Configurazioni della famiglia selezionata. I menù settimanali già generati non vengono modificati.</p>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><button class="nav-link active" type="button" data-kind="master">Master menù</button></li>
  <li class="nav-item"><button class="nav-link" type="button" data-kind="ricette">Ricette e preferenze</button></li>
</ul>
<div id="menuConfigAlert" role="status"></div>
<section id="masterEditor">
  <div class="row g-2 mb-3">
    <div class="col-md-4"><label class="form-label">Anno scolastico</label><input id="cfgYear" class="form-control bg-dark text-white"></div>
    <div class="col-md-4"><label class="form-label">Lunedì di riferimento del ciclo</label><input id="cfgAnchor" type="date" class="form-control bg-dark text-white"></div>
    <div class="col-md-4"><label class="form-label">Settimana del ciclo alla data indicata</label><select id="cfgAnchorWeek" class="form-select bg-dark text-white"><option>1</option><option>2</option><option>3</option><option>4</option></select></div>
  </div>
  <div class="row g-2 mb-3">
    <div class="col-md-6"><label class="form-label">Stagione</label><select id="cfgSeason" class="form-select bg-dark text-white"><option>Estivo</option><option>Invernale</option></select></div>
    <div class="col-md-6"><label class="form-label">Settimana del ciclo</label><select id="cfgWeek" class="form-select bg-dark text-white"><option>1</option><option>2</option><option>3</option><option>4</option></select></div>
  </div>
  <div id="cfgDays"></div>
</section>
<section id="recipesEditor" hidden>
  <p class="small text-secondary">Associazioni per nome esatto o alias. Una ricetta può valere per più piatti. Le istruzioni vengono esportate insieme al menù.</p>
  <div id="cfgRecipes"></div>
  <?php if ($canSave): ?><button id="cfgAddRecipe" type="button" class="btn btn-outline-light btn-sm mb-3">+ Aggiungi ricetta</button><?php endif; ?>
</section>
<div class="d-flex flex-wrap gap-2 my-3">
  <?php if ($canSave): ?><button id="cfgSave" class="btn btn-primary" type="button">Salva modifiche</button>
  <label class="btn btn-outline-light mb-0" for="cfgImport">Importa JSON</label><input type="file" id="cfgImport" accept=".json,application/json" hidden><?php endif; ?>
  <button id="cfgExport" class="btn btn-outline-light" type="button">Scarica JSON</button>
</div>
<p class="small text-secondary">L'importazione viene controllata prima del salvataggio. Prima di sostituire un file viene creato un backup sul server. Non importare JSON di provenienza sconosciuta.</p>
<script>window.MENU_CONFIG_CAN_SAVE = <?= $canSave ? 'true' : 'false' ?>; window.MENU_CONFIG_CSRF = <?= json_encode($_SESSION['menu_config_csrf']) ?>;</script>
<script src="js/menu_cene_config.js"></script>
<?php include 'includes/footer.php'; ?>
