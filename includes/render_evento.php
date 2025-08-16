<?php
function render_evento(array $row): void {
    $search = strtolower(trim(($row['titolo'] ?? '') . ' ' . ($row['descrizione'] ?? '') . ' ' . ($row['tipo_evento'] ?? '')));
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $startDate = !empty($row['data_evento']) ? date('d/m/Y', strtotime($row['data_evento'])) : '';
    $endDate = !empty($row['data_fine']) ? date('d/m/Y', strtotime($row['data_fine'])) : '';
    $startTime = $row['ora_evento'] ?? '';
    $endTime = $row['ora_fine'] ?? '';
    $periodo = trim($startDate . ' ' . $startTime);
    $endPart = trim(($endDate ?: $startDate) . ' ' . $endTime);
    if ($endPart && $endPart !== $periodo) {
        $periodo .= ' - ' . $endPart;
    }
    $url = 'eventi_dettaglio.php?id=' . (int)($row['id'] ?? 0);
    echo '<div class="event-card movement d-flex justify-content-between align-items-start text-white text-decoration-none" data-search="' . $searchAttr . '" onclick="window.location.href=\'' . $url . '\'">';
    echo '  <div class="flex-grow-1">';
    echo '    <div class="fw-semibold">' . htmlspecialchars($row['titolo']) . '</div>';
    if ($periodo !== '') {
        echo '    <div class="small">' . htmlspecialchars($periodo) . '</div>';
    }
    if (!empty($row['descrizione'])) {
        echo '    <div class="small text-muted">' . htmlspecialchars($row['descrizione']) . '</div>';
    }
    echo '  </div>';
    if (!empty($row['tipo_evento'])) {
        $bg = htmlspecialchars($row['colore'] ?? '#71843f', ENT_QUOTES);
        $txt = htmlspecialchars($row['colore_testo'] ?? '#ffffff', ENT_QUOTES);
        echo '  <span class="badge ms-2" style="background-color: ' . $bg . ';color:' . $txt . '">' . htmlspecialchars($row['tipo_evento']) . '</span>';
    }
    echo '</div>';
}
?>
