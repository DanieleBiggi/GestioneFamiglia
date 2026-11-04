<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/vacanze_spese_assoc.php';
require_once 'includes/permissions.php';


$id = (int)($_GET['id'] ?? 0);
$alt = (int)($_GET['alt'] ?? 0);
$id_alloggio = (int)($_GET['id_alloggio'] ?? 0);
$duplica = isset($_GET['duplica']);

// I permessi vanno verificati anche lato server, non solo nascondendo i pulsanti.
$canViewSpesa = has_permission($conn, 'table:viaggi_alloggi', 'view');
$canInsertSpesa = has_permission($conn, 'table:viaggi_alloggi', 'insert');
$canUpdateSpesa = has_permission($conn, 'table:viaggi_alloggi', 'update');
$canDeleteSpesa = has_permission($conn, 'table:viaggi_alloggi', 'delete');
if (!$canViewSpesa || ($duplica && !$canInsertSpesa) || ($id_alloggio && !$duplica && !$canUpdateSpesa) || (!$id_alloggio && !$canInsertSpesa)) {
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
    if (!isset($_POST['delete']) && !($id_alloggio ? $canUpdateSpesa : $canInsertSpesa)) { http_response_code(403); exit('Accesso negato'); }
    if (!hash_equals($_SESSION['vacanze_csrf'] ?? '', (string)($_POST['vacanze_csrf'] ?? '')) || empty($_SESSION['vacanze_csrf'])) { http_response_code(403); exit('Token non valido'); }
    $postedSpesaId = (int)($_POST['id_alloggio'] ?? 0);
    if ($postedSpesaId !== $id_alloggio) { http_response_code(400); exit('Identificativo spesa non valido'); }
    $selectedAltIds = array_map('intval', (array)($_POST['id_alternative'] ?? []));
    $id_alt = $selectedAltIds[0] ?? null;
    $giorno = (int)($_POST['giorno_indice'] ?? 0);
    $nome = $_POST['nome_alloggio'] ?? null;
    $indirizzo = $_POST['indirizzo'] ?? null;
    $lat = $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
    $lng = $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;
    $checkin = $_POST['data_checkin'] ?: null;
    $checkout = $_POST['data_checkout'] ?: null;
    $costo = (float)($_POST['costo_notte_eur'] ?? 0);
    $note = $_POST['note'] ?? null;
    $pagato = isset($_POST['pagato']) ? 1 : 0;

    $conn->begin_transaction();
    try {
    if (isset($_POST['delete']) && $id_alloggio) {
        $del = $conn->prepare('DELETE FROM viaggi_alloggi WHERE id_alloggio=? AND id_viaggio=?');
        $del->bind_param('ii', $id_alloggio, $id);
        if (!$del->execute()) { throw new RuntimeException('Eliminazione fallita'); }
    } elseif ($id_alloggio) {
        $upd = $conn->prepare('UPDATE viaggi_alloggi SET id_viaggio_alternativa=?, giorno_indice=?, nome_alloggio=?, indirizzo=?, lat=?, lng=?, data_checkin=?, data_checkout=?, costo_notte_eur=?, note=?, pagato=? WHERE id_alloggio=? AND id_viaggio=?');
        $upd->bind_param('iissddsssdsiii', $id_alt, $giorno, $nome, $indirizzo, $lat, $lng, $checkin, $checkout, $costo, $note, $pagato, $id_alloggio, $id);
        if (!$upd->execute()) { throw new RuntimeException('Aggiornamento fallito'); }
        vacanze_sync_spesa_alternative($conn, 'viaggi_alloggi', $id_alloggio, $id, $selectedAltIds);
    } else {
        $ins = $conn->prepare('INSERT INTO viaggi_alloggi (id_viaggio, id_viaggio_alternativa, giorno_indice, nome_alloggio, indirizzo, lat, lng, data_checkin, data_checkout, costo_notte_eur, note, pagato) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $ins->bind_param('iiissddssdsi', $id, $id_alt, $giorno, $nome, $indirizzo, $lat, $lng, $checkin, $checkout, $costo, $note, $pagato);
        if (!$ins->execute()) { throw new RuntimeException('Inserimento fallito'); }
        vacanze_sync_spesa_alternative($conn, 'viaggi_alloggi', (int)$conn->insert_id, $id, $selectedAltIds);
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
$alloggio = [
    'id_viaggio_alternativa' => $alt,
    'giorno_indice' => '',
    'nome_alloggio' => '',
    'indirizzo' => '',
    'lat' => '',
    'lng' => '',
    'data_checkin' => '',
    'data_checkout' => '',
    'costo_notte_eur' => '',
    'note' => '',
    'pagato' => 0,
];

if ($id_alloggio) {
    $aStmt = $conn->prepare('SELECT * FROM viaggi_alloggi WHERE id_alloggio=? AND id_viaggio=?');
    $aStmt->bind_param('ii', $id_alloggio, $id);
    $aStmt->execute();
    $alloggio = $aStmt->get_result()->fetch_assoc();
    if (!$alloggio) {
        echo '<p class="text-danger">Alloggio non trovato</p>';
        include 'includes/footer.php';
        exit;
    }
    if (!$alt) { $alt = (int)$alloggio['id_viaggio_alternativa']; }
    if ($duplica) {
        $id_alloggio = 0;
        $alloggio['pagato'] = 0;
    }
}

$altStmt = $conn->prepare('SELECT id_viaggio_alternativa, breve_descrizione FROM viaggi_alternative WHERE id_viaggio=? ORDER BY id_viaggio_alternativa');
$altStmt->bind_param('i', $id);
$altStmt->execute();
$altRes = $altStmt->get_result();
$alternative = [];
while ($row = $altRes->fetch_assoc()) { $alternative[$row['id_viaggio_alternativa']] = $row['breve_descrizione']; }
$selectedAlternativeIds = $id_alloggio && !$duplica ? vacanze_spesa_alternative($conn, 'viaggi_alloggi_alternative', 'id_alloggio', $id_alloggio) : [($alt ?: 0)];
$alt_desc = $alternative[$alt] ?? '';
?>
<?php if (empty($_SESSION['vacanze_csrf'])) { $_SESSION['vacanze_csrf'] = bin2hex(random_bytes(32)); } ?>
<?php include 'includes/header.php'; ?>
<div class="container text-white">
  <a href="vacanze_tratte.php?id=<?= $id ?>&alt=<?= $alt ?>" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-3"><?= $duplica ? 'Duplica' : ($id_alloggio ? 'Modifica' : 'Nuovo') ?> alloggio</h4>
  <form method="post">
    <input type="hidden" name="vacanze_csrf" value="<?= htmlspecialchars($_SESSION['vacanze_csrf'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id_alloggio" value="<?= (int)$id_alloggio ?>">
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
      <label class="form-label">Giorno indice</label>
      <input type="number" class="form-control" name="giorno_indice" value="<?= htmlspecialchars($alloggio['giorno_indice']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Nome alloggio</label>
      <input type="text" class="form-control" name="nome_alloggio" id="nome_alloggio" value="<?= htmlspecialchars($alloggio['nome_alloggio']) ?>">
    </div>
    <input type="hidden" name="indirizzo" id="indirizzo" value="<?= htmlspecialchars($alloggio['indirizzo']) ?>">
    <input type="hidden" name="lat" id="lat" value="<?= htmlspecialchars($alloggio['lat']) ?>">
    <input type="hidden" name="lng" id="lng" value="<?= htmlspecialchars($alloggio['lng']) ?>">
    <div class="row g-2 mt-2">
      <div class="col">
        <label class="form-label">Checkin</label>
        <input type="date" class="form-control" name="data_checkin" id="data_checkin" value="<?= htmlspecialchars($alloggio['data_checkin']) ?>">
      </div>
      <div class="col">
        <label class="form-label">Checkout</label>
        <input type="date" class="form-control" name="data_checkout" id="data_checkout" value="<?= htmlspecialchars($alloggio['data_checkout']) ?>">
      </div>
    </div>
    <div class="mb-3 mt-2">
      <label class="form-label">Costo notte €</label>
      <input type="number" step="0.01" class="form-control" name="costo_notte_eur" value="<?= htmlspecialchars($alloggio['costo_notte_eur']) ?>">
    </div>
    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" name="pagato" id="pagato_alloggio" <?= !empty($alloggio['pagato']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="pagato_alloggio">Già pagato</label>
    </div>
    <div class="mb-3">
      <label class="form-label">Note</label>
      <textarea class="form-control" name="note"><?= htmlspecialchars($alloggio['note']) ?></textarea>
    </div>
    <div class="d-flex justify-content-between mt-3">
      <button type="submit" class="btn btn-primary">Salva</button>
      <?php if ($id_alloggio && !$duplica): ?>
        <button type="submit" name="delete" value="1" class="btn btn-danger">Elimina</button>
      <?php endif; ?>
    </div>
  </form>
</div>
<script>
const checkinInput = document.getElementById('data_checkin');
const checkoutInput = document.getElementById('data_checkout');
function updateCheckoutMin() {
  checkoutInput.min = checkinInput.value;
  if (checkoutInput.value && checkoutInput.value < checkinInput.value) {
    checkoutInput.value = checkinInput.value;
  }
}
checkinInput.addEventListener('change', updateCheckoutMin);
updateCheckoutMin();

let autocomplete;
async function initAutocomplete() {
  const {Autocomplete} = await google.maps.importLibrary('places');
  autocomplete = new Autocomplete(document.getElementById('nome_alloggio'), {
    types: ['establishment'],
    fields: ['name','formatted_address','geometry']
  });
  autocomplete.addListener('place_changed', () => {
    const place = autocomplete.getPlace();
    if (!place) return;
    document.getElementById('nome_alloggio').value = place.name || '';
    document.getElementById('indirizzo').value = place.formatted_address || '';
    document.getElementById('lat').value = place.geometry?.location?.lat() || '';
    document.getElementById('lng').value = place.geometry?.location?.lng() || '';
  });
}
window.initAutocomplete = initAutocomplete;
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= $config['GOOGLE_MAPS_API'] ?>&libraries=places&callback=initAutocomplete&loading=async" async defer></script>
<?php include 'includes/footer.php'; ?>
