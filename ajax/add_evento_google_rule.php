<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
include '../includes/permissions.php';
if (!has_permission($conn, 'table:eventi_google_rules', 'insert')) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Accesso negato']);
    exit;
}
$idEvento = isset($_POST['id_evento']) ? (int)$_POST['id_evento'] : 0;
if (!$idEvento) {
    echo json_encode(['success'=>false,'error'=>'ID evento mancante']);
    exit;
}
$stmt = $conn->prepare('SELECT creator_email, id_tipo_evento, descrizione FROM eventi WHERE id=?');
$stmt->bind_param('i', $idEvento);
$stmt->execute();
$res = $stmt->get_result();
$ev = $res->fetch_assoc();
$stmt->close();
if (!$ev || empty($ev['creator_email'])) {
    echo json_encode(['success'=>false,'error'=>'Dati evento non validi']);
    exit;
}
$idTipo = $ev['id_tipo_evento'] ? (int)$ev['id_tipo_evento'] : null;
$inv = [];
$invStmt = $conn->prepare('SELECT id_invitato FROM eventi_eventi2invitati WHERE id_evento=?');
$invStmt->bind_param('i', $idEvento);
$invStmt->execute();
$invRes = $invStmt->get_result();
while ($row = $invRes->fetch_assoc()) { $inv[] = (int)$row['id_invitato']; }
$invStmt->close();
if (!$idTipo && !$inv) {
    echo json_encode(['success'=>false,'error'=>'Nessun tipo evento o invitati']);
    exit;
}
$stmt = $conn->prepare('INSERT INTO eventi_google_rules (creator_email, description_keyword, id_tipo_evento, attiva) VALUES (?,?,?,1)');
$creatorEmail = $ev['creator_email'];
$descrizione = $ev['descrizione'];
$stmt->bind_param('ssi', $creatorEmail, $descrizione, $idTipo);
$stmt->execute();
$ruleId = $stmt->insert_id;
$stmt->close();
if ($inv) {
    $insInv = $conn->prepare('INSERT INTO eventi_google_rules_invitati (id_rule, id_invitato) VALUES (?,?)');
    foreach ($inv as $idInv) {
        $insInv->bind_param('ii', $ruleId, $idInv);
        $insInv->execute();
    }
    $insInv->close();
}
echo json_encode(['success'=>true]);
