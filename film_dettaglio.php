<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$idUtente = $_SESSION['utente_id'] ?? 0;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo '<p class="text-danger">ID non valido.</p>';
    include 'includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dataVisto = $_POST['data_visto'] ?: null;
    $voto = $_POST['voto'] !== '' ? (float)$_POST['voto'] : null;
    $liste = isset($_POST['liste']) ? array_map('intval', (array)$_POST['liste']) : [];
    $nuovaLista = trim($_POST['nuova_lista'] ?? '');
    $idGruppo = isset($_POST['id_gruppo']) ? (int)$_POST['id_gruppo'] : null;
    $nuovoGruppo = trim($_POST['nuovo_gruppo'] ?? '');

    $stmt = $conn->prepare("UPDATE film_utenti SET data_visto=?, voto=? WHERE id_film=? AND id_utente=?");
    $stmt->bind_param('sddi', $dataVisto, $voto, $id, $idUtente);
    $stmt->execute();
    $stmt->close();

    if ($nuovoGruppo !== '') {
        $stmtNG = $conn->prepare("INSERT INTO film_gruppi (nome) VALUES (?)");
        $stmtNG->bind_param('s', $nuovoGruppo);
        $stmtNG->execute();
        $idGruppo = $stmtNG->insert_id;
        $stmtNG->close();
    }
    if ($idGruppo) {
        $stmtFG = $conn->prepare("UPDATE film SET id_gruppo=? WHERE id_film=?");
        $stmtFG->bind_param('ii', $idGruppo, $id);
        $stmtFG->execute();
        $stmtFG->close();
    } else {
        $stmtFG = $conn->prepare("UPDATE film SET id_gruppo=NULL WHERE id_film=?");
        $stmtFG->bind_param('i', $id);
        $stmtFG->execute();
        $stmtFG->close();
    }

    if ($nuovaLista !== '') {
        $stmtNL = $conn->prepare("INSERT INTO film_liste (id_utente, nome) VALUES (?, ?)");
        $stmtNL->bind_param('is', $idUtente, $nuovaLista);
        $stmtNL->execute();
        $liste[] = $stmtNL->insert_id;
        $stmtNL->close();
    }
    $stmtDel = $conn->prepare("DELETE fl FROM film2liste fl JOIN film_liste l ON fl.id_lista = l.id_lista WHERE fl.id_film=? AND l.id_utente=?");
    $stmtDel->bind_param('ii', $id, $idUtente);
    $stmtDel->execute();
    $stmtDel->close();
    if (!empty($liste)) {
        $stmtIns = $conn->prepare("INSERT INTO film2liste (id_film, id_lista) VALUES (?, ?)");
        foreach ($liste as $idLista) {
            $stmtIns->bind_param('ii', $id, $idLista);
            $stmtIns->execute();
        }
        $stmtIns->close();
    }
}

$stmt = $conn->prepare("SELECT f.*, fu.data_visto, fu.voto FROM film f JOIN film_utenti fu ON f.id_film=fu.id_film WHERE f.id_film=? AND fu.id_utente=?");
$stmt->bind_param('ii', $id, $idUtente);
$stmt->execute();
$res = $stmt->get_result();
if (!($film = $res->fetch_assoc())) {
    echo '<p class="text-danger">Film non trovato.</p>';
    include 'includes/footer.php';
    exit;
}
$stmt->close();

$stmtC = $conn->prepare("SELECT c.id, c.commento, c.inserito_il, c.id_utente, u.username FROM film_commenti c JOIN utenti u ON c.id_utente=u.id WHERE c.id_film=? ORDER BY c.inserito_il DESC");
$stmtC->bind_param('i', $id);
$stmtC->execute();
$commenti = $stmtC->get_result();
$stmtC->close();

$stmtListe = $conn->prepare("SELECT id_lista, nome FROM film_liste WHERE id_utente=? ORDER BY nome");
$stmtListe->bind_param('i', $idUtente);
$stmtListe->execute();
$listeUtente = $stmtListe->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtListe->close();

$stmtFilmListe = $conn->prepare("SELECT fl.id_lista FROM film2liste fl JOIN film_liste l ON fl.id_lista = l.id_lista WHERE fl.id_film=? AND l.id_utente=?");
$stmtFilmListe->bind_param('ii', $id, $idUtente);
$stmtFilmListe->execute();
$listeFilm = [];
$resFilmListe = $stmtFilmListe->get_result();
while ($r = $resFilmListe->fetch_assoc()) {
    $listeFilm[] = (int)$r['id_lista'];
}
$stmtFilmListe->close();

$stmtGruppi = $conn->prepare("SELECT id_gruppo, nome FROM film_gruppi ORDER BY nome");
$stmtGruppi->execute();
$gruppi = $stmtGruppi->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtGruppi->close();
?>
<div class="container text-white">
  <a href="film.php" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-4"><?= htmlspecialchars($film['titolo']) ?></h4>
  <?php if (!empty($film['poster_url'])): ?>
  <img src="<?= htmlspecialchars($film['poster_url']) ?>" alt="" class="mb-3" style="max-width:200px;">
  <?php endif; ?>
  <form method="post" class="bg-dark text-white p-3 rounded mb-4">
    <div class="mb-3">
      <label class="form-label">Data visto</label>
      <input type="date" name="data_visto" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($film['data_visto'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Voto</label>
      <input type="number" name="voto" step="0.5" min="1" max="10" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($film['voto'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Gruppo</label>
      <select name="id_gruppo" class="form-select bg-dark text-white border-secondary">
        <option value="">Nessuno</option>
        <?php foreach ($gruppi as $g): ?>
        <option value="<?= $g['id_gruppo'] ?>" <?= ($film['id_gruppo'] == $g['id_gruppo']) ? 'selected' : '' ?>><?= htmlspecialchars($g['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="nuovo_gruppo" class="form-control bg-dark text-white border-secondary mt-2" placeholder="Nuovo gruppo">
    </div>
    <div class="mb-3">
      <label class="form-label">Liste</label>
      <?php foreach ($listeUtente as $lista): ?>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="liste[]" value="<?= $lista['id_lista'] ?>" <?= in_array($lista['id_lista'], $listeFilm) ? 'checked' : '' ?>>
        <label class="form-check-label"><?= htmlspecialchars($lista['nome']) ?></label>
      </div>
      <?php endforeach; ?>
      <input type="text" name="nuova_lista" class="form-control bg-dark text-white border-secondary mt-2" placeholder="Nuova lista">
    </div>
    <button type="submit" class="btn btn-primary w-100">Salva</button>
  </form>
  <h5>Commenti</h5>
  <button type="button" class="btn btn-outline-light btn-sm mb-3" id="addCommentoBtn">Aggiungi commento</button>
  <div id="commentiList">
  <?php while($c = $commenti->fetch_assoc()): ?>
    <div class="mb-3 commento-row" data-id="<?= $c['id'] ?>" data-commento="<?= htmlspecialchars($c['commento'], ENT_QUOTES) ?>" data-utente="<?= $c['id_utente'] ?>">
      <div class="small text-muted"><?= htmlspecialchars($c['username']) ?> - <?= htmlspecialchars($c['inserito_il']) ?></div>
      <div><?= nl2br(htmlspecialchars($c['commento'])) ?></div>
    </div>
  <?php endwhile; ?>
  </div>
</div>
<div class="modal fade" id="commentoModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="commentoForm">
      <div class="modal-header">
        <h5 class="modal-title">Commento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <textarea name="commento" class="form-control bg-dark text-white border-secondary" rows="3" required></textarea>
        <input type="hidden" name="id">
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-danger d-none" id="deleteCommentoBtn">Elimina</button>
        <button type="submit" class="btn btn-primary">Salva</button>
      </div>
    </form>
  </div>
</div>
<script>
const FILM_ID = <?= (int)$id ?>;
const UTENTE_ID = <?= (int)$idUtente ?>;
</script>
<script src="js/film_dettaglio.js"></script>
<?php include 'includes/footer.php'; ?>
