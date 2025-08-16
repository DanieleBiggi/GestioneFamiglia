<?php
function render_evento_tipo(array $row) {
    $isActive = (int)($row['attivo'] ?? 0) === 1;
    $classes = 'evento-tipo-card movement d-flex justify-content-between align-items-center text-white text-decoration-none';
    if (!$isActive) {
        $classes .= ' inactive';
    }
    $search = strtolower(($row['tipo_evento'] ?? '') . ' ' . ($row['colore'] ?? '') . ' ' . ($row['colore_testo'] ?? ''));
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $url = 'evento_tipo_dettaglio.php?id=' . (int)($row['id'] ?? 0);
    echo '<div class="' . $classes . '" data-search="' . $searchAttr . '" onclick="window.location.href=\'' . $url . '\'">';
    echo '  <div class="flex-grow-1">';
    echo '    <div class="fw-semibold">' . htmlspecialchars($row['tipo_evento'] ?? '') . '</div>';
    echo '  </div>';
    echo '  <div class="ms-2 d-flex align-items-center">';
    $bg = htmlspecialchars($row['colore'] ?? '#000000');
    $txt = htmlspecialchars($row['colore_testo'] ?? '#ffffff');
    echo '    <span class="badge me-2" style="background:' . $bg . ';color:' . $txt . '">Aa</span>';
    echo $isActive ? '    <i class="bi bi-check-circle-fill text-success"></i>' : '    <i class="bi bi-x-circle-fill text-danger"></i>';
    echo '  </div>';
    echo '</div>';
}
?>
