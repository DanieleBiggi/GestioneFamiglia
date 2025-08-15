<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

function int_or_null($val) {
    $val = trim($val ?? '');
    if (is_numeric($val) && (int)$val > 0) {
        return (int)$val;
    }
    return null;
}

$id_e2se = (int)($_POST['id_e2se'] ?? 0);

$id_evento      = int_or_null($_POST['id_evento'] ?? null);
$id_salvadanaio = int_or_null($_POST['id_salvadanaio'] ?? null);
$id_etichetta   = int_or_null($_POST['id_etichetta'] ?? null);

if (
    !$id_e2se || 
    (
        empty($id_salvadanaio) + empty($id_etichetta) + empty($id_evento) > 1
    )
) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare('UPDATE eventi_eventi2salvadanai_etichette SET id_evento=?, id_salvadanaio=?, id_etichetta=? WHERE id_e2se=?');
$stmt->bind_param('iiii', $id_evento, $id_salvadanaio, $id_etichetta, $id_e2se);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
