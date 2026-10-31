<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
require_once '../includes/permissions.php';
if (!has_permission($conn, 'ajax:import_lista_spesa', 'insert')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Accesso negato']); exit; }
$itemsRaw = trim($_POST['items'] ?? '');
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$itemsRaw = trim($itemsRaw);
if ($itemsRaw === '' || !$idFamiglia) { echo json_encode(['success'=>false,'error'=>'Inserisci almeno un elemento da importare.']); exit; }
// Formato JSON del plugin Lista della spesa, con fallback al vecchio formato testuale.
$decoded = json_decode($itemsRaw, true);
$jsonItems = null;
if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['version'], $decoded['items'])) {
    if ($decoded['version'] !== 1 || !is_array($decoded['items']) || !array_is_list($decoded['items'])) {
        echo json_encode(['success'=>false,'error'=>'Formato JSON non valido: version deve essere 1 e items un elenco.']); exit;
    }
    $validDays = ['Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato','Domenica'];
    $jsonItems = [];
    foreach ($decoded['items'] as $index => $item) {
        if (!is_array($item) || !is_string($item['nome'] ?? null) || !is_string($item['quantita'] ?? null) || !is_array($item['associazioni'] ?? null) || !$item['associazioni']) {
            echo json_encode(['success'=>false,'error'=>'Elemento JSON non valido alla posizione '.($index+1)]); exit;
        }
        $nome = trim($item['nome']);
        $quantita = trim($item['quantita']);
        if ($nome === '') { echo json_encode(['success'=>false,'error'=>'Nome ingrediente vuoto']); exit; }
        $associazioni = [];
        foreach ($item['associazioni'] as $ref) {
            if (!is_array($ref) || !is_string($ref['giorno'] ?? null) || !is_string($ref['piatto'] ?? null) || !in_array($ref['giorno'], $validDays, true) || trim($ref['piatto']) === '') {
                echo json_encode(['success'=>false,'error'=>'Associazione giorno/piatto non valida per '.$nome]); exit;
            }
            $associazioni[] = trim($ref['piatto']).', '.$ref['giorno'];
        }
        $jsonItems[] = ['nome'=>$nome, 'quantita'=>$quantita ?: null, 'note'=>implode('; ', array_unique($associazioni))];
    }
    if (!$jsonItems) { echo json_encode(['success'=>false,'error'=>'Il JSON non contiene ingredienti']); exit; }
} elseif (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
    echo json_encode(['success'=>false,'error'=>'JSON non riconosciuto: servono version e items']); exit;
}
$lines = $jsonItems === null ? array_filter(array_map('trim', preg_split("/[\\r\\n]+/", $itemsRaw))) : [];
if ($jsonItems === null && empty($lines)) { echo json_encode(['success'=>false,'error'=>'Nessun elemento trovato nelle righe inserite.']); exit; }
$stmt = $conn->prepare('INSERT INTO lista_spesa (id_famiglia, nome, quantita, note) VALUES (?, ?, ?, ?)');
$nome = '';
$quantita = null;
$note = null;
$stmt->bind_param('isss', $idFamiglia, $nome, $quantita, $note);
$ok = true;
$invalidLines = [];
$itemsToInsert = [];
foreach ($lines as $index => $line) {
    $nome = trim($line);
    // Accetta elenchi Markdown e parentesi con escape (es. \\[...\\]).
    $nome = preg_replace('/^[-*+]\\s+/u', '', $nome);
    $nome = str_replace(['\\\\[', '\\\\]', '\\\\(', '\\\\)'], ['[', ']', '(', ')'], $nome);
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

    if ($nome === '') {
        $invalidLines[] = $index + 1;
        continue;
    }
    $itemsToInsert[] = [
        'nome' => $nome,
        'quantita' => $quantita,
        'note' => $note,
    ];
}
if ($jsonItems !== null) { $itemsToInsert = $jsonItems; }
if (!empty($invalidLines)) {
    echo json_encode([
        'success' => false,
        'error' => 'Alcune righe non contengono un nome valido (righe: ' . implode(', ', $invalidLines) . ').'
    ]);
    exit;
}
if (empty($itemsToInsert)) {
    echo json_encode(['success' => false, 'error' => 'Nessun elemento valido trovato.']);
    exit;
}
foreach ($itemsToInsert as $item) {
    $nome = $item['nome'];
    $quantita = $item['quantita'];
    $note = $item['note'];
    if (!$stmt->execute()) {
        $ok = false;
        $errorMessage = $stmt->error ?: 'Errore durante il salvataggio degli elementi importati.';
        echo json_encode(['success' => false, 'error' => $errorMessage]);
        exit;
    }
}
echo json_encode(['success' => $ok]);
?>
