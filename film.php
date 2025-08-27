<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/render_film.php';
include 'includes/header.php';

$idUtente = $_SESSION['utente_id'] ?? 0;

// Fetch films for current user with genres
$stmt = $conn->prepare(
    "SELECT f.*, fu.data_visto, fu.voto, GROUP_CONCAT(f2g.id_genere) AS generi " .
    "FROM film f " .
    "JOIN film_utenti fu ON f.id_film = fu.id_film " .
    "LEFT JOIN film2generi f2g ON f.id_film = f2g.id_film " .
    "WHERE fu.id_utente = ? " .
    "GROUP BY f.id_film, fu.data_visto, fu.voto"
);
$stmt->bind_param('i', $idUtente);
$stmt->execute();
$res = $stmt->get_result();

$yearsRes = $conn->query("SELECT DISTINCT anno FROM film ORDER BY anno DESC");
$generiRes = $conn->query("SELECT id_genere, nome FROM film_generi ORDER BY nome");
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Film</h4><a href="film_aggiungi.php" class="btn btn-outline-light btn-sm">Aggiungi</a>
</div>
<div class="row mb-3 g-2">
  <div class="col-sm">
    <input type="text" id="search" class="form-control bg-dark text-white border-secondary" placeholder="Cerca">
  </div>
  <div class="col-sm-3">
    <select id="filterAnno" class="form-select bg-dark text-white border-secondary">
      <option value="">Tutti gli anni</option>
      <?php while($y = $yearsRes->fetch_assoc()): ?>
      <option value="<?= (int)$y['anno'] ?>"><?= (int)$y['anno'] ?></option>
      <?php endwhile; ?>
    </select>
  </div>
  <div class="col-sm-3">
    <select id="filterGenere" class="form-select bg-dark text-white border-secondary">
      <option value="">Tutti i generi</option>
      <?php while($g = $generiRes->fetch_assoc()): ?>
      <option value="<?= (int)$g['id_genere'] ?>"><?= htmlspecialchars($g['nome']) ?></option>
      <?php endwhile; ?>
    </select>
  </div>
</div>
<div id="filmList">
  <?php while ($row = $res->fetch_assoc()): ?>
    <?php render_film($row); ?>
  <?php endwhile; ?>
</div>
<script src="js/film.js"></script>
<?php include 'includes/footer.php'; ?>
