<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

// Funzione helper: restituisce int se > 0, altrimenti null
function int_or_null($val) {
    $val = trim($val ?? '');
    if (is_numeric($val) && (int)$val > 0) {
        return (int)$val;
    }
    return null;
}

$id_evento      = int_or_null($_POST['id_evento'] ?? null);
$id_salvadanaio = int_or_null($_POST['id_salvadanaio'] ?? null);
$id_etichetta   = int_or_null($_POST['id_etichetta'] ?? null);

// Validazione: almeno 2 dei 3 devono essere valorizzati
if (
    ($id_salvadanaio !== null ? 0 : 1) +
    ($id_etichetta   !== null ? 0 : 1) +
    ($id_evento      !== null ? 0 : 1) > 1
) {
    echo json_encode(['success' => false]);
    exit;
}

// Query
$stmt = $conn->prepare('
    INSERT INTO eventi_eventi2salvadanai_etichette (id_evento, id_salvadanaio, id_etichetta)
    VALUES (?, ?, ?)
');
$stmt->bind_param('iii', $id_evento, $id_salvadanaio, $id_etichetta);
$success = $stmt->execute();
$stmt->close();

// Debug SQL
$query_debug = sprintf(
    "INSERT INTO eventi_eventi2salvadanai_etichette (id_evento, id_salvadanaio, id_etichetta) VALUES (%s, %s, %s)",
    $id_evento      !== null ? $id_evento : 'NULL',
    $id_salvadanaio !== null ? $id_salvadanaio : 'NULL',
    $id_etichetta   !== null ? $id_etichetta : 'NULL'
);
error_log("DEBUG SQL: $query_debug");

echo json_encode(['success' => $success, 'debug' => $query_debug]);
