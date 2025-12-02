<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$id_salvadanaio = (int)($_POST['id_salvadanaio'] ?? 0);
$importo = isset($_POST['importo_attuale']) ? (float)$_POST['importo_attuale'] : null;
$now = date('Y-m-d H:i:s');

if (!$id_salvadanaio || $importo === null) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare('UPDATE salvadanai SET importo_attuale = ?, data_aggiornamento_manuale = ? WHERE id_salvadanaio = ?');
$stmt->bind_param('dsi', $importo, $now, $id_salvadanaio);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
