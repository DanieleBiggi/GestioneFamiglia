<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';

$idUtente = $_SESSION['utente_id'];

$sql = "SELECT id_caricamento, nome_file, data_scontrino, totale_scontrino, descrizione
        FROM ocr_caricamenti c
        WHERE id_utente = ?
          AND id_caricamento NOT IN (
            SELECT id_caricamento FROM bilancio_entrate WHERE id_caricamento IS NOT NULL
            UNION
            SELECT id_caricamento FROM bilancio_uscite WHERE id_caricamento IS NOT NULL
            UNION
            SELECT id_caricamento FROM movimenti_revolut WHERE id_caricamento IS NOT NULL
          )
        ORDER BY data_caricamento DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $idUtente);
$stmt->execute();
$result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($result);
