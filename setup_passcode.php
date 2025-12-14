<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['utente_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = $_POST['passcode'] ?? '';
    $pass2 = $_POST['confirm'] ?? '';

    if (!preg_match('/^\d{6}$/', $pass1)) {
        $error = 'Il passcode deve essere composto da 6 cifre.';
    } elseif ($pass1 !== $pass2) {
        $error = 'I passcode non coincidono.';
    } else {
        $hash = password_hash($pass1, PASSWORD_DEFAULT);
        $upd = $conn->prepare('UPDATE utenti SET passcode = ? WHERE id = ?');
        $upd->bind_param('si', $hash, $_SESSION['utente_id']);
        $upd->execute();

        $token = bin2hex(random_bytes(32));
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $now = date('Y-m-d H:i:s');
        $longDuration = 60*60*24*365*10; // 10 anni
        $exp = date('Y-m-d H:i:s', time() + $longDuration);
        $ins = $conn->prepare('INSERT INTO dispositivi_riconosciuti (id_utente, token_dispositivo, user_agent, ip, data_attivazione, scadenza) VALUES (?, ?, ?, ?, ?, ?)');
        $ins->bind_param('isssss', $_SESSION['utente_id'], $token, $ua, $ip, $now, $exp);
        $ins->execute();

        setcookie('device_token', $token, time()+$longDuration, '/', '', !empty($_SERVER['HTTPS']), true);
        header('Location: index.php');
        exit;
    }
}
?>
<?php include 'includes/header.php'; ?>
<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card bg-dark text-white p-4">
      <h4 class="mb-3">Imposta passcode</h4>
        <h5 class="mb-3">Potrai utilizzare il passcode da questo dispositivo per accedere pi&ugrave; velocemente.<br>Se non vuoi impostare un passcode, <a href='index.php'>clicca qui</a> per andare alla pagina iniziale</h5>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>
      <form method="POST" action="setup_passcode.php">
        <div class="mb-3">
          <label class="form-label">Passcode (6 cifre)</label>
          <input type="password" name="passcode" class="form-control" required pattern="\d{6}">
        </div>
        <div class="mb-3">
          <label class="form-label">Conferma Passcode</label>
          <input type="password" name="confirm" class="form-control" required pattern="\d{6}">
        </div>
        <button type="submit" class="btn btn-primary">Salva dispositivo</button>
      </form>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
