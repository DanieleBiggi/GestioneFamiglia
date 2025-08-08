<?php
session_start();
include 'includes/db.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($password !== $confirm) {
        $error = 'Le password non coincidono.';
    } else {
        $stmt = $conn->prepare('SELECT id_utente FROM reset_password WHERE token = ? AND scadenza >= NOW() LIMIT 1');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 1) {
            $row = $res->fetch_assoc();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $conn->prepare('UPDATE utenti SET password = ? WHERE id = ?');
            $upd->bind_param('si', $hash, $row['id_utente']);
            $upd->execute();
            $del = $conn->prepare('DELETE FROM reset_password WHERE token = ?');
            $del->bind_param('s', $token);
            $del->execute();
            $success = 'Password aggiornata con successo.';
        } else {
            $error = 'Token non valido o scaduto.';
        }
    }
} else {
    if ($token) {
        $check = $conn->prepare('SELECT id_utente FROM reset_password WHERE token = ? AND scadenza >= NOW() LIMIT 1');
        $check->bind_param('s', $token);
        $check->execute();
        $res = $check->get_result();
        if ($res->num_rows !== 1) {
            $error = 'Token non valido o scaduto.';
            $token = '';
        }
    } else {
        $error = 'Token mancante.';
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card bg-dark text-white p-4">
      <h4 class="mb-3">Imposta nuova password</h4>
      <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><a href="login.php"  class="btn btn-link text-light">Vai alla login</a><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
      <?php if ($token && !$success): ?>
      <form method="POST" action="reset_password.php">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <div class="mb-3">
          <label class="form-label">Nuova password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Conferma password</label>
          <input type="password" name="confirm" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Salva</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
