<?php
function render_film(array $row) {
    $search = strtolower(($row['titolo'] ?? '') . ' ' . ($row['titolo_originale'] ?? ''));
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $anno = (int)($row['anno'] ?? 0);
    $generi = htmlspecialchars($row['generi'] ?? '', ENT_QUOTES);
    $url = 'film_dettaglio.php?id=' . (int)($row['id_film'] ?? 0);
    echo '<div class="film-card movement d-flex align-items-start text-white text-decoration-none mb-2" data-search="' . $searchAttr . '" data-anno="' . $anno . '" data-generi="' . $generi . '" onclick="window.location.href=\'' . $url . '\'">';
    if (!empty($row['poster_url'])) {
        echo '<img src="' . htmlspecialchars($row['poster_url']) . '" alt="" class="me-3" style="height:75px;">';
    }
    echo '<div class="flex-grow-1">';
    echo '<div class="fw-semibold">' . htmlspecialchars($row['titolo']);
    if ($anno) { echo ' (' . $anno . ')'; }
    echo '</div>';
    if (!empty($row['voto'])) {
        echo '<div class="small">Voto: ' . htmlspecialchars($row['voto']) . '</div>';
    }
    if (!empty($row['voto_medio'])) {
        echo '<div class="small">Voto medio: ' . htmlspecialchars($row['voto_medio']) . '</div>';
    }
    if (!empty($row['durata'])) {
        echo '<div class="small">Durata: ' . (int)$row['durata'] . ' min</div>';
    }
    if (!empty($row['data_visto'])) {
        echo '<div class="small">Visto il: ' . htmlspecialchars($row['data_visto']) . '</div>';
    }
    echo '</div>';
    echo '</div>';
}
?>
