<?php
require_once __DIR__ . '/../includes/db.php';
setlocale(LC_TIME, 'it_IT.UTF-8');

$mese = $_GET['mese'] ?? date('Y-m');

$stmt = $conn->prepare("SELECT id_movimento_revolut, started_date, amount, COALESCE(NULLIF(descrizione_extra, ''), description) AS descrizione, etichette FROM v_movimenti_revolut WHERE DATE_FORMAT(started_date, '%Y-%m') = ? ORDER BY started_date DESC");
$stmt->bind_param('s', $mese);
$stmt->execute();
$result = $stmt->get_result();

$giorno_corrente = '';
while ($mov = $result->fetch_assoc()) {
    $giorno = strftime('%A %e %B', strtotime($mov['started_date']));
    if ($giorno !== $giorno_corrente) {
        echo '<div class="day-header mt-3 mb-1 fw-bold">' . ucfirst($giorno) . '</div>';
        $giorno_corrente = $giorno;
    }

    $importo = number_format($mov['amount'], 2, ',', '.');
    $classe_importo = $mov['amount'] >= 0 ? 'text-success' : 'text-danger';
    $ora = date('H:i', strtotime($mov['started_date']));
    echo '<div class="movement d-flex align-items-center py-2">';
    echo '  <div class="icon me-3"><i class="bi bi-arrow-left-right fs-4"></i></div>';
    echo '  <div class="flex-grow-1">';
    echo '    <div class="descr">' . htmlspecialchars($mov['descrizione']) . '</div>';
    echo '    <div class="text-muted small">' . $ora . '</div>';
    if (!empty($mov['etichette'])) {
        echo '    <div class="mt-1">';
        foreach (explode(',', $mov['etichette']) as $tag) {
            $tag = trim($tag);
            echo '      <a href="etichetta.php?etichetta=' . urlencode($tag) . '" class="badge-etichetta me-1 text-white">' . htmlspecialchars($tag) . '</a>';
        }
        echo '    </div>';
    }
    echo '  </div>';
    echo '  <div class="amount ms-2 ' . $classe_importo . '">' . ($mov['amount'] >= 0 ? '+' : '') . $importo . ' €</div>';
    echo '</div>';
}
?>
