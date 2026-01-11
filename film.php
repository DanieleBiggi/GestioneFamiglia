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
    "GROUP_CONCAT(DISTINCT f2l.id_lista) AS liste, " .
    "GROUP_CONCAT(DISTINCT sp.icon) AS piattaforme, " .
    "GROUP_CONCAT(DISTINCT f2p.id_piattaforma) AS piattaforme_ids, " .
    "MAX(f2p.indicata_il) AS piattaforme_aggiornate_il " .
    "FROM film f " .
    "JOIN film_utenti fu ON f.id_film = fu.id_film " .
    "LEFT JOIN film2generi f2g ON f.id_film = f2g.id_film " .
    "LEFT JOIN film_gruppi fg ON f.id_gruppo = fg.id_gruppo " .
    "LEFT JOIN film2piattaforme f2p ON f.id_film = f2p.id_film " .
    "LEFT JOIN streaming_piattaforme sp ON f2p.id_piattaforma = sp.id_piattaforma " .
    "LEFT JOIN film2liste f2l ON f.id_film = f2l.id_film " .
    "LEFT JOIN film_liste l ON f2l.id_lista = l.id_lista AND l.id_utente = ? " .
    "WHERE fu.id_utente = ? " .
    "GROUP BY f.id_film, fu.data_visto, fu.voto, fg.nome, f.inserito_il " .
    "ORDER BY f.inserito_il DESC"
);
$stmt->bind_param('ii', $idUtente, $idUtente);
$stmt->execute();
$res = $stmt->get_result();

$yearsRangeRes = $conn->query("SELECT MIN(anno) AS min_anno, MAX(anno) AS max_anno FROM film");
$yearsRange = $yearsRangeRes ? $yearsRangeRes->fetch_assoc() : ['min_anno' => null, 'max_anno' => null];
$generiRes = $conn->query("SELECT id_genere, nome FROM film_generi ORDER BY nome");
$gruppiRes = $conn->query("SELECT id_gruppo, nome FROM film_gruppi ORDER BY nome");
$stmtListe = $conn->prepare("SELECT id_lista, nome FROM film_liste WHERE id_utente=? ORDER BY nome");
$stmtListe->bind_param('i', $idUtente);
$stmtListe->execute();
$listeRes = $stmtListe->get_result();
$piattaformeRes = $conn->query("SELECT id_piattaforma, nome FROM streaming_piattaforme ORDER BY nome");
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Film</h4><a href="film_aggiungi.php" class="btn btn-outline-light btn-sm">Aggiungi</a>
</div>
<div class="row mb-3 g-2">
  <div class="col">
    <input type="text" id="search" class="form-control bg-dark text-white border-secondary" placeholder="Cerca">
  </div>
  <div class="col-auto">
    <div class="btn-group">
      <button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#filtersModal">
        <i class="bi bi-funnel"></i>
      </button>
      <button type="button" class="btn btn-outline-light" id="resetFilters" title="Reset filtri">
        <i class="bi bi-arrow-counterclockwise"></i>
      </button>
    </div>
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
          <label for="filterOrdine" class="form-label">Ordinamento</label>
          <div class="d-flex gap-2">
            <select id="filterOrdine" class="form-select bg-dark text-white border-secondary">
              <option value="inserimento" selected>Data inserimento</option>
              <option value="titolo">Titolo</option>
              <option value="titolo_originale">Titolo originale</option>
              <option value="anno">Anno</option>
              <option value="regista">Regista</option>
              <option value="gruppo">Gruppo</option>
              <option value="voto">Voto</option>
              <option value="voto_medio">Voto medio</option>
              <option value="durata">Durata</option>
              <option value="visto">Data visione</option>
              <option value="piattaforme_aggiornamento">Data aggiornamento piattaforme</option>
            </select>
            <select id="filterOrdineDirezione" class="form-select bg-dark text-white border-secondary">
              <option value="desc" selected>Desc</option>
              <option value="asc">Asc</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Anno</label>
          <div class="d-flex gap-2">
            <input type="number" id="filterAnnoDa" class="form-control bg-dark text-white border-secondary" placeholder="Da" min="<?= (int)($yearsRange['min_anno'] ?? 0) ?>" max="<?= (int)($yearsRange['max_anno'] ?? 0) ?>">
            <input type="number" id="filterAnnoA" class="form-control bg-dark text-white border-secondary" placeholder="A" min="<?= (int)($yearsRange['min_anno'] ?? 0) ?>" max="<?= (int)($yearsRange['max_anno'] ?? 0) ?>">
          </div>
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
          <label class="form-label">Piattaforme streaming</label>
          <div class="d-flex flex-column gap-1">
            <?php while($p = $piattaformeRes->fetch_assoc()): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="filterPiattaforme[]" id="filterPiattaforma<?= (int)$p['id_piattaforma'] ?>" value="<?= (int)$p['id_piattaforma'] ?>">
              <label class="form-check-label" for="filterPiattaforma<?= (int)$p['id_piattaforma'] ?>">
                <?= htmlspecialchars($p['nome']) ?>
              </label>
            </div>
            <?php endwhile; ?>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Voto</label>
          <div class="d-flex gap-2">
            <input type="number" step="0.1" id="filterVotoDa" class="form-control bg-dark text-white border-secondary" placeholder="Da">
            <input type="number" step="0.1" id="filterVotoA" class="form-control bg-dark text-white border-secondary" placeholder="A">
          </div>
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
