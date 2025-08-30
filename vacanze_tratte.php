<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$grp = $_GET['grp'] ?? '';

// Recupera info viaggio per breadcrumb
$stmt = $conn->prepare('SELECT titolo FROM viaggi WHERE id_viaggio=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$viaggio = $stmt->get_result()->fetch_assoc();
if (!$viaggio) {
    echo '<p class="text-danger">Viaggio non trovato</p>';
    include 'includes/footer.php';
    exit;
}

// Gestione form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tratta = (int)($_POST['id_tratta'] ?? 0);
    $gruppo = trim($_POST['gruppo_alternativa'] ?? $grp);
    $tipo = $_POST['tipo_tratta'] ?? 'auto';
    $descrizione = $_POST['descrizione'] ?? null;
    $origine = $_POST['origine_testo'] ?? null;
    $destinazione = $_POST['destinazione_testo'] ?? null;
    $distanza = (float)($_POST['distanza_km'] ?? 0);
    $durata = (float)($_POST['durata_ore'] ?? 0);
    $consumo = (float)($_POST['consumo_litri_100km'] ?? 0);
    $prezzo = (float)($_POST['prezzo_carburante_eur_litro'] ?? 0);
    $pedaggi = (float)($_POST['pedaggi_eur'] ?? 0);
    $traghetto = (float)($_POST['costo_traghetto_eur'] ?? 0);
    $volo = (float)($_POST['costo_volo_eur'] ?? 0);
    $noleggio = (float)($_POST['costo_noleggio_eur'] ?? 0);
    $altri = (float)($_POST['altri_costi_eur'] ?? 0);
    $note = $_POST['note'] ?? null;

    if (isset($_POST['delete']) && $id_tratta) {
        $del = $conn->prepare('DELETE FROM viaggi_tratte WHERE id_tratta=? AND id_viaggio=?');
        $del->bind_param('ii', $id_tratta, $id);
        $del->execute();
    } elseif ($id_tratta) {
        $upd = $conn->prepare('UPDATE viaggi_tratte SET gruppo_alternativa=?, tipo_tratta=?, descrizione=?, origine_testo=?, destinazione_testo=?, distanza_km=?, durata_ore=?, consumo_litri_100km=?, prezzo_carburante_eur_litro=?, pedaggi_eur=?, costo_traghetto_eur=?, costo_volo_eur=?, costo_noleggio_eur=?, altri_costi_eur=?, note=? WHERE id_tratta=? AND id_viaggio=?');
        $upd->bind_param('sssssdddddddddsii', $gruppo, $tipo, $descrizione, $origine, $destinazione, $distanza, $durata, $consumo, $prezzo, $pedaggi, $traghetto, $volo, $noleggio, $altri, $note, $id_tratta, $id);
        $upd->execute();
    } else {
        $ins = $conn->prepare('INSERT INTO viaggi_tratte (id_viaggio, gruppo_alternativa, tipo_tratta, descrizione, origine_testo, destinazione_testo, distanza_km, durata_ore, consumo_litri_100km, prezzo_carburante_eur_litro, pedaggi_eur, costo_traghetto_eur, costo_volo_eur, costo_noleggio_eur, altri_costi_eur, note) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $ins->bind_param('isssssddddddddds', $id, $gruppo, $tipo, $descrizione, $origine, $destinazione, $distanza, $durata, $consumo, $prezzo, $pedaggi, $traghetto, $volo, $noleggio, $altri, $note);
        $ins->execute();
        if (!$grp) { $grp = $gruppo; }
    }
    header('Location: vacanze_tratte.php?id=' . $id . '&grp=' . urlencode($gruppo));
    exit;
}

// Recupera tratte
$trStmt = $conn->prepare('SELECT * FROM viaggi_tratte WHERE id_viaggio=? AND gruppo_alternativa=? ORDER BY id_tratta');
$trStmt->bind_param('is', $id, $grp);
$trStmt->execute();
$trRes = $trStmt->get_result();
?>
<div class="container text-white">
  <a href="vacanze_view.php?id=<?= $id ?>" class="btn btn-outline-light mb-3">← Indietro</a>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="vacanze.php">Vacanze</a></li>
      <li class="breadcrumb-item"><a href="vacanze_view.php?id=<?= $id ?>"><?= htmlspecialchars($viaggio['titolo']) ?></a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($grp) ?></li>
    </ol>
  </nav>
  <div class="d-flex justify-content-between mb-3">
    <h4 class="m-0">Tratte - <?= htmlspecialchars($grp) ?></h4>
    <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#trattaModal" id="btnAdd">Aggiungi</button>
  </div>

  <?php if ($trRes->num_rows === 0): ?>
    <p class="text-muted">Nessuna tratta.</p>
  <?php else: ?>
    <div class="list-group">
      <?php while ($row = $trRes->fetch_assoc()): ?>
        <button type="button" class="list-group-item list-group-item-action bg-dark text-white" data-bs-toggle="modal" data-bs-target="#trattaModal"
          data-id="<?= (int)$row['id_tratta'] ?>" data-gruppo="<?= htmlspecialchars($row['gruppo_alternativa'], ENT_QUOTES) ?>" data-tipo="<?= htmlspecialchars($row['tipo_tratta'], ENT_QUOTES) ?>"
          data-descrizione="<?= htmlspecialchars($row['descrizione'], ENT_QUOTES) ?>" data-origine="<?= htmlspecialchars($row['origine_testo'], ENT_QUOTES) ?>" data-destinazione="<?= htmlspecialchars($row['destinazione_testo'], ENT_QUOTES) ?>"
          data-distanza="<?= (float)$row['distanza_km'] ?>" data-durata="<?= (float)$row['durata_ore'] ?>" data-consumo="<?= (float)$row['consumo_litri_100km'] ?>"
          data-prezzo="<?= (float)$row['prezzo_carburante_eur_litro'] ?>" data-pedaggi="<?= (float)$row['pedaggi_eur'] ?>" data-traghetto="<?= (float)$row['costo_traghetto_eur'] ?>"
          data-volo="<?= (float)$row['costo_volo_eur'] ?>" data-noleggio="<?= (float)$row['costo_noleggio_eur'] ?>" data-altri="<?= (float)$row['altri_costi_eur'] ?>" data-note="<?= htmlspecialchars($row['note'], ENT_QUOTES) ?>">
          <div class="d-flex justify-content-between">
            <span><?= htmlspecialchars($row['descrizione'] ?: $row['tipo_tratta']) ?></span>
            <i class="bi bi-pencil"></i>
          </div>
        </button>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>
</div>

<div class="modal fade" id="trattaModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Tratta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_tratta" id="modal-id">
        <div class="mb-3">
          <label class="form-label">Gruppo alternativa</label>
          <input type="text" class="form-control" name="gruppo_alternativa" id="modal-gruppo" value="<?= htmlspecialchars($grp) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Tipo tratta</label>
          <select class="form-select" name="tipo_tratta" id="modal-tipo">
            <option value="auto">Auto</option>
            <option value="aereo">Aereo</option>
            <option value="traghetto">Traghetto</option>
            <option value="treno">Treno</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Descrizione</label>
          <input type="text" class="form-control" name="descrizione" id="modal-descrizione">
        </div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Origine</label>
            <input type="text" class="form-control" name="origine_testo" id="modal-origine">
          </div>
          <div class="col-md-6">
            <label class="form-label">Destinazione</label>
            <input type="text" class="form-control" name="destinazione_testo" id="modal-destinazione">
          </div>
        </div>
        <div class="row g-2 mt-2">
          <div class="col-md-6">
            <label class="form-label">Distanza (km)</label>
            <input type="number" step="0.01" class="form-control" name="distanza_km" id="modal-distanza">
          </div>
          <div class="col-md-6">
            <label class="form-label">Durata (ore)</label>
            <input type="number" step="0.01" class="form-control" name="durata_ore" id="modal-durata">
          </div>
        </div>
        <div class="row g-2 mt-2">
          <div class="col-md-6">
            <label class="form-label">Consumo L/100km</label>
            <input type="number" step="0.01" class="form-control" name="consumo_litri_100km" id="modal-consumo">
          </div>
          <div class="col-md-6">
            <label class="form-label">Prezzo carburante €/L</label>
            <input type="number" step="0.001" class="form-control" name="prezzo_carburante_eur_litro" id="modal-prezzo">
          </div>
        </div>
        <div class="row g-2 mt-2">
          <div class="col-md-6">
            <label class="form-label">Pedaggi €</label>
            <input type="number" step="0.01" class="form-control" name="pedaggi_eur" id="modal-pedaggi">
          </div>
          <div class="col-md-6">
            <label class="form-label">Traghetto €</label>
            <input type="number" step="0.01" class="form-control" name="costo_traghetto_eur" id="modal-traghetto">
          </div>
        </div>
        <div class="row g-2 mt-2">
          <div class="col-md-6">
            <label class="form-label">Volo €</label>
            <input type="number" step="0.01" class="form-control" name="costo_volo_eur" id="modal-volo">
          </div>
          <div class="col-md-6">
            <label class="form-label">Noleggio €</label>
            <input type="number" step="0.01" class="form-control" name="costo_noleggio_eur" id="modal-noleggio">
          </div>
        </div>
        <div class="mb-3 mt-2">
          <label class="form-label">Altri costi €</label>
          <input type="number" step="0.01" class="form-control" name="altri_costi_eur" id="modal-altri">
        </div>
        <div class="mb-3">
          <label class="form-label">Note</label>
          <textarea class="form-control" name="note" id="modal-note"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
        <button type="submit" class="btn btn-primary">Salva</button>
        <button type="submit" name="delete" value="1" class="btn btn-danger ms-auto" id="modal-delete">Elimina</button>
      </div>
    </form>
  </div>
</div>
<script>
const trattaModal = document.getElementById('trattaModal');
trattaModal.addEventListener('show.bs.modal', event => {
  const button = event.relatedTarget;
  const idField = document.getElementById('modal-id');
  const delBtn = document.getElementById('modal-delete');
  if (button.id === 'btnAdd') {
    trattaModal.querySelector('form').reset();
    idField.value = '';
    document.getElementById('modal-gruppo').value = '<?= htmlspecialchars($grp, ENT_QUOTES) ?>';
    delBtn.classList.add('d-none');
  } else {
    idField.value = button.dataset.id;
    document.getElementById('modal-gruppo').value = button.dataset.gruppo;
    document.getElementById('modal-tipo').value = button.dataset.tipo;
    document.getElementById('modal-descrizione').value = button.dataset.descrizione;
    document.getElementById('modal-origine').value = button.dataset.origine;
    document.getElementById('modal-destinazione').value = button.dataset.destinazione;
    document.getElementById('modal-distanza').value = button.dataset.distanza;
    document.getElementById('modal-durata').value = button.dataset.durata;
    document.getElementById('modal-consumo').value = button.dataset.consumo;
    document.getElementById('modal-prezzo').value = button.dataset.prezzo;
    document.getElementById('modal-pedaggi').value = button.dataset.pedaggi;
    document.getElementById('modal-traghetto').value = button.dataset.traghetto;
    document.getElementById('modal-volo').value = button.dataset.volo;
    document.getElementById('modal-noleggio').value = button.dataset.noleggio;
    document.getElementById('modal-altri').value = button.dataset.altri;
    document.getElementById('modal-note').value = button.dataset.note;
    delBtn.classList.remove('d-none');
  }
});
</script>
<?php include 'includes/footer.php'; ?>
