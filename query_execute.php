<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/utility.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
header('Content-Type: application/json');

if ($id <= 0) {
    echo json_encode(['error' => 'ID mancante']);
    exit;
}

$stmt = $conn->prepare('SELECT stringa_da_completare, parametri FROM dati_remoti WHERE id_dato_remoto = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['error' => 'Record non trovato']);
    exit;
}

$SQLinv = $row['stringa_da_completare'] ?? '';
$parametri = json_decode($row['parametri'] ?? '', true);
if (is_array($parametri)) {
    foreach ($parametri as $chiave => $valore) {
        $SQLinv = str_replace('[[' . $chiave . ']]', $valore, $SQLinv);
    }
}

$token = Encrypt(microtime(true) . rand(1000, 9999), 'test');
$SQLen = Encrypt(htmlspecialchars_decode($SQLinv), 'test');
$url = 'https://new.cosulich.it/approvazione_fatture/user_get_inaz_json.php?action=execute_query&token=' . $token . '&SQL=' . $SQLen;
$response = @file_get_contents($url);
$risposta = json_decode($response, true);

echo json_encode([
    'query' => $SQLinv,
    'risposta' => $risposta,
    'url' => $url
]);
