<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$alt = (int)($_GET['alt'] ?? 0);
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
    $id_alt = (int)($_POST['id_viaggio_alternativa'] ?? $alt);
    $tipo = $_POST['tipo_tratta'] ?? 'auto';
    $descrizione = $_POST['descrizione'] ?? null;
    $origine = $_POST['origine_testo'] ?? null;
    $origine_lat = $_POST['origine_lat'] !== '' ? (float)$_POST['origine_lat'] : null;
    $origine_lng = $_POST['origine_lng'] !== '' ? (float)$_POST['origine_lng'] : null;
    $destinazione = $_POST['destinazione_testo'] ?? null;
    $destinazione_lat = $_POST['destinazione_lat'] !== '' ? (float)$_POST['destinazione_lat'] : null;
    $destinazione_lng = $_POST['destinazione_lng'] !== '' ? (float)$_POST['destinazione_lng'] : null;
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
        $upd = $conn->prepare('UPDATE viaggi_tratte SET id_viaggio_alternativa=?, tipo_tratta=?, descrizione=?, origine_testo=?, origine_lat=?, origine_lng=?, destinazione_testo=?, destinazione_lat=?, destinazione_lng=?, distanza_km=?, durata_ore=?, consumo_litri_100km=?, prezzo_carburante_eur_litro=?, pedaggi_eur=?, costo_traghetto_eur=?, costo_volo_eur=?, costo_noleggio_eur=?, altri_costi_eur=?, note=? WHERE id_tratta=? AND id_viaggio=?');
        $upd->bind_param('isssddsdddddddddddsii', $id_alt, $tipo, $descrizione, $origine, $origine_lat, $origine_lng, $destinazione, $destinazione_lat, $destinazione_lng, $distanza, $durata, $consumo, $prezzo, $pedaggi, $traghetto, $volo, $noleggio, $altri, $note, $id_tratta, $id);
        $upd->execute();
    } else {
        $ins = $conn->prepare('INSERT INTO viaggi_tratte (id_viaggio, id_viaggio_alternativa, tipo_tratta, descrizione, origine_testo, origine_lat, origine_lng, destinazione_testo, destinazione_lat, destinazione_lng, distanza_km, durata_ore, consumo_litri_100km, prezzo_carburante_eur_litro, pedaggi_eur, costo_traghetto_eur, costo_volo_eur, costo_noleggio_eur, altri_costi_eur, note) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $ins->bind_param('iisssddsddddddddddds', $id, $id_alt, $tipo, $descrizione, $origine, $origine_lat, $origine_lng, $destinazione, $destinazione_lat, $destinazione_lng, $distanza, $durata, $consumo, $prezzo, $pedaggi, $traghetto, $volo, $noleggio, $altri, $note);
        $ins->execute();
    }
    header('Location: vacanze_tratte.php?id=' . $id . '&alt=' . $id_alt);
    exit;
}

$tratta = [
    'id_viaggio_alternativa' => $alt,
    'tipo_tratta' => 'auto',
    'descrizione' => '',
    'origine_testo' => '',
    'origine_lat' => '',
    'origine_lng' => '',
    'destinazione_testo' => '',
    'destinazione_lat' => '',
    'destinazione_lng' => '',
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
    $alt = (int)$tratta['id_viaggio_alternativa'];
}

$altStmt = $conn->prepare('SELECT id_viaggio_alternativa, breve_descrizione FROM viaggi_alternative WHERE id_viaggio=? ORDER BY id_viaggio_alternativa');
$altStmt->bind_param('i', $id);
$altStmt->execute();
$altRes = $altStmt->get_result();
$alternative = [];
while ($row = $altRes->fetch_assoc()) { $alternative[$row['id_viaggio_alternativa']] = $row['breve_descrizione']; }
$alt_desc = $alternative[$alt] ?? '';
?>
<div class="container text-white">
  <a href="vacanze_tratte.php?id=<?= $id ?>&alt=<?= $alt ?>" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-3"><?= $id_tratta ? 'Modifica' : 'Nuova' ?> tratta</h4>
  <form method="post">
    <input type="hidden" name="id_tratta" value="<?= (int)$id_tratta ?>">
    <div class="mb-3">
      <label class="form-label">Alternativa</label>
      <select class="form-select" name="id_viaggio_alternativa">
        <?php foreach ($alternative as $aid => $descr): ?>
          <option value="<?= $aid ?>"<?= $tratta['id_viaggio_alternativa']==$aid ? ' selected' : '' ?>><?= htmlspecialchars($descr) ?></option>
        <?php endforeach; ?>
      </select>
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
    <input type="hidden" name="origine_lat" id="origine_lat" value="<?= htmlspecialchars($tratta['origine_lat']) ?>">
    <input type="hidden" name="origine_lng" id="origine_lng" value="<?= htmlspecialchars($tratta['origine_lng']) ?>">
    <input type="hidden" name="destinazione_lat" id="destinazione_lat" value="<?= htmlspecialchars($tratta['destinazione_lat']) ?>">
    <input type="hidden" name="destinazione_lng" id="destinazione_lng" value="<?= htmlspecialchars($tratta['destinazione_lng']) ?>">
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
    <div class="row g-2 mt-2 auto-only">
      <div class="col-md-6">
        <label class="form-label">Consumo L/100km</label>
        <input type="number" step="0.01" class="form-control" name="consumo_litri_100km" value="<?= htmlspecialchars($tratta['consumo_litri_100km']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Prezzo carburante €/L</label>
        <input type="number" step="0.001" class="form-control" name="prezzo_carburante_eur_litro" value="<?= htmlspecialchars($tratta['prezzo_carburante_eur_litro']) ?>">
      </div>
    </div>
    <div class="row g-2 mt-2 auto-traghetto-row">
      <div class="col-md-6 auto-only">
        <label class="form-label">Pedaggi €</label>
        <input type="number" step="0.01" class="form-control" name="pedaggi_eur" value="<?= htmlspecialchars($tratta['pedaggi_eur']) ?>">
      </div>
      <div class="col-md-6 traghetto-only">
        <label class="form-label">Traghetto €</label>
        <input type="number" step="0.01" class="form-control" name="costo_traghetto_eur" value="<?= htmlspecialchars($tratta['costo_traghetto_eur']) ?>">
      </div>
    </div>
    <div class="row g-2 mt-2 aereo-only">
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
document.addEventListener('DOMContentLoaded', () => {
  const tipoSelect = document.querySelector('select[name="tipo_tratta"]');
  tipoSelect.addEventListener('change', toggleFields);
  toggleFields();
});
function toggleFields() {
  const tipo = document.querySelector('select[name="tipo_tratta"]').value;
  document.querySelectorAll('.auto-only').forEach(el => {
    const show = tipo === 'auto';
    el.style.display = show ? '' : 'none';
    el.querySelectorAll('input').forEach(inp => inp.disabled = !show);
  });
  document.querySelectorAll('.aereo-only').forEach(el => {
    const show = tipo === 'aereo';
    el.style.display = show ? '' : 'none';
    el.querySelectorAll('input').forEach(inp => inp.disabled = !show);
  });
  document.querySelectorAll('.traghetto-only').forEach(el => {
    const show = tipo === 'traghetto';
    el.style.display = show ? '' : 'none';
    el.querySelectorAll('input').forEach(inp => inp.disabled = !show);
  });
  document.querySelectorAll('.auto-traghetto-row').forEach(el => {
    const show = tipo === 'auto' || tipo === 'traghetto';
    el.style.display = show ? '' : 'none';
  });
}
let originAutocomplete, destinationAutocomplete;
async function initAutocomplete() {
  const {Autocomplete} = await google.maps.importLibrary('places');
  originAutocomplete = new Autocomplete(document.getElementById('origine'));
  destinationAutocomplete = new Autocomplete(document.getElementById('destinazione'));
  originAutocomplete.addListener('place_changed', () => handlePlace('origine', originAutocomplete));
  destinationAutocomplete.addListener('place_changed', () => handlePlace('destinazione', destinationAutocomplete));
}
function handlePlace(prefix, autocomplete) {
  const place = autocomplete.getPlace();
  if (place && place.geometry && place.geometry.location) {
    document.getElementById(prefix + '_lat').value = place.geometry.location.lat();
    document.getElementById(prefix + '_lng').value = place.geometry.location.lng();
  }
  calculateDistance();
}
async function calculateDistance() {
  if (document.querySelector('select[name="tipo_tratta"]').value !== 'auto') return;
  const originLat = parseFloat(document.getElementById('origine_lat').value);
  const originLng = parseFloat(document.getElementById('origine_lng').value);
  const destLat = parseFloat(document.getElementById('destinazione_lat').value);
  const destLng = parseFloat(document.getElementById('destinazione_lng').value);
  if (isNaN(originLat) || isNaN(originLng) || isNaN(destLat) || isNaN(destLng)) return;
  try {
    const response = await fetch('https://routes.googleapis.com/directions/v2:computeRoutes?key=<?= $config['GOOGLE_MAPS_API'] ?>', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Goog-FieldMask': 'routes.distanceMeters'
      },
      body: JSON.stringify({
        origin: { location: { latLng: { latitude: originLat, longitude: originLng } } },
        destination: { location: { latLng: { latitude: destLat, longitude: destLng } } },
        travelMode: 'DRIVE'
      })
    });
    const data = await response.json();
    const meters = data.routes && data.routes[0] ? data.routes[0].distanceMeters : null;
    if (meters) {
      document.getElementById('distanza').value = (meters / 1000).toFixed(2);
    }
  } catch (err) {
    console.error('Distance calculation error', err);
  }
}
window.initAutocomplete = initAutocomplete;
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= $config['GOOGLE_MAPS_API'] ?>&libraries=places&callback=initAutocomplete&loading=async" async defer></script>
<?php include 'includes/footer.php'; ?>
