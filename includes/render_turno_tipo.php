<?php
function render_turno_tipo(array $row) {
    $isActive = (int)($row['attivo'] ?? 0) === 1;
    $classes = 'turno-tipo-card movement d-flex justify-content-between align-items-center text-white text-decoration-none';
    if (!$isActive) {
        $classes .= ' inactive';
    }
    $searchParts = [
        $row['descrizione'] ?? '',
        $row['ora_inizio'] ?? '',
        $row['ora_fine'] ?? '',
        $row['colore_bg'] ?? '',
        $row['colore_testo'] ?? ''
    ];
    $search = strtolower(implode(' ', $searchParts));
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $url = 'turno_tipo_dettaglio.php?id=' . (int)($row['id'] ?? 0);
    echo '<div class="' . $classes . '" data-search="' . $searchAttr . '" onclick="window.location.href=\'' . $url . '\'">';
    echo '  <div class="flex-grow-1">';
    echo '    <div class="fw-semibold">' . htmlspecialchars($row['descrizione'] ?? '') . '</div>';
    $start = $row['ora_inizio'] ?? '';
    $end = $row['ora_fine'] ?? '';
    $timeText = trim($start . ' - ' . $end, ' -');
    if ($timeText !== '') {
        echo '    <div class="small">' . htmlspecialchars($timeText) . '</div>';
    }
    echo '  </div>';
    echo '  <div class="ms-2 d-flex align-items-center">';
    $bg = htmlspecialchars($row['colore_bg'] ?? '#000000');
    $txt = htmlspecialchars($row['colore_testo'] ?? '#ffffff');
    echo '    <span class="badge me-2" style="background:' . $bg . ';color:' . $txt . '">Aa</span>';
    echo $isActive ? '    <i class="bi bi-check-circle-fill text-success"></i>' : '    <i class="bi bi-x-circle-fill text-danger"></i>';
    echo '  </div>';
    echo '</div>';
}
?>
