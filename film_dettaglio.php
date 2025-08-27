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
    $commento = trim($_POST['commento'] ?? '');
    $liste = isset($_POST['liste']) ? array_map('intval', (array)$_POST['liste']) : [];
    $nuovaLista = trim($_POST['nuova_lista'] ?? '');
    $stmt = $conn->prepare("UPDATE film_utenti SET data_visto=?, voto=? WHERE id_film=? AND id_utente=?");
    $stmt->bind_param('sddi', $dataVisto, $voto, $id, $idUtente);
    $stmt->execute();
    $stmt->close();
    if ($commento !== '') {
        $stmtC = $conn->prepare("INSERT INTO film_commenti (id_film, id_utente, commento) VALUES (?,?,?)");
        $stmtC->bind_param('iis', $id, $idUtente, $commento);
        $stmtC->execute();
        $stmtC->close();
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

$stmtC = $conn->prepare("SELECT c.commento, c.inserito_il, u.username FROM film_commenti c JOIN utenti u ON c.id_utente=u.id WHERE c.id_film=? ORDER BY c.inserito_il DESC");
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
      <label class="form-label">Commento</label>
      <textarea name="commento" class="form-control bg-dark text-white border-secondary" rows="3"></textarea>
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
  <?php if ($commenti->num_rows > 0): ?>
  <h5>Commenti</h5>
  <?php while($c = $commenti->fetch_assoc()): ?>
    <div class="mb-3">
      <div class="small text-muted"><?= htmlspecialchars($c['username']) ?> - <?= htmlspecialchars($c['inserito_il']) ?></div>
      <div><?= htmlspecialchars($c['commento']) ?></div>
    </div>
  <?php endwhile; ?>
  <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
