<?php
function render_query(array $row) {
    $isArchived = (int)($row['archiviato'] ?? 0) === 1;
    $classes = 'query-card d-flex justify-content-between align-items-center text-white text-decoration-none border-bottom py-2';
    if ($isArchived) {
        $classes .= ' archiviato';
    }
    $search = strtolower(($row['descrizione'] ?? '') . ' ' . ($row['stringa_da_completare'] ?? ''));
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $url = 'query_dettaglio.php?id=' . (int)$row['id_dato_remoto'];
    echo '<div class="' . $classes . '" data-search="' . $searchAttr . '">';
    echo '  <div class="flex-grow-1" onclick="window.location.href=\'' . $url . '\'">';
    echo '    <div class="fw-semibold">' . htmlspecialchars($row['descrizione'] ?? '') . '</div>';
    echo '  </div>';
    echo '  <div class="ms-2 text-nowrap">';
    echo '    <a class="btn btn-sm btn-outline-light run-query" href="query_execute.php?id=' . (int)$row['id_dato_remoto'] . '" onclick="event.stopPropagation();">Esegui</a>';
    echo '  </div>';
    echo '</div>';
}
?>
