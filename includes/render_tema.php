<?php
function render_tema(array $row) {
    $search = strtolower(
        ($row['nome'] ?? '') . ' ' .
        ($row['background_color'] ?? '') . ' ' .
        ($row['text_color'] ?? '') . ' ' .
        ($row['primary_color'] ?? '') . ' ' .
        ($row['secondary_color'] ?? '')
    );
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $url = 'tema_dettaglio.php?id=' . (int)$row['id'];
    echo '<div class="tema-card movement d-flex justify-content-between align-items-center text-white text-decoration-none"';
    echo ' data-search="' . $searchAttr . '" onclick="window.location.href=\'' . $url . '\'">';
    echo '  <div class="flex-grow-1">';
    echo '    <div class="fw-semibold">' . htmlspecialchars($row['nome']) . '</div>';
    echo '    <div class="small">BG: ' . htmlspecialchars($row['background_color']);
    echo ' | Text: ' . htmlspecialchars($row['text_color']) . '</div>';
    echo '    <div class="small">Primary: ' . htmlspecialchars($row['primary_color']);
    echo ' | Secondary: ' . htmlspecialchars($row['secondary_color']) . '</div>';
    echo '  </div>';
    echo '  <div class="ms-2 d-flex align-items-center">';
    foreach (['background_color','text_color','primary_color','secondary_color'] as $col) {
        $color = htmlspecialchars($row[$col]);
        echo '<span class="rounded me-1" style="width:20px;height:20px;background:' . $color . ';border:1px solid #fff;"></span>';
    }
    echo '  </div>';
    echo '</div>';
}
?>
