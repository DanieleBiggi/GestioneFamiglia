<?php
session_start();
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
include 'includes/db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    $sql = "SELECT * FROM utenti WHERE username = ? AND attivo = 1 LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 1) {
        $user = $res->fetch_assoc();
        $stored = $user["password"];

        $valid = password_verify($password, $stored) || $stored === md5($password);

        if ($valid) {
            if ($stored === md5($password) || password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE utenti SET password = ? WHERE id = ?");
                $upd->bind_param("si", $newHash, $user["id"]);
                $upd->execute();
            }

            $code = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', time() + 300);
            $ins = $conn->prepare("INSERT INTO codici_2fa (id_utente, codice, scadenza) VALUES (?, ?, ?)");
            $ins->bind_param("iss", $user["id"], $code, $expires);
            $ins->execute();

            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = getenv('SMTP_HOST');
                $mail->SMTPAuth = true;
                $mail->Username = getenv('SMTP_USER');
                $mail->Password = getenv('SMTP_PASS');
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = getenv('SMTP_PORT') ?: 587;

                $mail->setFrom(getenv('SMTP_FROM') ?: 'no-reply@example.com', 'Gestione Famiglia');
                $mail->addAddress($user['email'], $user['nome']);
                $mail->Subject = 'Codice di verifica';
                $mail->Body = "Il tuo codice di verifica è: $code";
                $mail->send();

                $_SESSION['2fa_user_id'] = $user['id'];
                $_SESSION['2fa_user_nome'] = $user['nome'];
                $_SESSION['2fa_attempts'] = 0;
                header("Location: verifica_2fa.php");
                exit;
            } catch (Exception $e) {
                $error = "Errore nell'invio del codice.";
            }
        } else {
            $error = "Password errata.";
        }
    } else {
        $error = "Utente non trovato.";
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card bg-dark text-white p-4">
      <h4 class="mb-3">Accesso</h4>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>
      <form method="POST" action="login.php">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Accedi</button>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
