<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/render_movimento.php';
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

    render_movimento($mov);
}

