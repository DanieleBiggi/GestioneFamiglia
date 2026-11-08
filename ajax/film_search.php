<?php
include '../includes/session_check.php';
include '../includes/db.php';
header('Content-Type: application/json; charset=utf-8');
$q = trim((string)($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) > 150) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Inserisci un titolo valido.']);
    exit;
}
$apiKey = $config['TMDB_API_KEY'] ?? null;
if (!$apiKey) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Servizio TMDB non configurato.']);
    exit;
}
$url = 'https://api.themoviedb.org/3/search/movie?' . http_build_query([
    'api_key' => $apiKey, 'language' => 'it-IT', 'query' => $q, 'include_adult' => 'false'
]);
$body = false;
$status = 0;
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    if (isset($http_response_header[0]) && preg_match('/HTTP\\/\\S+\\s+(\\d+)/', $http_response_header[0], $matches)) {
        $status = (int)$matches[1];
    }
}
$data = $body ? json_decode($body, true) : null;
if ($status !== 200 || !is_array($data) || !isset($data['results']) || !is_array($data['results'])) {
    error_log('TMDB search failed: HTTP ' . $status);
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Ricerca TMDB temporaneamente non disponibile. Riprova tra poco.']);
    exit;
}
$idUtente = (int)($_SESSION['utente_id'] ?? 0);
$stmt = $conn->prepare('SELECT f.id_film FROM film f JOIN film_utenti fu ON f.id_film=fu.id_film WHERE f.tmdb_id=? AND fu.id_utente=? LIMIT 1');
$results = [];
foreach ($data['results'] as $movie) {
    $tmdbId = (int)($movie['id'] ?? 0);
    if (!$tmdbId) continue;
    $stmt->bind_param('ii', $tmdbId, $idUtente);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $results[] = [
        'id' => $tmdbId,
        'title' => (string)($movie['title'] ?? ''),
        'release_date' => (string)($movie['release_date'] ?? ''),
        'existing_film_id' => $existing ? (int)$existing['id_film'] : null
    ];
}
$stmt->close();
echo json_encode(['success' => true, 'results' => $results]);
