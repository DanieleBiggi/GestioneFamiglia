<?php
include '../includes/session_check.php';
include '../includes/db.php';

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data['id'] ?? 0);
$etichette = $data['etichette'] ?? [];
$src = $data['src'] ?? 'movimenti_revolut';

$allowedSources = ['movimenti_revolut', 'bilancio_entrate', 'bilancio_uscite'];
if (!$id || !in_array($src, $allowedSources, true)) {
  http_response_code(400);
  echo "Dati non validi";
  exit;
}

// Cancella le etichette precedenti
$stmt = $conn->prepare("DELETE FROM bilancio_etichette2operazioni WHERE tabella_operazione = ? AND id_tabella = ?");
$stmt->bind_param("si", $src, $id);
$stmt->execute();

// Inserisce le nuove etichette
if (!empty($etichette)) {
  $insert = $conn->prepare("INSERT INTO bilancio_etichette2operazioni (tabella_operazione, id_tabella, id_etichetta) VALUES (?, ?, ?)");
  foreach ($etichette as $e) {
    $ide = intval($e);
    $insert->bind_param("sii", $src, $id, $ide);
    $insert->execute();
  }
}

echo "ok";

