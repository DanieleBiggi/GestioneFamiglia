<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card bg-dark text-white p-4">
      <h4 class="mb-3">Crea passkey</h4>
      <p class="mb-3">Le passkey permettono di accedere in modo sicuro senza inserire la password. Premi il pulsante per crearne una sul tuo dispositivo.</p>
      <button class="btn btn-primary" onclick="registerWebAuthn()">Crea passkey</button>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

