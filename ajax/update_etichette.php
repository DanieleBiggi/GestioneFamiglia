<?php
include '../includes/session_check.php';
include '../includes/db.php';

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data['id'] ?? 0);
$etichette = $data['etichette'] ?? [];

if (!$id) {
  http_response_code(400);
  echo "ID non valido";
  exit;
}

// Cancella le etichette precedenti
$stmt = $conn->prepare("DELETE FROM bilancio_etichette2operazioni WHERE tabella_operazione = 'movimenti_revolut' AND id_tabella = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

// Inserisce le nuove etichette
if (!empty($etichette)) {
  $insert = $conn->prepare("INSERT INTO bilancio_etichette2operazioni (tabella_operazione, id_tabella, id_etichetta) VALUES ('movimenti_revolut', ?, ?)");
  foreach ($etichette as $e) {
    $ide = intval($e);
    $insert->bind_param("ii", $id, $ide);
    $insert->execute();
  }
}

echo "ok";