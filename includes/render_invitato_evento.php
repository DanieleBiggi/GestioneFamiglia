<?php
function render_invitato_evento(array $row): void {
    $search = strtolower(trim(($row['nome'] ?? '') . ' ' . ($row['cognome'] ?? '')));
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $url = 'invitati_eventi_dettaglio.php?id=' . (int)($row['id'] ?? 0);
    echo '<div class="invitato-card movement d-flex justify-content-between align-items-start text-white text-decoration-none" data-search="' . $searchAttr . '" onclick="window.location.href=\'' . $url . '\'">';
    echo '  <div class="flex-grow-1">';
    echo '    <div class="fw-semibold">' . htmlspecialchars(($row['nome'] ?? '') . ' ' . ($row['cognome'] ?? '')) . '</div>';
    echo '  </div>';
    echo '</div>';
}
?>
