<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/vacanze_spese_assoc.php';
require_once 'includes/vacanze_alloggio_link.php';
require_once 'includes/permissions.php';


$id = (int)($_GET['id'] ?? 0);
$alt = (int)($_GET['alt'] ?? 0);
$id_alloggio = (int)($_GET['id_alloggio'] ?? 0);
$duplica = isset($_GET['duplica']);

if (!has_permission($conn, 'table:viaggi', 'view')) { http_response_code(403); exit('Accesso negato'); }
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
    if (!isset($_POST['delete']) && !($id_alloggio && !$duplica ? $canUpdateSpesa : $canInsertSpesa)) { http_response_code(403); exit('Accesso negato'); }
    if (!hash_equals($_SESSION['vacanze_csrf'] ?? '', (string)($_POST['vacanze_csrf'] ?? '')) || empty($_SESSION['vacanze_csrf'])) { http_response_code(403); exit('Token non valido'); }
    if (isset($_POST['delete']) && $duplica) { http_response_code(400); exit('Operazione non valida'); }
    $postedSpesaId = (int)($_POST['id_alloggio'] ?? 0);
    if ($postedSpesaId !== ($duplica ? 0 : $id_alloggio)) { http_response_code(400); exit('Identificativo spesa non valido'); }
    if (isset($_POST['separa_scenario'])) {
        if (!$id_alloggio || $duplica || !$canInsertSpesa || !$canUpdateSpesa) {
            http_response_code(403); exit('Operazione non consentita');
        }
        $targetAlt = filter_var($_POST['separa_scenario'], FILTER_VALIDATE_INT);
        if (!$targetAlt || $targetAlt <= 0) { http_response_code(422); exit('Scenario non valido'); }
        try {
            $newId = vacanze_separa_spesa_scenario($conn, 'viaggi_alloggi', $id_alloggio, $id, (int)$targetAlt);
        } catch (Throwable $e) {
            http_response_code(422);
            exit('Impossibile separare la spesa: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
        header('Location: vacanze_alloggi_dettaglio.php?id=' . $id . '&alt=' . (int)$targetAlt . '&id_alloggio=' . $newId);
        exit;
    }
    $selectedAltIds = array_map('intval', (array)($_POST['id_alternative'] ?? []));
    $id_alt = $selectedAltIds[0] ?? null;
    $giorno = (int)($_POST['giorno_indice'] ?? 0);
    $nome = $_POST['nome_alloggio'] ?? null;
    $indirizzo = $_POST['indirizzo'] ?? null;
    $lat = (($_POST['lat'] ?? '') !== '') ? (float)$_POST['lat'] : null;
    $lng = (($_POST['lng'] ?? '') !== '') ? (float)$_POST['lng'] : null;
    $checkin = $_POST['data_checkin'] ?: null;
    $checkout = $_POST['data_checkout'] ?: null;
    $tipoCosto=(string)($_POST['tipo_costo'] ?? 'notte');
    if (!in_array($tipoCosto,['notte','totale'],true)) { http_response_code(400); exit('Tipo di costo non valido'); }
    $rawCosto=(string)($_POST['importo_alloggio_eur'] ?? '');
    if(!isset($_POST['delete']) && ($rawCosto==='' || !is_numeric($rawCosto) || (float)$rawCosto<0)){
        http_response_code(400);exit('Inserisci un importo valido');
    }
    $importo=(float)$rawCosto;
    $costo=$tipoCosto==='notte'?$importo:0.0;
    $costoTotale=$tipoCosto==='totale'?$importo:null;
    $note = $_POST['note'] ?? null;
    $linkRaw = trim((string)($_POST['link_alloggio'] ?? ''));
    $linkAlloggio = $linkRaw === '' ? '' : vacanze_alloggio_url_valido($linkRaw);
    if ($linkRaw !== '' && $linkAlloggio === false) { http_response_code(400); exit('Link alloggio non valido: usa http:// o https://'); }
    $note = vacanze_alloggio_note_con_link($note, $linkAlloggio);
    $pagato = isset($_POST['pagato']) ? 1 : 0;

    $conn->begin_transaction();
    if ($id_alloggio && !$duplica) {
        $sharedCheck = $conn->prepare('SELECT COUNT(*) AS totale FROM viaggi_alloggi_alternative WHERE id_alloggio=?');
        $sharedCheck->bind_param('i', $id_alloggio);
        $sharedCheck->execute();
        $sharedCount = (int)$sharedCheck->get_result()->fetch_assoc()['totale'];
        $sharedCheck->close();
        if ($sharedCount > 1 && ($_POST['conferma_spesa_condivisa'] ?? '') !== '1') {
            $conn->rollback();
            http_response_code(422);
            exit('Questa spesa appartiene a più scenari. Conferma esplicitamente la modifica o eliminazione su tutti gli scenari collegati.');
        }
    }
    try {
    if (isset($_POST['delete']) && $id_alloggio && !$duplica) {
        $del = $conn->prepare('DELETE FROM viaggi_alloggi WHERE id_alloggio=? AND id_viaggio=?');
        $del->bind_param('ii', $id_alloggio, $id);
        if (!$del->execute()) { throw new RuntimeException('Eliminazione fallita'); }
    } elseif ($id_alloggio && !$duplica) {
        $upd = $conn->prepare('UPDATE viaggi_alloggi SET id_viaggio_alternativa=?, giorno_indice=?, nome_alloggio=?, indirizzo=?, lat=?, lng=?, data_checkin=?, data_checkout=?, costo_notte_eur=?, costo_totale_eur=?, note=?, pagato=? WHERE id_alloggio=? AND id_viaggio=?');
        $upd->bind_param('iissddssddsiii', $id_alt, $giorno, $nome, $indirizzo, $lat, $lng, $checkin, $checkout, $costo, $costoTotale, $note, $pagato, $id_alloggio, $id);
        if (!$upd->execute()) { throw new RuntimeException('Aggiornamento fallito'); }
        vacanze_sync_spesa_alternative($conn, 'viaggi_alloggi', $id_alloggio, $id, $selectedAltIds);
    } else {
        $ins = $conn->prepare('INSERT INTO viaggi_alloggi (id_viaggio, id_viaggio_alternativa, giorno_indice, nome_alloggio, indirizzo, lat, lng, data_checkin, data_checkout, costo_notte_eur, costo_totale_eur, note, pagato) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $ins->bind_param('iiissddssddsi', $id, $id_alt, $giorno, $nome, $indirizzo, $lat, $lng, $checkin, $checkout, $costo, $costoTotale, $note, $pagato);
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
    'costo_totale_eur' => null,
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
<div class="container text-white vw">
  <a href="vacanze_tratte.php?id=<?= $id ?>&alt=<?= $alt ?>" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-3"><?= $duplica ? 'Duplica' : ($id_alloggio ? 'Modifica' : 'Nuovo') ?> alloggio</h4>
  <form method="post">
    <?php if ($id_alloggio && !$duplica && count($selectedAlternativeIds) > 1): ?>
      <div class="alert alert-warning">
        Questa spesa è condivisa da <?= count($selectedAlternativeIds) ?> scenari. Le modifiche ai dati o l'eliminazione avranno effetto su tutti gli scenari collegati.
        <label class="d-block mt-2"><input type="checkbox" name="conferma_spesa_condivisa" value="1" required> Confermo di voler modificare o eliminare questa spesa condivisa.</label>
      </div>
    <?php endif; ?>
    <?php if ($id_alloggio && !$duplica && count($selectedAlternativeIds) > 1 && $canInsertSpesa && $canUpdateSpesa): ?>
      <div class="mb-3">
        <label for="separa_scenario_id_alloggio" class="form-label">Modifica soltanto uno scenario</label>
        <div class="d-flex gap-2 flex-wrap">
          <select id="separa_scenario_id_alloggio" name="separa_scenario" class="form-select" style="max-width: 320px">
            <?php foreach ($selectedAlternativeIds as $scenarioId): ?>
              <option value="<?= (int)$scenarioId ?>"><?= htmlspecialchars($alternative[$scenarioId] ?? (string)$scenarioId, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-outline-primary" formnovalidate onclick="return confirm('Creare una copia indipendente per questo scenario? La spesa originale resterà negli altri scenari.')">Separa e modifica</button>
        </div>
        <small class="text-muted">Crea una copia per lo scenario scelto, senza modificare gli altri. Le modifiche saranno salvate nella pagina successiva.</small>
      </div>
    <?php endif; ?>
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
    <?php $tipoCostoAttuale=isset($alloggio['costo_totale_eur']) && $alloggio['costo_totale_eur']!==null ? 'totale' : 'notte'; ?>
    <fieldset class="mb-3 mt-3">
      <legend class="fs-6">Come vuoi inserire il prezzo?</legend>
      <div class="d-flex flex-wrap gap-3">
        <label><input type="radio" name="tipo_costo" value="notte" <?= $tipoCostoAttuale==='notte'?'checked':'' ?>> Prezzo per notte</label>
        <label><input type="radio" name="tipo_costo" value="totale" <?= $tipoCostoAttuale==='totale'?'checked':'' ?>> Totale soggiorno</label>
      </div>
    </fieldset>
    <div class="mb-3">
      <label class="form-label" for="importo_alloggio_eur" id="labelImportoAlloggio">Prezzo per notte (€)</label>
      <input id="importo_alloggio_eur" type="number" min="0" step="0.01" required class="form-control" name="importo_alloggio_eur" value="<?= htmlspecialchars((string)($tipoCostoAttuale==='totale' ? $alloggio['costo_totale_eur'] : $alloggio['costo_notte_eur']), ENT_QUOTES, 'UTF-8') ?>">
      <div class="form-text" id="riepilogoCostoAlloggio" aria-live="polite"></div>
    </div>
    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" name="pagato" id="pagato_alloggio" <?= !empty($alloggio['pagato']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="pagato_alloggio">Già pagato</label>
    </div>
    <div class="mb-3">
      <label class="form-label" for="link_alloggio">Link alloggio</label>
      <input type="url" class="form-control" id="link_alloggio" name="link_alloggio" maxlength="2048" placeholder="https://www.airbnb.it/rooms/..." value="<?= htmlspecialchars(vacanze_alloggio_link_da_note($alloggio['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      <div class="form-text">Visibile e cliccabile anche nella pagina di condivisione.</div>
    </div>
    <div class="mb-3">
      <label class="form-label">Note</label>
      <textarea class="form-control" name="note"><?= htmlspecialchars($alloggio['note']) ?></textarea>
    </div>
    <div class="d-flex justify-content-between mt-3">
      <button type="submit" class="btn btn-primary">Salva</button>
      <?php if ($id_alloggio && !$duplica && $canDeleteSpesa): ?>
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
const costoInput=document.getElementById('importo_alloggio_eur');
const labelCosto=document.getElementById('labelImportoAlloggio');
const riepilogoCosto=document.getElementById('riepilogoCostoAlloggio');
function aggiornaCostoAlloggio(){
 const tipo=document.querySelector('input[name="tipo_costo"]:checked')?.value || 'notte';
 labelCosto.textContent=tipo==='totale'?'Totale soggiorno (€)':'Prezzo per notte (€)';
 const start=checkinInput.value,end=checkoutInput.value;
 const notti=start&&end?Math.round((new Date(end+'T12:00:00')-new Date(start+'T12:00:00'))/86400000):0;
 const val=parseFloat(costoInput.value);
 if(tipo==='notte')riepilogoCosto.textContent=notti>0&&Number.isFinite(val)?`Totale stimato: € ${ (notti*val).toLocaleString('it-IT',{minimumFractionDigits:2,maximumFractionDigits:2}) } per ${notti} notti`:'Il totale sarà calcolato dalle date di check-in e check-out.';
 else riepilogoCosto.textContent=Number.isFinite(val)?`Totale del soggiorno: € ${val.toLocaleString('it-IT',{minimumFractionDigits:2,maximumFractionDigits:2})}`:'L’importo indicato sarà il totale, senza moltiplicazione per le notti.';
}
document.querySelectorAll('input[name="tipo_costo"]').forEach(el=>el.addEventListener('change',aggiornaCostoAlloggio));
[costoInput,checkinInput,checkoutInput].forEach(el=>el.addEventListener('input',aggiornaCostoAlloggio));
aggiornaCostoAlloggio();

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
