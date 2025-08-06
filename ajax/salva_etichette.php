<?php
require_once '../includes/db.php';

$id = $_POST['id'];
$etichette = $_POST['etichette'] ?? [];

// Elimina le esistenti
$conn->query("DELETE FROM bilancio_etichette2operazioni WHERE tabella_operazione = 'movimenti_revolut' AND id_tabella = $id");

// Inserisce le nuove
foreach ($etichette as $id_etic) {
    $stmt = $conn->prepare("INSERT INTO bilancio_etichette2operazioni (tabella_operazione, id_tabella, id_etichetta) VALUES ('movimenti_revolut', ?, ?)");
    $stmt->bind_param("ii", $id, $id_etic);
    $stmt->execute();
}

// Ritorna i nomi delle etichette selezionate
if (count($etichette) > 0) {
    $placeholders = implode(',', array_fill(0, count($etichette), '?'));
    $types = str_repeat('i', count($etichette));
    $stmt = $conn->prepare("SELECT GROUP_CONCAT(descrizione SEPARATOR ', ') AS etichette FROM bilancio_etichette WHERE id_etichetta IN ($placeholders)");
    $stmt->bind_param($types, ...$etichette);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    echo $result['etichette'];
} else {
    echo '— Nessuna —';
}