<?php
include '../includes/session_check.php';
include '../includes/db.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
$tmdbId = (int)($input['tmdb_id'] ?? 0);
$dataVisto = isset($input['data_visto']) && $input['data_visto'] !== '' ? $input['data_visto'] : null;
$voto = isset($input['voto']) && $input['voto'] !== '' ? (float)$input['voto'] : null;
$commento = isset($input['commento']) ? trim($input['commento']) : '';

if (!$tmdbId) {
    echo json_encode(['success' => false, 'error' => 'ID mancante']);
    exit;
}

$apiKey =  $config['TMDB_API_KEY'];
if (!$apiKey) {
    echo json_encode(['success' => false, 'error' => 'TMDB_API_KEY non configurato']);
    exit;
}

$detailsJson = @file_get_contents("https://api.themoviedb.org/3/movie/{$tmdbId}?api_key={$apiKey}&language=it-IT");
if (!$detailsJson) {
    echo json_encode(['success' => false, 'error' => 'Errore chiamata TMDB']);
    exit;
}
$movie = json_decode($detailsJson, true);

$conn->begin_transaction();
try {
    // Insert film if not exists
    $stmt = $conn->prepare("SELECT id_film FROM film WHERE tmdb_id=?");
    $stmt->bind_param('i', $tmdbId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $idFilm = (int)$row['id_film'];
    } else {
        $stmtIns = $conn->prepare("INSERT INTO film (tmdb_id, titolo, titolo_originale, anno, durata, trama, poster_url, lingua_originale) VALUES (?,?,?,?,?,?,?,?)");
        $anno = substr($movie['release_date'] ?? '', 0, 4);
        $poster = isset($movie['poster_path']) && $movie['poster_path'] !== '' ? 'https://image.tmdb.org/t/p/w500' . $movie['poster_path'] : null;
        $stmtIns->bind_param('isssisss', $tmdbId, $movie['title'], $movie['original_title'], $anno, $movie['runtime'], $movie['overview'], $poster, $movie['original_language']);
        $stmtIns->execute();
        $idFilm = $stmtIns->insert_id;
        $stmtIns->close();
        if (!empty($movie['genres'])) {
            foreach ($movie['genres'] as $g) {
                $gid = (int)$g['id'];
                $gname = $g['name'];
                $stmtG = $conn->prepare("INSERT IGNORE INTO film_generi (id_genere, nome) VALUES (?,?)");
                $stmtG->bind_param('is', $gid, $gname);
                $stmtG->execute();
                $stmtG->close();
                $stmtFG = $conn->prepare("INSERT IGNORE INTO film2generi (id_film, id_genere) VALUES (?,?)");
                $stmtFG->bind_param('ii', $idFilm, $gid);
                $stmtFG->execute();
                $stmtFG->close();
            }
        }
    }
    $stmt->close();

    // Insert/Update film_utenti
    $idUtente = $_SESSION['utente_id'];
    $stmtFU = $conn->prepare("INSERT INTO film_utenti (id_film, id_utente, data_visto, voto) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE data_visto=VALUES(data_visto), voto=VALUES(voto)");
    $stmtFU->bind_param('iisd', $idFilm, $idUtente, $dataVisto, $voto);
    $stmtFU->execute();
    $stmtFU->close();

    if ($commento !== '') {
        $stmtC = $conn->prepare("INSERT INTO film_commenti (id_film, id_utente, commento) VALUES (?,?,?)");
        $stmtC->bind_param('iis', $idFilm, $idUtente, $commento);
        $stmtC->execute();
        $stmtC->close();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'id_film' => $idFilm]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
