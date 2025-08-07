<?php
function render_movimento(array $mov) {
    global $conn;
    
    if($mov['tabella']=="bilancio_uscite" && $mov['amount'] >= 0)
    {
        $mov['amount'] *= -1;
    }
    
    $importo = number_format($mov['amount'], 2, ',', '.');
    $dataOra  = date('d/m/Y H:i', strtotime($mov['data_operazione']));

    // Determine icon based on source
    $icon = $mov['source'] === 'revolut' ? 'assets/revolut.jpeg' : 'assets/credit.jpeg';

    $url = 'dettaglio.php?id=' . (int)$mov['id'] . '&src=' . urlencode($mov['tabella']);

    echo '<div class="movement d-flex justify-content-between align-items-start text-white text-decoration-none" data-id="' . (int)$mov['id'] . '" data-src="' . htmlspecialchars($mov['tabella'], ENT_QUOTES) . '" style="cursor:pointer" onclick="window.location.href=\'' . $url . '\'">';
    echo '  <img src="' . htmlspecialchars($icon) . '" alt="src" class="me-2" style="width:24px;height:24px">';
    echo '  <div class="flex-grow-1 me-3">';
    echo '    <div class="descr fw-semibold">' . htmlspecialchars($mov['descrizione']) . '</div>';
    echo '    <div class="small">' . $dataOra . '</div>';
    echo '  </div>';
    echo '  <div class="text-end">';
    echo '    <div class="amount text-white">' . ($mov['amount'] >= 0 ? '+' : '') . $importo . ' €';
    if (!empty($mov['mezzo']) && $mov['mezzo'] === 'contanti' && in_array($mov['tabella'], ['bilancio_entrate', 'bilancio_uscite'], true)) {
        echo ' <i class="bi bi-trash text-danger ms-2 delete-movimento" onclick="event.stopPropagation();"></i>';
    }
    echo '</div>';
    if (!empty($mov['etichette'])) {
        echo '    <div class="mt-1">';
        foreach (explode(',', $mov['etichette']) as $tag) {
            $tag = trim($tag);

            echo '      <a href="etichetta.php?id_etichetta=' . urlencode($tag) . '" class="badge-etichetta me-1" onclick="event.stopPropagation();">' . htmlspecialchars($tag) . '</a>';

        }
        echo '    </div>';
    }
    echo '  </div>';

    echo '</div>';

}
