<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';

$id = (int)($_GET['id'] ?? 0);
$alt = (int)($_GET['alt'] ?? 0);
$id_pasto = (int)($_GET['id_pasto'] ?? 0);
$duplica = isset($_GET['duplica']);

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
    $id_pasto = (int)($_POST['id_pasto'] ?? 0);
    $id_alt = (int)($_POST['id_viaggio_alternativa'] ?? $alt);
    $giorno = (int)($_POST['giorno_indice'] ?? 0);
    $tipo = $_POST['tipo_pasto'] ?? 'pranzo';
    $nome = $_POST['nome_locale'] ?? null;
    $indirizzo = $_POST['indirizzo'] ?? null;
    $lat = $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
    $lng = $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;
    $tipologia = $_POST['tipologia'] ?? 'ristorante';
    $costo = $_POST['costo_medio_eur'] !== '' ? (float)$_POST['costo_medio_eur'] : null;
    $note = $_POST['note'] ?? null;
    $pagato = isset($_POST['pagato']) ? 1 : 0;

    if (isset($_POST['delete']) && $id_pasto) {
        $del = $conn->prepare('DELETE FROM viaggi_pasti WHERE id_pasto=? AND id_viaggio=?');
        $del->bind_param('ii', $id_pasto, $id);
        $del->execute();
    } elseif ($id_pasto) {
        $upd = $conn->prepare('UPDATE viaggi_pasti SET id_viaggio_alternativa=?, giorno_indice=?, tipo_pasto=?, nome_locale=?, indirizzo=?, lat=?, lng=?, tipologia=?, costo_medio_eur=?, note=?, pagato=? WHERE id_pasto=? AND id_viaggio=?');
        $upd->bind_param('iisssddsdsiii', $id_alt, $giorno, $tipo, $nome, $indirizzo, $lat, $lng, $tipologia, $costo, $note, $pagato, $id_pasto, $id);
        $upd->execute();
    } else {
        $ins = $conn->prepare('INSERT INTO viaggi_pasti (id_viaggio, id_viaggio_alternativa, giorno_indice, tipo_pasto, nome_locale, indirizzo, lat, lng, tipologia, costo_medio_eur, note, pagato) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $ins->bind_param('iiisssddsdsi', $id, $id_alt, $giorno, $tipo, $nome, $indirizzo, $lat, $lng, $tipologia, $costo, $note, $pagato);
        $ins->execute();
    }
    header('Location: vacanze_tratte.php?id=' . $id . '&alt=' . $id_alt);
    exit;
}

$pasto = [
    'id_viaggio_alternativa' => $alt,
    'giorno_indice' => '',
    'tipo_pasto' => 'pranzo',
    'nome_locale' => '',
    'indirizzo' => '',
    'lat' => '',
    'lng' => '',
    'tipologia' => 'ristorante',
    'costo_medio_eur' => '',
    'note' => '',
    'pagato' => 0,
];

if ($id_pasto) {
    $pStmt = $conn->prepare('SELECT * FROM viaggi_pasti WHERE id_pasto=? AND id_viaggio=?');
    $pStmt->bind_param('ii', $id_pasto, $id);
    $pStmt->execute();
    $pasto = $pStmt->get_result()->fetch_assoc();
    if (!$pasto) {
        echo '<p class="text-danger">Pasto non trovato</p>';
        include 'includes/footer.php';
        exit;
    }
    $alt = (int)$pasto['id_viaggio_alternativa'];
    if ($duplica) {
        $id_pasto = 0;
        $pasto['pagato'] = 0;
    }
}

$altStmt = $conn->prepare('SELECT id_viaggio_alternativa, breve_descrizione FROM viaggi_alternative WHERE id_viaggio=? ORDER BY id_viaggio_alternativa');
$altStmt->bind_param('i', $id);
$altStmt->execute();
$altRes = $altStmt->get_result();
$alternative = [];
while ($row = $altRes->fetch_assoc()) { $alternative[$row['id_viaggio_alternativa']] = $row['breve_descrizione']; }
$alt_desc = $alternative[$alt] ?? '';
?>
<?php include 'includes/header.php'; ?>
<div class="container text-white">
  <a href="vacanze_tratte.php?id=<?= $id ?>&alt=<?= $alt ?>" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-3"><?= $duplica ? 'Duplica' : ($id_pasto ? 'Modifica' : 'Nuovo') ?> pasto</h4>
  <form method="post">
    <input type="hidden" name="id_pasto" value="<?= (int)$id_pasto ?>">
    <div class="mb-3">
      <label class="form-label">Alternativa</label>
      <select class="form-select" name="id_viaggio_alternativa">
        <?php foreach ($alternative as $aid => $descr): ?>
          <option value="<?= $aid ?>"<?= $pasto['id_viaggio_alternativa']==$aid ? ' selected' : '' ?>><?= htmlspecialchars($descr) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Giorno indice</label>
      <input type="number" class="form-control" name="giorno_indice" value="<?= htmlspecialchars($pasto['giorno_indice']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Tipo pasto</label>
      <select class="form-select" name="tipo_pasto">
        <?php foreach (['colazione','pranzo','cena'] as $tp): ?>
          <option value="<?= $tp ?>"<?= $pasto['tipo_pasto']==$tp ? ' selected' : '' ?>><?= ucfirst($tp) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Nome locale</label>
      <input type="text" class="form-control" name="nome_locale" id="nome_locale" value="<?= htmlspecialchars($pasto['nome_locale']) ?>">
    </div>
    <input type="hidden" name="indirizzo" id="indirizzo" value="<?= htmlspecialchars($pasto['indirizzo']) ?>">
    <input type="hidden" name="lat" id="lat" value="<?= htmlspecialchars($pasto['lat']) ?>">
    <input type="hidden" name="lng" id="lng" value="<?= htmlspecialchars($pasto['lng']) ?>">
    <div class="mb-3">
      <label class="form-label">Tipologia</label>
      <select class="form-select" name="tipologia">
        <?php foreach (['ristorante','pizzeria','cucinato'] as $tp): ?>
          <option value="<?= $tp ?>"<?= $pasto['tipologia']==$tp ? ' selected' : '' ?>><?= ucfirst($tp) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Costo medio €</label>
      <input type="number" step="0.01" class="form-control" name="costo_medio_eur" value="<?= htmlspecialchars($pasto['costo_medio_eur']) ?>">
    </div>
    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" name="pagato" id="pagato_pasto" <?= !empty($pasto['pagato']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="pagato_pasto">Già pagato</label>
    </div>
    <div class="mb-3">
      <label class="form-label">Note</label>
      <textarea class="form-control" name="note"><?= htmlspecialchars($pasto['note']) ?></textarea>
    </div>
    <div class="d-flex justify-content-between mt-3">
      <button type="submit" class="btn btn-primary">Salva</button>
      <?php if ($id_pasto && !$duplica): ?>
        <button type="submit" name="delete" value="1" class="btn btn-danger">Elimina</button>
      <?php endif; ?>
    </div>
  </form>
</div>
<script>
let autocomplete;
async function initAutocomplete(){
  const {Autocomplete} = await google.maps.importLibrary('places');
  autocomplete = new Autocomplete(document.getElementById('nome_locale'), {
    types:['establishment'],
    fields:['name','formatted_address','geometry']
  });
  autocomplete.addListener('place_changed', () => {
    const place = autocomplete.getPlace();
    if(!place) return;
    document.getElementById('nome_locale').value = place.name || '';
    document.getElementById('indirizzo').value = place.formatted_address || '';
    document.getElementById('lat').value = place.geometry?.location?.lat() || '';
    document.getElementById('lng').value = place.geometry?.location?.lng() || '';
  });
}
window.initAutocomplete = initAutocomplete;
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= $config['GOOGLE_MAPS_API'] ?>&libraries=places&callback=initAutocomplete&loading=async" async defer></script>
<?php include 'includes/footer.php'; ?>
