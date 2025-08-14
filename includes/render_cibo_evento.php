<?php
function render_cibo_evento(array $row): void {
    $search = strtolower($row['piatto'] ?? '');
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $id = (int)($row['id'] ?? 0);
    $piatto = htmlspecialchars($row['piatto'] ?? '', ENT_QUOTES);
    $dolce = (int)($row['dolce'] ?? 0);
    $bere = (int)($row['bere'] ?? 0);
    $um = htmlspecialchars($row['um'] ?? '', ENT_QUOTES);
    echo '<div class="cibo-card movement d-flex justify-content-between align-items-start text-white text-decoration-none" data-search="' . $searchAttr . '" data-id="' . $id . '" data-piatto="' . $piatto . '" data-dolce="' . $dolce . '" data-bere="' . $bere . '" data-um="' . $um . '" onclick="openCiboEdit(this)">';
    echo '  <div class="fw-semibold">' . htmlspecialchars($row['piatto']) . '</div>';
    echo '</div>';
}
?>
