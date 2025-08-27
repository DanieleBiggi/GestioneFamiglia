<?php
include '../includes/session_check.php';
include '../includes/db.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
$idFilm = (int)($input['id_film'] ?? 0);
if (!$idFilm) {
    echo json_encode(['success' => false, 'error' => 'ID mancante']);
    exit;
}

$stmt = $conn->prepare("SELECT tmdb_id FROM film WHERE id_film=?");
$stmt->bind_param('i', $idFilm);
$stmt->execute();
$res = $stmt->get_result();
if (!($row = $res->fetch_assoc()) || !$row['tmdb_id']) {
    echo json_encode(['success' => false, 'error' => 'TMDB ID non trovato']);
    exit;
}
$tmdbId = (int)$row['tmdb_id'];
$stmt->close();

$apiKey = $config['TMDB_API_KEY'] ?? null;
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
    $anno = substr($movie['release_date'] ?? '', 0, 4);
    $poster = isset($movie['poster_path']) && $movie['poster_path'] !== '' ? 'https://image.tmdb.org/t/p/w500' . $movie['poster_path'] : null;
    $stmtUp = $conn->prepare("UPDATE film SET titolo=?, titolo_originale=?, anno=?, durata=?, trama=?, poster_url=?, lingua_originale=? WHERE id_film=?");
    $stmtUp->bind_param('sssisssi', $movie['title'], $movie['original_title'], $anno, $movie['runtime'], $movie['overview'], $poster, $movie['original_language'], $idFilm);
    $stmtUp->execute();
    $stmtUp->close();

    $stmtDel = $conn->prepare("DELETE FROM film2generi WHERE id_film=?");
    $stmtDel->bind_param('i', $idFilm);
    $stmtDel->execute();
    $stmtDel->close();

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

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
