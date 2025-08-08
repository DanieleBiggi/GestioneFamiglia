<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/permissions.php';
include 'includes/header.php';

$idUtente = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$data = [
    'nome_mezzo' => '',
    'data_immatricolazione' => '',
    'attivo' => 1,
    'id_mezzo' => 0,
    'id_utente' => $idUtente
];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM mezzi WHERE id_mezzo = ? AND id_famiglia = ?");
    $stmt->bind_param('ii', $id, $idFamiglia);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $data = $res->fetch_assoc();
    } else {
        echo '<p class="text-danger">Record non trovato.</p>';
        include 'includes/footer.php';
        exit;
    }
}
$isOwner = ($data['id_utente'] ?? $idUtente) == $idUtente;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_mezzo = isset($_POST['id_mezzo']) ? (int)$_POST['id_mezzo'] : 0;
    $nome_mezzo = $_POST['nome_mezzo'] ?? '';
    $data_immatricolazione = $_POST['data_immatricolazione'] ?? '';
    $attivo = isset($_POST['attivo']) ? 1 : 0;

    if ($id_mezzo > 0) {
        $stmt = $conn->prepare("SELECT id_utente FROM mezzi WHERE id_mezzo=? AND id_famiglia=?");
        $stmt->bind_param('ii', $id_mezzo, $idFamiglia);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if (!$row || (int)$row['id_utente'] !== $idUtente) {
            echo '<p class="text-danger">Operazione non autorizzata.</p>';
            include 'includes/footer.php';
            exit;
        }
        $stmt = $conn->prepare("UPDATE mezzi SET nome_mezzo=?, data_immatricolazione=?, attivo=? WHERE id_mezzo=? AND id_famiglia=?");
        $stmt->bind_param('ssiii', $nome_mezzo, $data_immatricolazione, $attivo, $id_mezzo, $idFamiglia);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO mezzi (id_utente, id_famiglia, nome_mezzo, data_immatricolazione, attivo) VALUES (?,?,?,?,?)");
        $stmt->bind_param('iissi', $idUtente, $idFamiglia, $nome_mezzo, $data_immatricolazione, $attivo);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: mezzi.php');
    exit;
}
?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-4">Dettaglio Mezzo</h4>
</div>
<form method="post" class="bg-dark text-white p-3 rounded">
  <div class="mb-3">
    <label class="form-label">Nome mezzo</label>
    <input type="text" name="nome_mezzo" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['nome_mezzo']) ?>" <?= $isOwner ? '' : 'disabled' ?>>
  </div>
  <div class="mb-3">
    <label class="form-label">Data immatricolazione</label>
    <input type="date" name="data_immatricolazione" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['data_immatricolazione']) ?>" <?= $isOwner ? '' : 'disabled' ?>>
  </div>
  <div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" id="attivo" name="attivo" <?= $data['attivo'] ? 'checked' : '' ?> <?= $isOwner ? '' : 'disabled' ?>>
    <label class="form-check-label" for="attivo">Attivo</label>
  </div>
  <input type="hidden" name="id_utente" value="<?= (int)$idUtente ?>">
  <input type="hidden" name="id_famiglia" value="<?= (int)$idFamiglia ?>">
  <?php if ($data['id_mezzo']): ?>
    <input type="hidden" name="id_mezzo" value="<?= (int)$data['id_mezzo'] ?>">
  <?php endif; ?>
  <?php if ($isOwner): ?>
    <button type="submit" class="btn btn-primary w-100">Salva</button>
  <?php endif; ?>
</form>
<?php include 'includes/footer.php'; ?>
