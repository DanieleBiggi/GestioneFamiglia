<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
include 'includes/header.php';
setlocale(LC_TIME, 'it_IT.UTF-8');

$mesi = [];
$sql = "SELECT DATE_FORMAT(started_date, '%Y-%m') AS ym, DATE_FORMAT(started_date, '%M %Y') AS mese_label FROM v_movimenti_revolut GROUP BY ym ORDER BY ym ASC";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $mesi[] = $row;
}
$ultimoIndice = count($mesi) - 1;
?>
<div class="months-scroll d-flex mb-3" id="monthsContainer">
    <?php foreach ($mesi as $idx => $m): ?>
        <button class="btn btn-outline-light me-2 <?= $idx === $ultimoIndice ? 'active' : '' ?>" data-mese="<?= htmlspecialchars($m['ym']) ?>">
            <?= ucfirst($m['mese_label']) ?>
        </button>
    <?php endforeach; ?>
</div>
<div id="movimenti" class="pb-5 text-white"></div>
<script src="js/tutti_movimenti.js"></script>
<?php include 'includes/footer.php'; ?>
