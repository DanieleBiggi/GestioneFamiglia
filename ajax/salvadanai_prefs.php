<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$idUtente = $_SESSION['utente_id'] ?? 0;
if (!$idUtente) {
    echo json_encode(['success' => false]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$hidden = $data['hidden'] ?? [];
$preferito = isset($data['preferito']) && $data['preferito'] !== null ? intval($data['preferito']) : null;

$conn->begin_transaction();
$stmtReset = $conn->prepare('UPDATE utenti2salvadanai SET nascosto = 0, preferito = 0 WHERE id_utente = ?');
$stmtReset->bind_param('i', $idUtente);
$stmtReset->execute();

if (!empty($hidden)) {
    $stmtHide = $conn->prepare('INSERT INTO utenti2salvadanai (id_utente, id_salvadanaio, nascosto, preferito) VALUES (?, ?, 1, 0)
        ON DUPLICATE KEY UPDATE nascosto = 1, preferito = 0');
    foreach ($hidden as $h) {
        $id = intval($h);
        $stmtHide->bind_param('ii', $idUtente, $id);
        $stmtHide->execute();
    }
}

if ($preferito) {
    $stmtFav = $conn->prepare('INSERT INTO utenti2salvadanai (id_utente, id_salvadanaio, nascosto, preferito) VALUES (?, ?, 0, 1)
        ON DUPLICATE KEY UPDATE preferito = 1, nascosto = 0');
    $stmtFav->bind_param('ii', $idUtente, $preferito);
    $stmtFav->execute();
}

$conn->commit();

echo json_encode(['success' => true]);
