<?php
function render_film(array $row) {
    $search = strtolower(($row['titolo'] ?? '') . ' ' . ($row['titolo_originale'] ?? ''));
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $anno = (int)($row['anno'] ?? 0);
    $generi = htmlspecialchars($row['generi'] ?? '', ENT_QUOTES);
    $regista = strtolower($row['regista'] ?? '');
    $registaAttr = htmlspecialchars($regista, ENT_QUOTES);
    $gruppoId = (int)($row['id_gruppo'] ?? 0);
    $listeAttr = htmlspecialchars($row['liste'] ?? '', ENT_QUOTES);
    $visto = htmlspecialchars($row['data_visto'] ?? '', ENT_QUOTES);
    $durata = (int)($row['durata'] ?? 0);
    $url = 'film_dettaglio.php?id=' . (int)($row['id_film'] ?? 0);
    $trailer = $row['trailer_ita_url'] ?? '';
    echo '<div class="film-card movement d-flex justify-content-between align-items-start text-white text-decoration-none mb-2 position-relative" data-search="' . $searchAttr . '" data-anno="' . $anno . '" data-generi="' . $generi . '" data-regista="' . $registaAttr . '" data-gruppo="' . $gruppoId . '" data-liste="' . $listeAttr . '" data-visto="' . $visto . '" data-durata="' . $durata . '" onclick="window.location.href=\'' . $url . '\'">';
    if (!empty($row['poster_url'])) {
        echo '<img src="' . htmlspecialchars($row['poster_url']) . '" alt="" class="me-3" style="height:75px;">';
    }
    echo '<div class="flex-grow-1 me-3">';
    echo '<div class="fw-semibold">' . htmlspecialchars($row['titolo']);
    if ($anno) { echo ' (' . $anno . ')'; }
    echo '</div>';
    if (!empty($row['voto'])) {
        echo '<div class="small">Voto: ' . htmlspecialchars($row['voto']) . '</div>';
    }
    if (!empty($row['data_visto'])) {
        echo '<div class="small">Visto il: ' . htmlspecialchars($row['data_visto']) . '</div>';
    }
    $piattaforme = array_filter(explode(',', $row['piattaforme'] ?? ''));
    if (!empty($piattaforme)) {
        echo '<div class="small mt-1">';
        foreach ($piattaforme as $icon) {
            echo '<img src="' . htmlspecialchars($icon) . '" alt="" class="me-1" style="height:20px;">';
        }
        echo '</div>';
    }
    echo '</div>';
    echo '<div class="text-end">';
    if (!empty($row['voto_medio'])) {
        echo '<div class="small">Voto medio: ' . htmlspecialchars($row['voto_medio']) . '</div>';
    }
    if (!empty($row['durata'])) {
        echo '<div class="small">Durata: ' . (int)$row['durata'] . ' min</div>';
    }
    if (!empty($row['gruppo'])) {
        echo '<div class="mt-1"><span class="badge-etichetta film-filter-gruppo" data-gruppo-id="' . $gruppoId . '">' . htmlspecialchars($row['gruppo']) . '</span></div>';
    }
    echo '</div>';
    if (!empty($trailer)) {
        echo '<a href="' . htmlspecialchars($trailer) . '" target="_blank" class="position-absolute bottom-0 end-0 p-2 text-danger" onclick="event.stopPropagation();"><i class="bi bi-youtube"></i></a>';
    }
    echo '</div>';
}
?>
