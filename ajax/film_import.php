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

// Never overwrite an existing user's viewing date, rating or comments.
$idUtente = (int)($_SESSION['utente_id'] ?? 0);
$stmtExisting = $conn->prepare("SELECT f.id_film FROM film f JOIN film_utenti fu ON fu.id_film=f.id_film WHERE f.tmdb_id=? AND fu.id_utente=? LIMIT 1");
$stmtExisting->bind_param('ii', $tmdbId, $idUtente);
$stmtExisting->execute();
$existing = $stmtExisting->get_result()->fetch_assoc();
$stmtExisting->close();
if ($existing) {
    echo json_encode(['success' => true, 'already_present' => true, 'id_film' => (int)$existing['id_film']]);
    exit;
}

$apiKey =  $config['TMDB_API_KEY'];
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
    // Insert film if not exists
    $stmt = $conn->prepare("SELECT id_film FROM film WHERE tmdb_id=?");
    $stmt->bind_param('i', $tmdbId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $idFilm = (int)$row['id_film'];
    } else {
        $stmtIns = $conn->prepare("INSERT INTO film (tmdb_id, titolo, titolo_originale, anno, durata, trama, poster_url, lingua_originale, voto_medio, regista, budget, incassi, trailer_ita_url) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $anno = substr($movie['release_date'] ?? '', 0, 4);
        $poster = isset($movie['poster_path']) && $movie['poster_path'] !== '' ? 'https://image.tmdb.org/t/p/w500' . $movie['poster_path'] : null;
        $stmtIns->bind_param('isssisssdsiis', $tmdbId, $movie['title'], $movie['original_title'], $anno, $movie['runtime'], $movie['overview'], $poster, $movie['original_language'], $votoMedio, $regista, $budget, $incassi, $trailerIta);
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
    // TMDB metadata for an existing global film is intentionally left unchanged.

    // Insert/Update film_utenti
    // The current user's association is new; never update existing personal data.
    $stmtFU = $conn->prepare("INSERT IGNORE INTO film_utenti (id_film, id_utente, data_visto, voto) VALUES (?,?,?,?)");
    $stmtFU->bind_param('iisd', $idFilm, $idUtente, $dataVisto, $voto);
    $stmtFU->execute();
    $newAssociation = $stmtFU->affected_rows > 0;
    $stmtFU->close();

    if ($newAssociation && $commento !== '') {
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
