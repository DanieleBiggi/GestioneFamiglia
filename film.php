<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/render_film.php';
include 'includes/header.php';

$idUtente = $_SESSION['utente_id'] ?? 0;

// Fetch films for current user with genres, group and lists
$stmt = $conn->prepare(
    "SELECT f.*, fu.data_visto, fu.voto, fg.nome AS gruppo, " .
    "GROUP_CONCAT(DISTINCT f2g.id_genere) AS generi, " .
    "GROUP_CONCAT(DISTINCT f2l.id_lista) AS liste " .
    "FROM film f " .
    "JOIN film_utenti fu ON f.id_film = fu.id_film " .
    "LEFT JOIN film2generi f2g ON f.id_film = f2g.id_film " .
    "LEFT JOIN film_gruppi fg ON f.id_gruppo = fg.id_gruppo " .
    "LEFT JOIN film2liste f2l ON f.id_film = f2l.id_film " .
    "LEFT JOIN film_liste l ON f2l.id_lista = l.id_lista AND l.id_utente = ? " .
    "WHERE fu.id_utente = ? " .
    "GROUP BY f.id_film, fu.data_visto, fu.voto, fg.nome"
);
$stmt->bind_param('ii', $idUtente, $idUtente);
$stmt->execute();
$res = $stmt->get_result();

$yearsRes = $conn->query("SELECT DISTINCT anno FROM film ORDER BY anno DESC");
$generiRes = $conn->query("SELECT id_genere, nome FROM film_generi ORDER BY nome");
$gruppiRes = $conn->query("SELECT id_gruppo, nome FROM film_gruppi ORDER BY nome");
$stmtListe = $conn->prepare("SELECT id_lista, nome FROM film_liste WHERE id_utente=? ORDER BY nome");
$stmtListe->bind_param('i', $idUtente);
$stmtListe->execute();
$listeRes = $stmtListe->get_result();
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Film</h4><a href="film_aggiungi.php" class="btn btn-outline-light btn-sm">Aggiungi</a>
</div>
<div class="row mb-3 g-2">
  <div class="col">
    <input type="text" id="search" class="form-control bg-dark text-white border-secondary" placeholder="Cerca">
  </div>
  <div class="col-auto">
    <button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#filtersModal">
      <i class="bi bi-funnel"></i>
    </button>
  </div>
</div>

<div class="modal fade" id="filtersModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title">Filtri</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="filterAnno" class="form-label">Anno</label>
          <select id="filterAnno" class="form-select bg-dark text-white border-secondary">
            <option value="">Tutti gli anni</option>
            <?php while($y = $yearsRes->fetch_assoc()): ?>
            <option value="<?= (int)$y['anno'] ?>"><?= (int)$y['anno'] ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="mb-3">
          <label for="filterGenere" class="form-label">Genere</label>
          <select id="filterGenere" class="form-select bg-dark text-white border-secondary">
            <option value="">Tutti i generi</option>
            <?php while($g = $generiRes->fetch_assoc()): ?>
            <option value="<?= (int)$g['id_genere'] ?>"><?= htmlspecialchars($g['nome']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="mb-3">
          <label for="filterRegista" class="form-label">Regista</label>
          <input type="text" id="filterRegista" class="form-control bg-dark text-white border-secondary">
        </div>
        <div class="mb-3">
          <label for="filterGruppo" class="form-label">Gruppo</label>
          <select id="filterGruppo" class="form-select bg-dark text-white border-secondary">
            <option value="">Tutti i gruppi</option>
            <?php while($gr = $gruppiRes->fetch_assoc()): ?>
            <option value="<?= (int)$gr['id_gruppo'] ?>"><?= htmlspecialchars($gr['nome']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="mb-3">
          <label for="filterLista" class="form-label">Lista</label>
          <select id="filterLista" class="form-select bg-dark text-white border-secondary">
            <option value="">Tutte le liste</option>
            <?php while($l = $listeRes->fetch_assoc()): ?>
            <option value="<?= (int)$l['id_lista'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Data visione</label>
          <div class="d-flex gap-2">
            <input type="date" id="filterDataDa" class="form-control bg-dark text-white border-secondary" placeholder="Da">
            <input type="date" id="filterDataA" class="form-control bg-dark text-white border-secondary" placeholder="A">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Durata (min)</label>
          <div class="d-flex gap-2">
            <input type="number" id="filterDurataDa" class="form-control bg-dark text-white border-secondary" placeholder="Da">
            <input type="number" id="filterDurataA" class="form-control bg-dark text-white border-secondary" placeholder="A">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Chiudi</button>
      </div>
    </div>
  </div>
</div>
<div id="filmList">
  <?php while ($row = $res->fetch_assoc()): ?>
    <?php render_film($row); ?>
  <?php endwhile; ?>
</div>
<script src="js/film.js"></script>
<?php include 'includes/footer.php'; ?>
