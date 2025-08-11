<?php
function render_salvadanaio(array $row) {
    $search = strtolower(($row['nome_salvadanaio'] ?? '') . ' ' . ($row['importo_attuale'] ?? '') . ' ' . ($row['data_aggiornamento_manuale'] ?? ''));
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $url = 'salvadanaio_dettaglio.php?id=' . (int)($row['id_salvadanaio'] ?? 0);
    echo '<div class="salvadanaio-card movement d-flex justify-content-between align-items-start text-white text-decoration-none"'
        . ' data-search="' . $searchAttr . '"'
        . ' onclick="window.location.href=\'' . $url . '\'">';
    echo '  <div class="flex-grow-1">';
    echo '    <div class="fw-semibold">' . htmlspecialchars($row['nome_salvadanaio'] ?? '') . '</div>';
    $importo = isset($row['importo_attuale']) ? (float)$row['importo_attuale'] : 0;
    $importoFmt = number_format($importo, 2, ',', '.');
    echo '    <div class="small">Importo: ' . $importoFmt . ' &euro;</div>';
    if (!empty($row['data_aggiornamento_manuale'])) {
        echo '    <div class="small text-muted">Agg. manuale: ' . htmlspecialchars($row['data_aggiornamento_manuale']) . '</div>';
    }
    echo '  </div>';
    echo '</div>';
}
?>
