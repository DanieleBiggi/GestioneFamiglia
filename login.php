<?php
session_start();
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

            $_SESSION["utente_id"] = $user["id"];
            $_SESSION["utente_nome"] = $user["nome"];
            header("Location: index.php");
            exit;
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
