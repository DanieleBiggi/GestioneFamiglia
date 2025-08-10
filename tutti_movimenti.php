<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
include 'includes/header.php';
setlocale(LC_TIME, 'it_IT.UTF-8');

$idUtente = $_SESSION['utente_id'] ?? 0;

$movimenti_revolut1 = "";
if (isset($_SESSION['id_famiglia_gestione']) && $_SESSION['id_famiglia_gestione'] == 1)
{
$movimenti_revolut1 = 
"SELECT started_date AS data_operazione FROM v_movimenti_revolut
            UNION ALL";
}

$mesi = [];
$sql = "SELECT DATE_FORMAT(data_operazione, '%Y-%m') AS ym
         FROM (
            ".$movimenti_revolut1."
            SELECT data_operazione FROM bilancio_entrate WHERE id_utente = {$idUtente}
            UNION ALL
            SELECT data_operazione FROM bilancio_uscite WHERE id_utente = {$idUtente}
         ) t
         GROUP BY ym ORDER BY ym ASC";
$result = $conn->query($sql);
$annoCorrente = date('Y');
while ($row = $result->fetch_assoc()) {
    $timestamp = strtotime($row['ym'] . '-01');
    $label = strftime('%B', $timestamp);
    $anno = date('Y', $timestamp);
    if ($anno < $annoCorrente) {
        $label .= ' ' . $anno;
    }
    $mesi[] = [
        'ym' => $row['ym'],
        'label' => ucfirst($label)
    ];
}
$ultimoIndice = count($mesi) - 1;
$anni = [];
foreach ($mesi as $m) {
    $y = substr($m['ym'], 0, 4);
    if (!in_array($y, $anni)) {
        $anni[] = $y;
    }
}
?>
<div class="mb-2">
    <select id="yearSelector" class="form-select bg-dark text-white border-secondary w-auto">
        <?php foreach ($anni as $anno): ?>
            <option value="<?= htmlspecialchars($anno) ?>" <?= $anno == $annoCorrente ? 'selected' : '' ?>><?= htmlspecialchars($anno) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="months-scroll d-flex mb-3 pt-3 pb-3" id="monthsContainer">
    <?php foreach ($mesi as $idx => $m): ?>
        <button class="btn btn-outline-light me-2 <?= $idx === $ultimoIndice ? 'active' : '' ?>" data-mese="<?= htmlspecialchars($m['ym']) ?>">
            <?= $m['label'] ?>
        </button>
    <?php endforeach; ?>
</div>
 <div id="movimenti" class="pb-5 text-white"></div>

 <!-- Modal conferma eliminazione -->
 <div class="modal fade" id="deleteModal" tabindex="-1">
   <div class="modal-dialog">
     <div class="modal-content bg-dark text-white">
       <div class="modal-header">
         <h5 class="modal-title">Conferma eliminazione</h5>
         <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
       </div>
       <div class="modal-body">
         Sei sicuro di voler eliminare questo movimento?
       </div>
       <div class="modal-footer">
         <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
         <button type="button" class="btn btn-danger" id="confirmDelete">Elimina</button>
       </div>
     </div>
   </div>
 </div>

 <script src="js/tutti_movimenti.js"></script>
 <script src="js/delete_movimento.js"></script>
 <?php include 'includes/footer.php'; ?>
