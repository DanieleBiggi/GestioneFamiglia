<?php
include '../includes/session_check.php';
include '../includes/db.php';
header('Content-Type: application/json');

$idUtente = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$idMezzo = (int)($_POST['id_mezzo'] ?? 0);
$nome = $_POST['nome_tagliando'] ?? '';
$mesiImm = (int)($_POST['mesi_da_immatricolazione'] ?? 0);
$mesiPrec = (int)($_POST['mesi_da_precedente_tagliando'] ?? 0);
$maxKm = (int)($_POST['massimo_km_tagliando'] ?? 0);
$freqMesi = (int)($_POST['frequenza_mesi'] ?? 0);
$freqKm = (int)($_POST['frequenza_km'] ?? 0);

$stmt = $conn->prepare("SELECT id_utente FROM mezzi WHERE id_mezzo=? AND id_famiglia=?");
$stmt->bind_param('ii', $idMezzo, $idFamiglia);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row || (int)$row['id_utente'] !== $idUtente) {
    echo json_encode(['success' => false, 'error' => 'Operazione non autorizzata']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO mezzi_tagliandi (id_mezzo, id_famiglia, id_utente, mesi_da_immatricolazione, mesi_da_precedente_tagliando, massimo_km_tagliando, frequenza_mesi, frequenza_km, nome_tagliando) VALUES (?,?,?,?,?,?,?,?,?)");
$stmt->bind_param('iiiiiiiis', $idMezzo, $idFamiglia, $idUtente, $mesiImm, $mesiPrec, $maxKm, $freqMesi, $freqKm, $nome);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok]);
