<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$grp = $_GET['grp'] ?? '';
$id_tratta = (int)($_GET['id_tratta'] ?? 0);

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
    }
    header('Location: vacanze_tratte.php?id=' . $id . '&grp=' . urlencode($gruppo));
    exit;
}

$tratta = [
    'gruppo_alternativa' => $grp,
    'tipo_tratta' => 'auto',
    'descrizione' => '',
    'origine_testo' => '',
    'destinazione_testo' => '',
    'distanza_km' => '',
    'durata_ore' => '',
    'consumo_litri_100km' => '',
    'prezzo_carburante_eur_litro' => '',
    'pedaggi_eur' => '',
    'costo_traghetto_eur' => '',
    'costo_volo_eur' => '',
    'costo_noleggio_eur' => '',
    'altri_costi_eur' => '',
    'note' => '',
];

if ($id_tratta) {
    $trStmt = $conn->prepare('SELECT * FROM viaggi_tratte WHERE id_tratta=? AND id_viaggio=?');
    $trStmt->bind_param('ii', $id_tratta, $id);
    $trStmt->execute();
    $tratta = $trStmt->get_result()->fetch_assoc();
    if (!$tratta) {
        echo '<p class="text-danger">Tratta non trovata</p>';
        include 'includes/footer.php';
        exit;
    }
}
?>
<div class="container text-white">
  <a href="vacanze_tratte.php?id=<?= $id ?>&grp=<?= urlencode($grp) ?>" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-3"><?= $id_tratta ? 'Modifica' : 'Nuova' ?> tratta</h4>
  <form method="post">
    <input type="hidden" name="id_tratta" value="<?= (int)$id_tratta ?>">
    <div class="mb-3">
      <label class="form-label">Gruppo alternativa</label>
      <input type="text" class="form-control" name="gruppo_alternativa" value="<?= htmlspecialchars($tratta['gruppo_alternativa']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Tipo tratta</label>
      <select class="form-select" name="tipo_tratta">
        <option value="auto"<?= $tratta['tipo_tratta']==='auto' ? ' selected' : '' ?>>Auto</option>
        <option value="aereo"<?= $tratta['tipo_tratta']==='aereo' ? ' selected' : '' ?>>Aereo</option>
        <option value="traghetto"<?= $tratta['tipo_tratta']==='traghetto' ? ' selected' : '' ?>>Traghetto</option>
        <option value="treno"<?= $tratta['tipo_tratta']==='treno' ? ' selected' : '' ?>>Treno</option>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Descrizione</label>
      <input type="text" class="form-control" name="descrizione" value="<?= htmlspecialchars($tratta['descrizione']) ?>">
    </div>
    <div class="row g-2">
      <div class="col-md-6">
        <label class="form-label">Origine</label>
        <input type="text" class="form-control" name="origine_testo" id="origine" value="<?= htmlspecialchars($tratta['origine_testo']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Destinazione</label>
        <input type="text" class="form-control" name="destinazione_testo" id="destinazione" value="<?= htmlspecialchars($tratta['destinazione_testo']) ?>">
      </div>
    </div>
    <div class="row g-2 mt-2">
      <div class="col-md-6">
        <label class="form-label">Distanza (km)</label>
        <input type="number" step="0.01" class="form-control" name="distanza_km" id="distanza" value="<?= htmlspecialchars($tratta['distanza_km']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Durata (ore)</label>
        <input type="number" step="0.01" class="form-control" name="durata_ore" value="<?= htmlspecialchars($tratta['durata_ore']) ?>">
      </div>
    </div>
    <div class="row g-2 mt-2">
      <div class="col-md-6">
        <label class="form-label">Consumo L/100km</label>
        <input type="number" step="0.01" class="form-control" name="consumo_litri_100km" value="<?= htmlspecialchars($tratta['consumo_litri_100km']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Prezzo carburante €/L</label>
        <input type="number" step="0.001" class="form-control" name="prezzo_carburante_eur_litro" value="<?= htmlspecialchars($tratta['prezzo_carburante_eur_litro']) ?>">
      </div>
    </div>
    <div class="row g-2 mt-2">
      <div class="col-md-6">
        <label class="form-label">Pedaggi €</label>
        <input type="number" step="0.01" class="form-control" name="pedaggi_eur" value="<?= htmlspecialchars($tratta['pedaggi_eur']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Traghetto €</label>
        <input type="number" step="0.01" class="form-control" name="costo_traghetto_eur" value="<?= htmlspecialchars($tratta['costo_traghetto_eur']) ?>">
      </div>
    </div>
    <div class="row g-2 mt-2">
      <div class="col-md-6">
        <label class="form-label">Volo €</label>
        <input type="number" step="0.01" class="form-control" name="costo_volo_eur" value="<?= htmlspecialchars($tratta['costo_volo_eur']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Noleggio €</label>
        <input type="number" step="0.01" class="form-control" name="costo_noleggio_eur" value="<?= htmlspecialchars($tratta['costo_noleggio_eur']) ?>">
      </div>
    </div>
    <div class="mb-3 mt-2">
      <label class="form-label">Altri costi €</label>
      <input type="number" step="0.01" class="form-control" name="altri_costi_eur" value="<?= htmlspecialchars($tratta['altri_costi_eur']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Note</label>
      <textarea class="form-control" name="note"><?= htmlspecialchars($tratta['note']) ?></textarea>
    </div>
    <div class="d-flex justify-content-between mt-3">
      <button type="submit" class="btn btn-primary">Salva</button>
      <?php if ($id_tratta): ?>
        <button type="submit" name="delete" value="1" class="btn btn-danger">Elimina</button>
      <?php endif; ?>
    </div>
  </form>
</div>
<script>
let originAutocomplete, destinationAutocomplete;
async function initAutocomplete() {
  const {PlaceAutocompleteElement} = await google.maps.importLibrary('places');
  originAutocomplete = new PlaceAutocompleteElement({inputElement: document.getElementById('origine')});
  destinationAutocomplete = new PlaceAutocompleteElement({inputElement: document.getElementById('destinazione')});
  originAutocomplete.addEventListener('gmpx-placechanged', calculateDistance);
  destinationAutocomplete.addEventListener('gmpx-placechanged', calculateDistance);
}
function calculateDistance() {
  const origin = originAutocomplete.getPlace();
  const destination = destinationAutocomplete.getPlace();
  if (!origin || !destination) return;
  const service = new google.maps.DirectionsService();
  service.route({
    origin: origin.location,
    destination: destination.location,
    travelMode: google.maps.TravelMode.DRIVING
  }, function(response, status) {
    if (status === 'OK') {
      const distanceMeters = response.routes[0].legs[0].distance.value;
      document.getElementById('distanza').value = (distanceMeters / 1000).toFixed(2);
    }
  });
}
window.initAutocomplete = initAutocomplete;
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= $config['GOOGLE_MAPS_API'] ?>&libraries=places&callback=initAutocomplete" async defer></script>
<?php include 'includes/footer.php'; ?>
