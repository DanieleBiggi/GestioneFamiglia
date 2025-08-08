<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$titolo = trim($_POST['titolo'] ?? '');
$descrizione = trim($_POST['descrizione'] ?? '');
$data_evento = $_POST['data_evento'] ?? null;
$ora_evento = $_POST['ora_evento'] ?? null;
$id_tipo_evento = (int)($_POST['id_tipo_evento'] ?? 0);

if ($titolo === '') {
    echo json_encode(['success' => false, 'error' => 'Titolo mancante']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO eventi (titolo, descrizione, data_evento, ora_evento, id_tipo_evento) VALUES (?,?,?,?,?)');
$stmt->bind_param('ssssi', $titolo, $descrizione, $data_evento, $ora_evento, $id_tipo_evento);
$ok = $stmt->execute();

if ($ok) {
    echo json_encode(['success' => true, 'id' => $conn->insert_id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Errore durante l\'inserimento']);
}
