<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$idUtente = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$data = ['url_login'=>'','username'=>'','password_account'=>'','note'=>'','attiva'=>1,'id_account'=>0];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM gestione_account_password WHERE id_account_password = ? AND id_famiglia = ?");
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_account = isset($_POST['id_account']) ? (int)$_POST['id_account'] : 0;
    $url = $_POST['url_login'] ?? '';
    $username = $_POST['username'] ?? '';
    $password_account = $_POST['password_account'] ?? '';
    $note = $_POST['note'] ?? '';
    $attiva = isset($_POST['attiva']) ? 1 : 0;

    if ($id_account > 0) {
        $stmt = $conn->prepare("UPDATE gestione_account_password SET url_login=?, username=?, password_account=?, note=?, attiva=? WHERE id_account_password=? AND id_famiglia=?");
        $stmt->bind_param('ssssiii', $url, $username, $password_account, $note, $attiva, $id_account, $idFamiglia);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO gestione_account_password (url_login, username, password_account, note, attiva, id_utente, id_famiglia) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssiii', $url, $username, $password_account, $note, $attiva, $idUtente, $idFamiglia);
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
    <input type="text" name="url_login" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['url_login']) ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Username</label>
    <input type="text" name="username" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['username']) ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Password</label>
    <input type="text" name="password_account" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['password_account']) ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Note</label>
    <textarea name="note" class="form-control bg-dark text-white border-secondary" rows="3"><?= htmlspecialchars($data['note']) ?></textarea>
  </div>
  <div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" id="attiva" name="attiva" <?= $data['attiva'] ? 'checked' : '' ?>>
    <label class="form-check-label" for="attiva">Attiva</label>
  </div>
  <input type="hidden" name="id_utente" value="<?= (int)$idUtente ?>">
  <input type="hidden" name="id_famiglia" value="<?= (int)$idFamiglia ?>">
  <?php if ($data['id_account']): ?>
    <input type="hidden" name="id_account" value="<?= (int)$data['id_account'] ?>">
  <?php endif; ?>
  <button type="submit" class="btn btn-primary w-100">Salva</button>
</form>

<?php include 'includes/footer.php'; ?>
