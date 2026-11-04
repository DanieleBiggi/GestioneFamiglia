<?php 

include 'includes/session_check.php'; ?>
<?php

include 'includes/db.php';
require_once 'includes/vacanze_spese_assoc.php';
require_once 'includes/permissions.php';



$id = (int)($_GET['id'] ?? 0);
$alt = (int)($_GET['alt'] ?? 0);
$id_tratta = (int)($_GET['id_tratta'] ?? 0);
$duplica = isset($_GET['duplica']);

if (!has_permission($conn, 'table:viaggi', 'view')) { http_response_code(403); exit('Accesso negato'); }
// I permessi vanno verificati anche lato server, non solo nascondendo i pulsanti.
$canViewSpesa = has_permission($conn, 'table:viaggi_tratte', 'view');
$canInsertSpesa = has_permission($conn, 'table:viaggi_tratte', 'insert');
$canUpdateSpesa = has_permission($conn, 'table:viaggi_tratte', 'update');
$canDeleteSpesa = has_permission($conn, 'table:viaggi_tratte', 'delete');
if (!$canViewSpesa || ($duplica && !$canInsertSpesa) || ($id_tratta && !$duplica && !$canUpdateSpesa) || (!$id_tratta && !$canInsertSpesa)) {
    http_response_code(403); exit('Accesso negato');
}

// Recupera info viaggio per breadcrumb
$stmt = $conn->prepare('SELECT titolo FROM viaggi WHERE id_viaggio=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$viaggio = $stmt->get_result()->fetch_assoc();
if (!$viaggio) {
    include 'includes/header.php';
    echo '<p class="text-danger">Viaggio non trovato</p>';
    include 'includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete']) && !$canDeleteSpesa) { http_response_code(403); exit('Accesso negato'); }
    if (!isset($_POST['delete']) && !($id_tratta && !$duplica ? $canUpdateSpesa : $canInsertSpesa)) { http_response_code(403); exit('Accesso negato'); }
    if (!hash_equals($_SESSION['vacanze_csrf'] ?? '', (string)($_POST['vacanze_csrf'] ?? '')) || empty($_SESSION['vacanze_csrf'])) { http_response_code(403); exit('Token non valido'); }
    if (isset($_POST['delete']) && $duplica) { http_response_code(400); exit('Operazione non valida'); }
    $postedSpesaId = (int)($_POST['id_tratta'] ?? 0);
    if ($postedSpesaId !== ($duplica ? 0 : $id_tratta)) { http_response_code(400); exit('Identificativo spesa non valido'); }
    $selectedAltIds = array_map('intval', (array)($_POST['id_alternative'] ?? []));
    $id_alt = $selectedAltIds[0] ?? null;
    $tipo = $_POST['tipo_tratta'] ?? 'auto';
    $descrizione = $_POST['descrizione'] ?? null;
    $origine = $_POST['origine_testo'] ?? null;
    $origine_lat = (($_POST['origine_lat'] ?? '') !== '') ? (float)$_POST['origine_lat'] : null;
    $origine_lng = (($_POST['origine_lng'] ?? '') !== '') ? (float)$_POST['origine_lng'] : null;
    $destinazione = $_POST['destinazione_testo'] ?? null;
    $destinazione_lat = (($_POST['destinazione_lat'] ?? '') !== '') ? (float)$_POST['destinazione_lat'] : null;
    $destinazione_lng = (($_POST['destinazione_lng'] ?? '') !== '') ? (float)$_POST['destinazione_lng'] : null;
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
    $pagato = isset($_POST['pagato']) ? 1 : 0;

    $conn->begin_transaction();
    try {
    if (isset($_POST['delete']) && $id_tratta && !$duplica) {
        $del = $conn->prepare('DELETE FROM viaggi_tratte WHERE id_tratta=? AND id_viaggio=?');
        $del->bind_param('ii', $id_tratta, $id);
        if (!$del->execute()) { throw new RuntimeException('Eliminazione fallita'); }
    } elseif ($id_tratta && !$duplica) {
        $upd = $conn->prepare('UPDATE viaggi_tratte SET id_viaggio_alternativa=?, tipo_tratta=?, descrizione=?, origine_testo=?, origine_lat=?, origine_lng=?, destinazione_testo=?, destinazione_lat=?, destinazione_lng=?, distanza_km=?, durata_ore=?, consumo_litri_100km=?, prezzo_carburante_eur_litro=?, pedaggi_eur=?, costo_traghetto_eur=?, costo_volo_eur=?, costo_noleggio_eur=?, altri_costi_eur=?, note=?, pagato=? WHERE id_tratta=? AND id_viaggio=?');
        $upd->bind_param('isssddsdddddddddddsiii', $id_alt, $tipo, $descrizione, $origine, $origine_lat, $origine_lng, $destinazione, $destinazione_lat, $destinazione_lng, $distanza, $durata, $consumo, $prezzo, $pedaggi, $traghetto, $volo, $noleggio, $altri, $note, $pagato, $id_tratta, $id);
        if (!$upd->execute()) { throw new RuntimeException('Aggiornamento fallito'); }
        vacanze_sync_spesa_alternative($conn, 'viaggi_tratte', $id_tratta, $id, $selectedAltIds);
    } else {
        $ins = $conn->prepare('INSERT INTO viaggi_tratte (id_viaggio, id_viaggio_alternativa, tipo_tratta, descrizione, origine_testo, origine_lat, origine_lng, destinazione_testo, destinazione_lat, destinazione_lng, distanza_km, durata_ore, consumo_litri_100km, prezzo_carburante_eur_litro, pedaggi_eur, costo_traghetto_eur, costo_volo_eur, costo_noleggio_eur, altri_costi_eur, note, pagato) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $ins->bind_param('iisssddsdddddddddddsi', $id, $id_alt, $tipo, $descrizione, $origine, $origine_lat, $origine_lng, $destinazione, $destinazione_lat, $destinazione_lng, $distanza, $durata, $consumo, $prezzo, $pedaggi, $traghetto, $volo, $noleggio, $altri, $note, $pagato);
        if (!$ins->execute()) { throw new RuntimeException('Inserimento fallito'); }
        vacanze_sync_spesa_alternative($conn, 'viaggi_tratte', (int)$conn->insert_id, $id, $selectedAltIds);
    }
    $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        http_response_code(400);
        exit('Impossibile salvare la spesa: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
    header('Location: ' . (($alt ?: (int)$id_alt) > 0 ? 'vacanze_tratte.php?id=' . $id . '&alt=' . ($alt ?: (int)$id_alt) : 'vacanze_spese_non_assegnate.php?id=' . $id));
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
    'pagato' => 0,
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
    if (!$alt) { $alt = (int)$tratta['id_viaggio_alternativa']; }
    if ($duplica) {
        $id_tratta = 0;
        $tratta['pagato'] = 0;
    }
}

$altStmt = $conn->prepare('SELECT id_viaggio_alternativa, breve_descrizione FROM viaggi_alternative WHERE id_viaggio=? ORDER BY id_viaggio_alternativa');
$altStmt->bind_param('i', $id);
$altStmt->execute();
$altRes = $altStmt->get_result();
$alternative = [];
while ($row = $altRes->fetch_assoc()) { $alternative[$row['id_viaggio_alternativa']] = $row['breve_descrizione']; }
$selectedAlternativeIds = $id_tratta && !$duplica ? vacanze_spesa_alternative($conn, 'viaggi_tratte_alternative', 'id_tratta', $id_tratta) : [($alt ?: 0)];
$alt_desc = $alternative[$alt] ?? '';
?>
<?php if (empty($_SESSION['vacanze_csrf'])) { $_SESSION['vacanze_csrf'] = bin2hex(random_bytes(32)); } ?>
<?php include 'includes/header.php'; ?>
<div class="container text-white">
  <a href="vacanze_tratte.php?id=<?= $id ?>&alt=<?= $alt ?>" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-3"><?= $duplica ? 'Duplica' : ($id_tratta ? 'Modifica' : 'Nuova') ?> tratta</h4>
  <form method="post">
    <input type="hidden" name="vacanze_csrf" value="<?= htmlspecialchars($_SESSION['vacanze_csrf'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id_tratta" value="<?= (int)$id_tratta ?>">
    <div class="mb-3">
      <label class="form-label">Alternativa</label>
      <div class="border border-secondary rounded p-2">
        <?php foreach ($alternative as $aid => $descr): ?>
          <label class="d-block mb-1"><input type="checkbox" name="id_alternative[]" value="<?= (int)$aid ?>" <?= in_array((int)$aid, $selectedAlternativeIds, true) ? 'checked' : '' ?>> <?= htmlspecialchars($descr) ?></label>
        <?php endforeach; ?>
        <small class="text-muted">Puoi scegliere più scenari o nessuno (spesa non assegnata).</small>
      </div>
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
    <div class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label">Origine</label>
        <input type="text" class="form-control" name="origine_testo" id="origine" value="<?= htmlspecialchars($tratta['origine_testo']) ?>">
      </div>
      <div class="col-md-2 d-flex align-items-center">
        <button type="button" class="btn btn-outline-light w-100" id="swap-tratta" aria-label="Inverti origine e destinazione">
          ↕
        </button>
      </div>
      <div class="col-md-5">
        <label class="form-label">Destinazione</label>
        <input type="text" class="form-control" name="destinazione_testo" id="destinazione" value="<?= htmlspecialchars($tratta['destinazione_testo']) ?>">
      </div>
    </div>
    <input type="hidden" name="origine_lat" id="origine_lat" value="<?= htmlspecialchars($tratta['origine_lat']) ?>">
    <input type="hidden" name="origine_lng" id="origine_lng" value="<?= htmlspecialchars($tratta['origine_lng']) ?>">
    <input type="hidden" name="destinazione_lat" id="destinazione_lat" value="<?= htmlspecialchars($tratta['destinazione_lat']) ?>">
    <input type="hidden" name="destinazione_lng" id="destinazione_lng" value="<?= htmlspecialchars($tratta['destinazione_lng']) ?>">
    <div class="row g-2 mt-2">
      <div class="col-md-6 auto-only" id="distanza-col">
        <label class="form-label">Distanza (km)</label>
        <input type="number" step="0.01" class="form-control" name="distanza_km" id="distanza" value="<?= htmlspecialchars($tratta['distanza_km']) ?>">
      </div>
      <div class="col-md-6" id="durata-col">
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
    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" name="pagato" id="pagato_tratta" <?= !empty($tratta['pagato']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="pagato_tratta">Già pagata</label>
    </div>
    <div class="mb-3">
      <label class="form-label">Note</label>
      <textarea class="form-control" name="note"><?= htmlspecialchars($tratta['note']) ?></textarea>
    </div>
    <div class="d-flex justify-content-between mt-3">
      <button type="submit" class="btn btn-primary">Salva</button>
      <?php if ($id_tratta && !$duplica && $canDeleteSpesa): ?>
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
  const swapBtn = document.getElementById('swap-tratta');
  if (swapBtn) {
    swapBtn.addEventListener('click', swapLocations);
  }
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
  const durataCol = document.getElementById('durata-col');
  if (durataCol) {
    durataCol.classList.toggle('col-md-12', tipo !== 'auto');
    durataCol.classList.toggle('col-md-6', tipo === 'auto');
  }
  if (tipo === 'auto') {
    calculateDistance();
  }
}
let originAutocomplete, destinationAutocomplete;
async function initAutocomplete() {
  const {Autocomplete} = await google.maps.importLibrary('places');
  originAutocomplete = new Autocomplete(document.getElementById('origine'));
  destinationAutocomplete = new Autocomplete(document.getElementById('destinazione'));
  originAutocomplete.addListener('place_changed', () => handlePlace('origine', originAutocomplete));
  destinationAutocomplete.addListener('place_changed', () => handlePlace('destinazione', destinationAutocomplete));
}
function swapLocations() {
  const originInput = document.getElementById('origine');
  const destinationInput = document.getElementById('destinazione');
  const originLatInput = document.getElementById('origine_lat');
  const originLngInput = document.getElementById('origine_lng');
  const destinationLatInput = document.getElementById('destinazione_lat');
  const destinationLngInput = document.getElementById('destinazione_lng');
  [originInput.value, destinationInput.value] = [destinationInput.value, originInput.value];
  [originLatInput.value, destinationLatInput.value] = [destinationLatInput.value, originLatInput.value];
  [originLngInput.value, destinationLngInput.value] = [destinationLngInput.value, originLngInput.value];
  calculateDistance();
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
  const service = new google.maps.DirectionsService();
  service.route({
    origin: {lat: originLat, lng: originLng},
    destination: {lat: destLat, lng: destLng},
    travelMode: google.maps.TravelMode.DRIVING
  }, (result, status) => {
    if (status === 'OK' && result.routes[0] && result.routes[0].legs[0].distance) {
      document.getElementById('distanza').value = (result.routes[0].legs[0].distance.value / 1000).toFixed(2);
    }
  });
}
window.initAutocomplete = initAutocomplete;
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= $config['GOOGLE_MAPS_API'] ?>&libraries=places&callback=initAutocomplete&loading=async" async defer></script>
<?php include 'includes/footer.php'; ?>
