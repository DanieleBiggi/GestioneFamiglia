<?php
function render_movimento(array $mov) {
    $importo = number_format($mov['amount'], 2, ',', '.');
    $dataOra = date('d/m/Y H:i', strtotime($mov['started_date']));
    echo '<a href="dettaglio.php?id=' . (int)$mov['id_movimento_revolut'] . '" class="movement d-flex justify-content-between align-items-start text-white text-decoration-none">';
    echo '  <div class="flex-grow-1 me-3">';
    echo '    <div class="descr fw-semibold">' . htmlspecialchars($mov['descrizione']) . '</div>';
    echo '    <div class="small">' . $dataOra . '</div>';
    echo '  </div>';
    echo '  <div class="text-end">';
    echo '    <div class="amount text-white">' . ($mov['amount'] >= 0 ? '+' : '') . $importo . ' €</div>';
    if (!empty($mov['etichette'])) {
        echo '    <div class="mt-1">';
        foreach (explode(',', $mov['etichette']) as $tag) {
            $tag = trim($tag);
            echo '      <span class="badge-etichetta me-1">' . htmlspecialchars($tag) . '</span>';
        }
        echo '    </div>';
    }
    echo '  </div>';
    echo '</a>';
}
