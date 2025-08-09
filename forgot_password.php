<?php
session_start();
require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/libs/PHPMailer/Exception.php';
require_once __DIR__ . '/libs/PHPMailer/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
include 'includes/db.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $stmt = $conn->prepare('SELECT id, nome FROM utenti WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();
        $token = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', time() + 3600);
        $ins = $conn->prepare('INSERT INTO reset_password (id_utente, token, scadenza) VALUES (?, ?, ?)');
        $ins->bind_param('iss', $user['id'], $token, $expires);
        $ins->execute();

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtps.aruba.it'; // con la "s"
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['mail_user'];
            $mail->Password   = $config['mail_pwd'];
            $mail->SMTPSecure = 'ssl';
            $mail->Port       = 465;
        
            // Aggiungi questa sezione per evitare errori SSL
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ]
            ];

            $mail->setFrom('assistenza@gestionefamiglia.it', 'Gestione Famiglia');
            $mail->addAddress($email, $user['nome']);
            $mail->isHTML(true);
            $link = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . urlencode($token);
            $mail->Subject = 'Reset password';
            $html = file_get_contents(__DIR__ . '/assets/html/codice_verifica.html');
            $resetButton = '<a href="' . $link . '" style="display:inline-block;padding:15px 25px;background-color:#d32f2f;color:#ffffff;text-decoration:none;border-radius:4px;">Reimposta password</a>';
            $html = str_replace(['[content]', '[message]'], [$resetButton, 'Per reimpostare la password, clicca sul pulsante seguente:'], $html);
            $mail->Body = $html;
            $mail->send();
            $success = 'Controlla la tua email per le istruzioni.';
        } catch (Exception $e) {
            $error = "Errore nell'invio dell'email.";
        }
    } else {
        $error = 'Indirizzo email non trovato.';
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card bg-dark text-white p-4">
      <h4 class="mb-3">Recupero password</h4>
      <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
      <form method="POST" action="forgot_password.php">
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary">Invia</button>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
