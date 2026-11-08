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

$url = "https://api.themoviedb.org/3/movie/{$tmdbId}?" . http_build_query(['api_key' => $apiKey, 'language' => 'it-IT', 'append_to_response' => 'credits,videos']);
$detailsJson = false;
$httpStatus = 0;
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false]);
    $detailsJson = curl_exec($ch);
    $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $context = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
    $detailsJson = @file_get_contents($url, false, $context);
    if (isset($http_response_header[0]) && preg_match('/HTTP\\/\\S+\\s+(\\d+)/', $http_response_header[0], $matches)) {
        $httpStatus = (int)$matches[1];
    }
}
if ($httpStatus !== 200 || !$detailsJson) {
    error_log('TMDB movie details failed: HTTP ' . $httpStatus . ', movie ID ' . $tmdbId);
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Impossibile recuperare i dettagli del film da TMDB. Riprova tra poco.']);
    exit;
}

$movie = json_decode($detailsJson, true);
if (!is_array($movie) || empty($movie['id']) || empty($movie['title'])) {
    error_log('Invalid TMDB movie response for movie ID ' . $tmdbId);
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'TMDB ha restituito dati non validi. Riprova tra poco.']);
    exit;
}
$votoMedio = isset($movie['vote_average']) ? (float)$movie['vote_average'] : null;
$budget = isset($movie['budget']) ? (int)$movie['budget'] : null;
$incassi = isset($movie['revenue']) ? (int)$movie['revenue'] : null;
$trailerIta = null;
if (!empty($movie['videos']['results'])) {
    foreach ($movie['videos']['results'] as $video) {
        if ($video['type'] === 'Trailer' && $video['site'] === 'YouTube' && $video['iso_639_1'] === 'it') {
            $trailerIta = 'https://www.youtube.com/watch?v=' . $video['key'];
            break;
        }
    }
}
$regista = null;
if (!empty($movie['credits']['crew'])) {
    $directors = array_filter($movie['credits']['crew'], function ($member) {
        return $member['job'] === 'Director';
    });
    $names = array_column($directors, 'name');
    if (!empty($names)) {
        $regista = implode(', ', $names);
    }
}

$conn->begin_transaction();
try {
    $anno = substr($movie['release_date'] ?? '', 0, 4);
    $poster = isset($movie['poster_path']) && $movie['poster_path'] !== '' ? 'https://image.tmdb.org/t/p/w500' . $movie['poster_path'] : null;
    $stmtUp = $conn->prepare("UPDATE film SET titolo=?, titolo_originale=?, anno=?, durata=?, trama=?, poster_url=?, lingua_originale=?, voto_medio=?, regista=?, budget=?, incassi=?, trailer_ita_url=? WHERE id_film=?");
    $stmtUp->bind_param('sssisssdsiisi', $movie['title'], $movie['original_title'], $anno, $movie['runtime'], $movie['overview'], $poster, $movie['original_language'], $votoMedio, $regista, $budget, $incassi, $trailerIta, $idFilm);
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
