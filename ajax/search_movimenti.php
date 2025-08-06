<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/render_movimento.php';

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    exit;
}

$like = "%" . $q . "%";
$stmt = $conn->prepare("SELECT id_movimento_revolut, started_date, amount, COALESCE(NULLIF(descrizione_extra, ''), description) AS descrizione, etichette FROM v_movimenti_revolut WHERE descrizione_extra LIKE ? OR description LIKE ? OR gruppo LIKE ? OR etichette LIKE ? ORDER BY started_date DESC LIMIT 50");
$stmt->bind_param('ssss', $like, $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();

while ($mov = $result->fetch_assoc()) {
    render_movimento($mov);
}
