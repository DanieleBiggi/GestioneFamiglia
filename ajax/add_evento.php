<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$titolo = trim($_POST['titolo'] ?? '');
$descrizione = trim($_POST['descrizione'] ?? '');
$data_evento = $_POST['data_evento'] ?? null;
$ora_evento = $_POST['ora_evento'] ?? null;
$id_tipo_evento = (int)($_POST['id_tipo_evento'] ?? 0);
$famiglie = $_POST['famiglie'] ?? [];
if (!is_array($famiglie)) { $famiglie = []; }

if ($titolo === '') {
    echo json_encode(['success' => false, 'error' => 'Titolo mancante']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO eventi (titolo, descrizione, data_evento, ora_evento, id_tipo_evento) VALUES (?,?,?,?,?)');
$stmt->bind_param('ssssi', $titolo, $descrizione, $data_evento, $ora_evento, $id_tipo_evento);
$ok = $stmt->execute();
$eventoId = $conn->insert_id;
$stmt->close();

if ($ok && $famiglie) {
    $stmtFam = $conn->prepare('INSERT INTO eventi_eventi2famiglie (id_evento, id_famiglia) VALUES (?,?)');
    foreach ($famiglie as $fid) {
        $fid = (int)$fid;
        if ($fid > 0) {
            $stmtFam->bind_param('ii', $eventoId, $fid);
            $stmtFam->execute();
        }
    }
    $stmtFam->close();
}

if ($ok) {
    echo json_encode(['success' => true, 'id' => $eventoId]);
} else {
    echo json_encode(['success' => false, 'error' => 'Errore durante l\'inserimento']);
}
