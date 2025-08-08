<?php
function render_mezzo(array $row) {
    $isActive = (int)($row['attivo'] ?? 0) === 1;
    $classes = 'mezzo-card movement d-flex justify-content-between align-items-start text-white text-decoration-none';
    if (!$isActive) {
        $classes .= ' inactive';
    }
    $search = strtolower(($row['nome_mezzo'] ?? '') . ' ' . ($row['data_immatricolazione'] ?? ''));
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $url = 'mezzo_dettaglio.php?id=' . (int)($row['id_mezzo'] ?? 0);
    $style = $isActive ? '' : ' style="display:none;"';
    echo '<div class="' . $classes . '" data-search="' . $searchAttr . '"' . $style . ' onclick="window.location.href=\'' . $url . '\'">';
    echo '  <div class="flex-grow-1">';
    echo '    <div class="fw-semibold">' . htmlspecialchars($row['nome_mezzo'] ?? '') . '</div>';
    if (!empty($row['data_immatricolazione'])) {
        echo '    <div class="small">Immatricolazione: ' . htmlspecialchars($row['data_immatricolazione']) . '</div>';
    }
    echo '  </div>';
    $icons = $isActive
        ? '<i class="bi bi-check-circle-fill text-success"></i>'
        : '<i class="bi bi-x-circle-fill text-danger"></i>';
    echo '  <div class="ms-2 text-nowrap">' . $icons . '</div>';
    echo '</div>';
}
?>
