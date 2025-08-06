<?php
session_start();
include 'includes/db.php';

if (!isset($_COOKIE['device_token'])) {
    header('Location: login.php');
    exit;
}

$token = $_COOKIE['device_token'];
$stmt = $conn->prepare('SELECT id_utente, user_agent, ip FROM dispositivi_riconosciuti WHERE token_dispositivo = ? AND scadenza >= NOW() LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows !== 1) {
    header('Location: login.php');
    exit;
}
$device = $res->fetch_assoc();
if (($device['user_agent'] ?? '') !== ($_SERVER['HTTP_USER_AGENT'] ?? '') || ($device['ip'] ?? '') !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['passcode'] ?? '';
    $sql = 'SELECT id, nome, passcode FROM utenti WHERE id = ? AND attivo = 1 LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $device['id_utente']);
    $stmt->execute();
    $userRes = $stmt->get_result();
    if ($userRes->num_rows === 1) {
        $user = $userRes->fetch_assoc();
        if (!empty($user['passcode']) && password_verify($pass, $user['passcode'])) {
            $_SESSION['utente_id'] = $user['id'];
            $_SESSION['utente_nome'] = $user['nome'];
            $newExp = date('Y-m-d H:i:s', time() + 60*60*24*30);
            $upd = $conn->prepare('UPDATE dispositivi_riconosciuti SET scadenza = ? WHERE token_dispositivo = ?');
            $upd->bind_param('ss', $newExp, $token);
            $upd->execute();
            header('Location: index.php');
            exit;
        }
    }
    $error = 'Passcode errato. Torna al login classico.';
}
?>
<?php include 'includes/header.php'; ?>
<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card bg-dark text-white p-4">
      <h4 class="mb-3">Login rapido</h4>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?> <a href="login.php" class="alert-link">Login classico</a></div>
      <?php else: ?>
      <form method="POST" action="login_passcode.php">
        <div class="mb-3">
          <label class="form-label">Passcode</label>
          <input type="password" name="passcode" class="form-control" required pattern="\d{6}" autofocus>
        </div>
        <button type="submit" class="btn btn-primary">Accedi</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
