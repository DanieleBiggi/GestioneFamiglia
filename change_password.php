<?php
include 'includes/session_check.php';
include 'includes/db.php';

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = $_SESSION["utente_id"];
    $old = md5($_POST["old_password"]);
    $new = md5($_POST["new_password"]);
    $check = $conn->prepare("SELECT * FROM utenti WHERE id = ? AND password = ?");
    $check->bind_param("is", $id, $old);
    $check->execute();
    $res = $check->get_result();
    if ($res->num_rows === 1) {
        $update = $conn->prepare("UPDATE utenti SET password = ? WHERE id = ?");
        $update->bind_param("si", $new, $id);
        $update->execute();
        $success = "Password cambiata correttamente.";
    } else {
        $error = "La vecchia password non è corretta.";
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card bg-dark text-white p-4">
      <h4 class="mb-3">Cambio Password</h4>
      <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Vecchia password</label>
          <input type="password" name="old_password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Nuova password</label>
          <input type="password" name="new_password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Cambia</button>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
