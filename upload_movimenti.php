<?php
include 'includes/session_check.php';
include 'includes/header.php';
?>

<div class="container text-white">
  <h4 class="mb-4">Carica movimenti</h4>
  <form method="post" enctype="multipart/form-data">
    <div class="mb-3">
      <input type="file" name="file" class="form-control bg-dark text-white">
    </div>
    <button type="submit" class="btn btn-primary">Carica</button>
  </form>
</div>

<?php include 'includes/footer.php'; ?>
