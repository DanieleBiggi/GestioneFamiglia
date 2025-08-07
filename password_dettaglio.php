<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$idUtente = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$data = [
    'url_login' => '',
    'username' => '',
    'password_account' => '',
    'note' => '',
    'attiva' => 1,
    'condivisa_con_famiglia' => 0,
    'id_account_password' => 0,
    'id_utente' => $idUtente
];

if ($id > 0) {
    $stmt = $conn->prepare(
        "SELECT g.* FROM gestione_account_password g " .
        "JOIN utenti u ON g.id_utente = u.id " .
        "WHERE g.id_account_password = ? AND g.id_famiglia = ? " .
        "AND (g.id_utente = ? OR (g.condivisa_con_famiglia = 1 AND u.id_famiglia_attuale = ?))"
    );
    $stmt->bind_param('iiii', $id, $idFamiglia, $idUtente, $idFamiglia);
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
    $id_account_password = isset($_POST['id_account_password']) ? (int)$_POST['id_account_password'] : 0;
    $url = $_POST['url_login'] ?? '';
    $username = $_POST['username'] ?? '';
    $password_account = $_POST['password_account'] ?? '';
    $note = $_POST['note'] ?? '';
    $attiva = isset($_POST['attiva']) ? 1 : 0;
    $condivisa = isset($_POST['condivisa_con_famiglia']) ? 1 : 0;

    if ($id_account_password > 0) {
        $stmt = $conn->prepare("SELECT id_utente FROM gestione_account_password WHERE id_account_password=? AND id_famiglia=?");
        $stmt->bind_param('ii', $id_account_password, $idFamiglia);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if (!$row || (int)$row['id_utente'] !== $idUtente) {
            echo '<p class="text-danger">Operazione non autorizzata.</p>';
            include 'includes/footer.php';
            exit;
        }
        $stmt = $conn->prepare("UPDATE gestione_account_password SET url_login=?, username=?, password_account=?, note=?, attiva=?, condivisa_con_famiglia=? WHERE id_account_password=? AND id_famiglia=?");
        $stmt->bind_param('ssssiiii', $url, $username, $password_account, $note, $attiva, $condivisa, $id_account_password, $idFamiglia);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO gestione_account_password (url_login, username, password_account, note, attiva, condivisa_con_famiglia, id_utente, id_famiglia) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssiiii', $url, $username, $password_account, $note, $attiva, $condivisa, $idUtente, $idFamiglia);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: password.php');
    exit;
}
?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-4">Dettaglio Password e Sito</h4>
</div>
<form method="post" class="bg-dark text-white p-3 rounded">
  <div class="mb-3">
    <label class="form-label">URL Login</label>
    <input type="text" name="url_login" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['url_login']) ?>" <?= $isOwner ? '' : 'disabled' ?>>
  </div>
  <div class="mb-3">
    <label class="form-label">Username</label>
    <input type="text" name="username" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['username']) ?>" <?= $isOwner ? '' : 'disabled' ?>>
  </div>
  <div class="mb-3">
    <label class="form-label">Password</label>
    <input type="text" name="password_account" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['password_account']) ?>" <?= $isOwner ? '' : 'disabled' ?>>
  </div>
  <div class="mb-3">
    <label class="form-label">Note</label>
    <textarea name="note" class="form-control bg-dark text-white border-secondary" rows="3" <?= $isOwner ? '' : 'disabled' ?>><?= htmlspecialchars($data['note']) ?></textarea>
  </div>
  <div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" id="attiva" name="attiva" <?= $data['attiva'] ? 'checked' : '' ?> <?= $isOwner ? '' : 'disabled' ?>>
    <label class="form-check-label" for="attiva">Attiva</label>
  </div>
  <div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" id="condivisa_con_famiglia" name="condivisa_con_famiglia" <?= $data['condivisa_con_famiglia'] ? 'checked' : '' ?> <?= $isOwner ? '' : 'disabled' ?>>
    <label class="form-check-label" for="condivisa_con_famiglia">Condividi con famiglia</label>
  </div>
  <input type="hidden" name="id_utente" value="<?= (int)$idUtente ?>">
  <input type="hidden" name="id_famiglia" value="<?= (int)$idFamiglia ?>">
  <?php if ($data['id_account_password']): ?>
    <input type="hidden" name="id_account_password" value="<?= (int)$data['id_account_password'] ?>">
  <?php endif; ?>
  <?php if ($isOwner): ?>
    <button type="submit" class="btn btn-primary w-100">Salva</button>
  <?php endif; ?>
</form>

<?php include 'includes/footer.php'; ?>
