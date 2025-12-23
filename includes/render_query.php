<?php
function extract_tables(string $sql): array {
    $tables = [];
    if ($sql === '') {
        return $tables;
    }

    if (preg_match_all('/\b(?:FROM|JOIN)\s+[`"]?([A-Za-z0-9_]+)[`"]?/i', $sql, $matches)) {
        foreach ($matches[1] as $table) {
            $tables[strtoupper($table)] = $table;
        }
    }

    return array_values($tables);
}

function render_query(array $row) {
    $isArchived = (int)($row['archiviato'] ?? 0) === 1;
    $classes = 'query-card d-flex justify-content-between align-items-center text-white text-decoration-none border-bottom py-2';
    if ($isArchived) {
        $classes .= ' archiviato';
    }
    $tables = extract_tables($row['stringa_da_completare'] ?? '');
    $tablesText = $tables ? 'Tabelle: ' . implode(', ', $tables) : 'Nessuna tabella trovata';
    $search = strtolower(($row['descrizione'] ?? '') . ' ' . ($row['stringa_da_completare'] ?? '') . ' ' . $tablesText);
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $url = 'query_dettaglio.php?id=' . (int)$row['id_dato_remoto'];
    echo '<div class="' . $classes . '" data-search="' . $searchAttr . '">';
    echo '  <div class="flex-grow-1" onclick="window.location.href=\'' . $url . '\'">';
    echo '    <div class="fw-semibold">' . htmlspecialchars($row['descrizione'] ?? '') . '</div>';
    if ($tables) {
        echo '    <div class="small text-secondary">Lavora su: ' . htmlspecialchars(implode(', ', $tables)) . '</div>';
    }
    //echo '    <div class="small text-muted fst-italic">' . htmlspecialchars($row['stringa_da_completare'] ?? '') . '</div>';
    echo '  </div>';
    echo '  <div class="ms-2 text-nowrap">';
    echo '    <a class="btn btn-sm btn-outline-light run-query" href="query_execute.php?id=' . (int)$row['id_dato_remoto'] . '" onclick="event.stopPropagation();">Esegui</a>';
    echo '  </div>';
    echo '</div>';
}
?>
