<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
include 'includes/db.php';

if (!isset($_COOKIE['device_token'])) {
    header('Location: /Gestionale25/login.php?scelta_login=1');
    exit;
}

$token = $_COOKIE['device_token'];
$stmt = $conn->prepare('SELECT id_utente, user_agent FROM dispositivi_riconosciuti WHERE token_dispositivo = ? AND scadenza >= NOW() LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows !== 1) {
    header('Location: /Gestionale25/login.php?scelta_login=1');
    exit;
}
$device = $res->fetch_assoc();
if (($device['user_agent'] ?? '') !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
    header('Location: /Gestionale25/login.php?scelta_login=1');
    exit;
}

$userName = '';
$userStmt = $conn->prepare('SELECT nome FROM utenti WHERE id = ? LIMIT 1');
$userStmt->bind_param('i', $device['id_utente']);
$userStmt->execute();
$userRow = $userStmt->get_result()->fetch_assoc();
$userName = $userRow['nome'] ?? '';
$userStmt->close();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['passcode'] ?? '';
    $sql = 'SELECT id, nome, passcode, id_famiglia_gestione, attivo, passcode_attempts, passcode_locked_until, id_tema FROM utenti WHERE id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $device['id_utente']);
    $stmt->execute();
    $userRes = $stmt->get_result();
    if ($userRes->num_rows === 1) {
        $user = $userRes->fetch_assoc();
        if ((int)$user['attivo'] !== 1) {
            $error = "Account bloccato. Contatta l'assistenza all'indirizzo <a href='mailto:'>assistenza@gestionefamiglia.it</a>.";
        } elseif (!empty($user['passcode_locked_until']) && strtotime($user['passcode_locked_until']) > time()) {
            $lockedUntil = strtotime($user['passcode_locked_until']);
            $now = time();
            $diff = $lockedUntil - $now;            
            if ($diff > 60) {
                $attesa = round($diff / 60) . " minuti.";
            } elseif ($diff > 0) {
                $attesa = $diff . " secondi.";
            }
            $error = 'Troppi tentativi. Account temporaneamente bloccato. Riprova tra '.$attesa;
        } else {
            if (!empty($user['passcode_locked_until']) && strtotime($user['passcode_locked_until']) <= time()) {
                $clear = $conn->prepare('UPDATE utenti SET passcode_locked_until = NULL, passcode_attempts = 0 WHERE id = ?');
                $clear->bind_param('i', $user['id']);
                $clear->execute();
                $user['passcode_attempts'] = 0;
            }
            if (!empty($user['passcode']) && password_verify($pass, $user['passcode'])) {
                $reset = $conn->prepare('UPDATE utenti SET passcode_attempts = 0, passcode_locked_until = NULL WHERE id = ?');
                $reset->bind_param('i', $user['id']);
                $reset->execute();

                $_SESSION['utente_id'] = $user['id'];
                $_SESSION['utente_nome'] = $user['nome'];
                $_SESSION['id_famiglia_gestione'] = $user['id_famiglia_gestione'] ?? 0;
                $_SESSION['theme_id'] = (int)($user['id_tema'] ?? 1);

                $lvlStmt = $conn->prepare('SELECT userlevelid FROM utenti2famiglie WHERE id_utente = ? AND id_famiglia = ? LIMIT 1');
                $lvlStmt->bind_param('ii', $_SESSION['utente_id'], $_SESSION['id_famiglia_gestione']);
                $lvlStmt->execute();
                $lvlRes = $lvlStmt->get_result();
                $_SESSION['userlevelid'] = ($lvlRes->num_rows === 1) ? intval($lvlRes->fetch_assoc()['userlevelid']) : 0;

                $newExp = date('Y-m-d H:i:s', time() + 60*60*24*30);
                $upd = $conn->prepare('UPDATE dispositivi_riconosciuti SET scadenza = ? WHERE token_dispositivo = ?');
                $upd->bind_param('ss', $newExp, $token);
                $upd->execute();
                header('Location: /Gestionale25/index.php');
                exit;
            } else {
                $attempts = (int)$user['passcode_attempts'] + 1;
                if ($attempts >= 3) {
                    $minuti_attesa = 15;
                    $until = date('Y-m-d H:i:s', time() + $minuti_attesa*60);
                    $block = $conn->prepare('UPDATE utenti SET passcode_locked_until = ?, passcode_attempts = 0 WHERE id = ?');
                    $block->bind_param('si', $until, $user['id']);
                    $block->execute();
                    $error = 'Troppi tentativi. Account temporaneamente bloccato. Riprova tra '.$minuti_attesa.' minuti.';
                } else {
                    $updAtt = $conn->prepare('UPDATE utenti SET passcode_attempts = ? WHERE id = ?');
                    $updAtt->bind_param('ii', $attempts, $user['id']);
                    $updAtt->execute();
                    $error = 'Passcode errato. Torna al login classico.';
                }
            }
        }
    } else {
        $error = 'Passcode errato. Torna al login classico.';
    }
}
?>
<?php include 'includes/header.php'; ?>
<div class="d-flex flex-column align-items-center mt-5">
  <img src="assets/icona.png" alt="Gestione Famiglia" class="mb-3" style="width:80px;">
  <h4 class="mb-4 text-center">Ciao, <?= htmlspecialchars($userName) ?></h4>
  <?php if ($error): ?>
    <div class="alert alert-danger text-center w-75"><?= $error ?> <a href="/Gestionale25/login.php?scelta_login=1" class="alert-link">Login classico</a></div>
  <?php else: ?>
  <form id="passcode-form" method="POST" action="login_passcode.php" class="w-100 d-flex flex-column align-items-center">
    <input type="hidden" id="passcode" name="passcode">
    <div id="pin-dots" class="d-flex justify-content-center mb-4">
      <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="dot"></div>
      <?php endfor; ?>
    </div>
    <div class="pin-keypad">
      <?php for ($i = 1; $i <= 9; $i++): ?>
        <button type="button" class="pin-key" data-number="<?= $i ?>"><?= $i ?></button>
      <?php endfor; ?>
      <button type="button" class="pin-key" id="fingerprint"><i class="bi bi-fingerprint"></i></button>
      <button type="button" class="pin-key" data-number="0">0</button>
      <button type="button" class="pin-key" id="backspace"><i class="bi bi-backspace"></i></button>
    </div>
  </form>
  <a href="/Gestionale25/login.php?scelta_login=1" class="btn btn-link text-light mt-3">Login con utente e password</a>
  <script>
    const input = document.getElementById('passcode');
    const dots = document.querySelectorAll('#pin-dots .dot');
    function addDigit(d){
      if(input.value.length < 6){
        input.value += d;
        dots[input.value.length - 1].classList.add('filled');
        if(input.value.length === 6){
          document.getElementById('passcode-form').submit();
        }
      }
    }
    function removeDigit(){
      if(input.value.length > 0){
        dots[input.value.length - 1].classList.remove('filled');
        input.value = input.value.slice(0, -1);
      }
    }
    document.querySelectorAll('.pin-key[data-number]').forEach(btn => {
      btn.addEventListener('click', () => addDigit(btn.dataset.number));
    });
    document.getElementById('backspace').addEventListener('click', removeDigit);
    document.getElementById('fingerprint').addEventListener('click', () => loginWebAuthn());
  </script>
  <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
