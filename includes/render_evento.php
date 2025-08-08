<?php
function render_evento(array $row): void {
    $search = strtolower(trim(($row['titolo'] ?? '') . ' ' . ($row['descrizione'] ?? '') . ' ' . ($row['tipo_evento'] ?? '')));
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $data = !empty($row['data_evento']) ? date('d/m/Y', strtotime($row['data_evento'])) : '';
    $ora = $row['ora_evento'] ?? '';
    $url = 'eventi_dettaglio.php?id=' . (int)($row['id'] ?? 0);
    echo '<div class="event-card movement d-flex justify-content-between align-items-start text-white text-decoration-none" data-search="' . $searchAttr . '" onclick="window.location.href=\'' . $url . '\'">';
    echo '  <div class="flex-grow-1">';
    echo '    <div class="fw-semibold">' . htmlspecialchars($row['titolo']) . '</div>';
    if ($data || $ora) {
        $dataOra = trim($data . ' ' . $ora);
        echo '    <div class="small">' . htmlspecialchars($dataOra) . '</div>';
    }
    if (!empty($row['descrizione'])) {
        echo '    <div class="small text-muted">' . htmlspecialchars($row['descrizione']) . '</div>';
    }
    echo '  </div>';
    if (!empty($row['tipo_evento'])) {
        $color = htmlspecialchars($row['colore'] ?? '#71843f', ENT_QUOTES);
        echo '  <span class="badge ms-2" style="background-color: ' . $color . '">' . htmlspecialchars($row['tipo_evento']) . '</span>';
    }
    echo '</div>';
}
?>
