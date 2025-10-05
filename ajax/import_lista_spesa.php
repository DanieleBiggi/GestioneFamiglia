<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:import_lista_spesa', 'insert')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$itemsRaw = trim($_POST['items'] ?? '');
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
if ($itemsRaw === '' || !$idFamiglia) { echo json_encode(['success'=>false,'error'=>'Dati non validi']); exit; }
$lines = array_filter(array_map('trim', preg_split("/[\r\n]+/", $itemsRaw)));
if (empty($lines)) { echo json_encode(['success'=>false,'error'=>'Nessun elemento']); exit; }
$stmt = $conn->prepare('INSERT INTO lista_spesa (id_famiglia, nome, quantita, note) VALUES (?, ?, ?, ?)');
$nome = '';
$quantita = null;
$note = null;
$stmt->bind_param('isss', $idFamiglia, $nome, $quantita, $note);
$ok = true;
foreach ($lines as $line) {
    $nome = trim($line);
    $quantita = null;
    $note = null;

    if ($nome === '') { continue; }

    if (preg_match('/\[(.*?)\]\s*$/', $nome, $noteMatch)) {
        $note = trim($noteMatch[1]);
        $nome = trim(substr($nome, 0, -strlen($noteMatch[0])));
    }

    if (preg_match('/\(([^()]*)\)\s*$/', $nome, $qtyMatch)) {
        $quantita = trim($qtyMatch[1]);
        $nome = trim(substr($nome, 0, -strlen($qtyMatch[0])));
    }

    if (($note === null || $note === '') && preg_match('/\[(.*?)\]\s*$/', $nome, $noteMatch)) {
        $note = trim($noteMatch[1]);
        $nome = trim(substr($nome, 0, -strlen($noteMatch[0])));
    }

    if ($quantita === '') { $quantita = null; }
    if ($note === '') { $note = null; }

    if ($nome === '') { continue; }
    if (!$stmt->execute()) { $ok = false; break; }
}
echo json_encode(['success'=>$ok]);
?>
