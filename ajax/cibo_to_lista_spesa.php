<?php
header('Content-Type: application/json');
include '../includes/session_check.php';
include '../includes/db.php';
include '../includes/permissions.php';

$id_evento = (int)($_POST['id_evento'] ?? 0);
$famiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
if(!$id_evento || !$famiglia){
    echo json_encode(['success'=>false,'error'=>'Dati non validi']);
    exit;
}

// svuota lista esistente
$stmt = $conn->prepare('DELETE FROM lista_spesa WHERE id_famiglia = ?');
$stmt->bind_param('i', $famiglia);
if(!$stmt->execute()){
    echo json_encode(['success'=>false]);
    exit;
}
$stmt->close();

// recupera cibo dell'evento
$stmt = $conn->prepare('SELECT c.piatto, e2c.quantita, c.um FROM eventi_eventi2cibo e2c JOIN eventi_cibo c ON e2c.id_cibo = c.id WHERE e2c.id_evento = ?');
$stmt->bind_param('i', $id_evento);
$stmt->execute();
$res = $stmt->get_result();
$items = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if($items){
    $stmtIns = $conn->prepare('INSERT INTO lista_spesa (id_famiglia, nome, quantita) VALUES (?,?,?)');
    $stmtIns->bind_param('iss', $famiglia, $nome, $quantita);
    foreach($items as $it){
        $nome = $it['piatto'];
        $quantita = $it['quantita'] !== null ? trim($it['quantita'] . ' ' . $it['um']) : '';
        if(!$stmtIns->execute()){
            $stmtIns->close();
            echo json_encode(['success'=>false]);
            exit;
        }
    }
    $stmtIns->close();
}

echo json_encode(['success'=>true]);
