<?php
include 'includes/session_check.php';
include 'includes/db.php';
require_once 'includes/utility.php';
include 'includes/header.php';

$SQLinv =
"SELECT
    CODAZI,
    RAGSOC,
    SIGLA,
    INIZIO,
    FINE,
    PARTIVA
FROM
    dbo.ARCAZI
ORDER BY
    RAGSOC ASC";

$utility = new Utility();
$per_aziende = $utility->getDati($SQLinv);

$aziende = [];
$aziende_chiavi = [];

foreach ($per_aziende as $azienda) {
    $aziende[] = $azienda;
    $aziende_chiavi[$azienda['CODAZI']] = $azienda;
}
?>
<script>
var aziende = JSON.parse('<?= json_encode($aziende, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>');
var aziende_chiavi = JSON.parse('<?= json_encode($aziende_chiavi, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>');
</script>
<div class="d-flex mb-3 justify-content-between">
    <h4>Storia</h4>
</div>
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link active" id="nav-utenti" data-quale="utenti" href="#">Utenti</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="nav-aziende" data-quale="aziende" href="#">Aziende</a>
    </li>
</ul>
<div class="d-flex mb-3 align-items-center">
    <input type="text" id="ricerca" class="form-control bg-dark text-white border-secondary me-2" placeholder="Cerca">
    <select id="filtroAzienda" class="form-select bg-dark text-white border-secondary me-2">
        <option value="">Tutte le aziende</option>
        <?php foreach ($aziende as $az): ?>
            <option value="<?= $az['CODAZI']; ?>"><?= htmlspecialchars($az['RAGSOC']); ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-light btn-sm" id="btn_ricerca" type="button">Cerca</button>
</div>
<nav aria-label="breadcrumb" id="breadcrumbs_storia" style="display:none">
    <ol class="breadcrumb" id="breadcrumbs_storia_content">
    </ol>
</nav>
<div id="loading" class="text-center my-3" style="display:none">
    <div class="spinner-border" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>
<div id="div_content">

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="js/storia.js"></script>
<?php include 'includes/footer.php'; ?>
