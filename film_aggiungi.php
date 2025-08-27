<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/header.php';
$apiKey = getenv('TMDB_API_KEY');
?>
<div class="container text-white">
  <a href="film.php" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-3">Aggiungi Film</h4>
  <?php if(!$apiKey): ?>
    <div class="alert alert-warning">TMDB_API_KEY non configurato.</div>
  <?php endif; ?>
  <div class="mb-3">
    <input type="text" id="query" class="form-control bg-dark text-white border-secondary" placeholder="Titolo film">
  </div>
  <button id="searchBtn" class="btn btn-primary mb-3">Cerca</button>
  <div id="searchResults"></div>
</div>
<script>
  const TMDB_API_KEY = '<?= htmlspecialchars($apiKey ?? '') ?>';
</script>
<script src="js/film_aggiungi.js"></script>
<?php include 'includes/footer.php'; ?>
