<?php
function render_eventi_google_rule(array $row) {
    $search = strtolower(($row['creator_email'] ?? '') . ' ' . ($row['description_keyword'] ?? '') . ' ' . ($row['id_tipo_evento'] ?? '') . ' ' . ($row['attiva'] ?? ''));
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $url = 'eventi_google_rule_dettaglio.php?id=' . (int)($row['id'] ?? 0);
    $active = (int)($row['attiva'] ?? 1);
    echo '<div class="rule-card d-flex justify-content-between align-items-start text-white text-decoration-none"'
        . ' data-search="' . $searchAttr . '" data-active="' . $active . '"'
        . ' onclick="window.location.href=\'' . $url . '\'">';
    echo '  <div class="flex-grow-1">';
    echo '    <div class="fw-semibold">' . htmlspecialchars($row['creator_email'] ?? '') . '</div>';
    echo '    <div class="small">' . htmlspecialchars($row['description_keyword'] ?? '') . '</div>';
    echo '  </div>';
    echo '</div>';
}
?>
