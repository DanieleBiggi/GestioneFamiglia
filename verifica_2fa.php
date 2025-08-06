<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['2fa_user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    $_SESSION['2fa_attempts'] = ($_SESSION['2fa_attempts'] ?? 0) + 1;

    $sql = 'SELECT id FROM codici_2fa WHERE id_utente = ? AND codice = ? AND scadenza >= NOW() LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $_SESSION['2fa_user_id'], $code);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $row = $res->fetch_assoc();
        $del = $conn->prepare('DELETE FROM codici_2fa WHERE id = ?');
        $del->bind_param('i', $row['id']);
        $del->execute();

        $_SESSION['utente_id'] = $_SESSION['2fa_user_id'];
        $_SESSION['utente_nome'] = $_SESSION['2fa_user_nome'];
        unset($_SESSION['2fa_user_id'], $_SESSION['2fa_user_nome'], $_SESSION['2fa_attempts']);
        header('Location: setup_passcode.php');
        exit;
    } else {
        if ($_SESSION['2fa_attempts'] >= 5) {
            $del = $conn->prepare('DELETE FROM codici_2fa WHERE id_utente = ?');
            $del->bind_param('i', $_SESSION['2fa_user_id']);
            $del->execute();
            $error = 'Troppi tentativi. Effettua nuovamente il login.';
            unset($_SESSION['2fa_user_id'], $_SESSION['2fa_user_nome'], $_SESSION['2fa_attempts']);
        } else {
            $error = 'Codice non valido o scaduto.';
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card bg-dark text-white p-4">
      <h4 class="mb-3">Verifica 2FA</h4>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>
      <?php if (isset($_SESSION['2fa_user_id'])): ?>
      <form method="POST" action="verifica_2fa.php">
        <div class="mb-3">
          <label class="form-label">Codice</label>
          <input type="text" name="code" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Verifica</button>
      </form>
      <?php else: ?>
        <a href="login.php" class="btn btn-primary">Torna al login</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

