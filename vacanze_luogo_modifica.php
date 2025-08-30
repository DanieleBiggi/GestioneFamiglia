<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id_luogo'] ?? 0);
    $nome = $_POST['nome'] ?? '';
    $citta = $_POST['citta'] ?? null;
    $regione = $_POST['regione'] ?? null;
    $paese = $_POST['paese'] ?? null;
    $lat = $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
    $lng = $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;
    $url_maps = $_POST['url_maps'] ?? null;
    $sito_web = $_POST['sito_web'] ?? null;
    $place_id = $_POST['place_id'] ?? null;
    $note = $_POST['note'] ?? null;

    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE viaggi_luoghi SET nome=?, citta=?, regione=?, paese=?, lat=?, lng=?, url_maps=?, sito_web=?, place_id=?, note=? WHERE id_luogo=?');
        $stmt->bind_param('ssssddssssi', $nome, $citta, $regione, $paese, $lat, $lng, $url_maps, $sito_web, $place_id, $note, $id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare('INSERT INTO viaggi_luoghi (nome, citta, regione, paese, lat, lng, url_maps, sito_web, place_id, note) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->bind_param('ssssddssss', $nome, $citta, $regione, $paese, $lat, $lng, $url_maps, $sito_web, $place_id, $note);
        $stmt->execute();
        $id = $stmt->insert_id;
    }

    $foto_refs = $_POST['foto_refs'] ?? [];
    if ($id && is_array($foto_refs)) {
        $insFoto = $conn->prepare('INSERT INTO viaggi_luogo_foto (id_luogo, photo_reference) VALUES (?, ?)');
        foreach ($foto_refs as $ref) {
            $insFoto->bind_param('is', $id, $ref);
            $insFoto->execute();
        }
    }

    header('Location: vacanze_luogo_modifica.php?id=' . $id);
    exit;
}

$luogo = [
    'nome' => '',
    'citta' => '',
    'regione' => '',
    'paese' => '',
    'lat' => '',
    'lng' => '',
    'url_maps' => '',
    'sito_web' => '',
    'place_id' => '',
    'note' => ''
];
$foto_esistenti = [];
if ($id > 0) {
    $stmt = $conn->prepare('SELECT * FROM viaggi_luoghi WHERE id_luogo=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $luogo = $res->fetch_assoc();
    }
    $pf = $conn->prepare('SELECT photo_reference FROM viaggi_luogo_foto WHERE id_luogo=?');
    $pf->bind_param('i', $id);
    $pf->execute();
    $foto_esistenti = $pf->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<?php include 'includes/header.php'; ?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="vacanze.php">Vacanze</a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= $id > 0 ? 'Modifica luogo' : 'Nuovo luogo' ?></li>
    </ol>
  </nav>
  <h4 class="mb-3"><?= $id > 0 ? 'Modifica luogo' : 'Nuovo luogo' ?></h4>
  <form method="post" class="bg-dark p-3 rounded">
    <input type="hidden" name="id_luogo" value="<?= (int)$id ?>">
    <div class="mb-3">
      <label class="form-label">Nome</label>
      <input type="text" name="nome" id="place-name" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($luogo['nome']) ?>" required>
    </div>
    <div id="photo-container" class="mb-3 d-flex flex-wrap gap-2">
      <?php foreach ($foto_esistenti as $f): ?>
        <?php $url = 'https://maps.googleapis.com/maps/api/place/photo?maxwidth=200&photo_reference=' . urlencode($f['photo_reference']) . '&key=' . $config['GOOGLE_PLACES_FOTO_API']; ?>
        <label class="d-inline-block">
          <input type="checkbox" name="foto_refs[]" value="<?= htmlspecialchars($f['photo_reference']) ?>" checked>
          <img src="<?= $url ?>" class="img-thumbnail">
        </label>
      <?php endforeach; ?>
    </div>
    <textarea name="note" class="form-control bg-dark text-white border-secondary mb-3" rows="3" placeholder="Note"><?= htmlspecialchars($luogo['note']) ?></textarea>
    <input type="hidden" name="citta" id="citta" value="<?= htmlspecialchars($luogo['citta']) ?>">
    <input type="hidden" name="regione" id="regione" value="<?= htmlspecialchars($luogo['regione']) ?>">
    <input type="hidden" name="paese" id="paese" value="<?= htmlspecialchars($luogo['paese']) ?>">
    <input type="hidden" name="lat" id="lat" value="<?= htmlspecialchars($luogo['lat']) ?>">
    <input type="hidden" name="lng" id="lng" value="<?= htmlspecialchars($luogo['lng']) ?>">
    <input type="hidden" name="url_maps" id="url_maps" value="<?= htmlspecialchars($luogo['url_maps']) ?>">
    <input type="hidden" name="sito_web" id="sito_web" value="<?= htmlspecialchars($luogo['sito_web']) ?>">
    <input type="hidden" name="place_id" id="place_id" value="<?= htmlspecialchars($luogo['place_id']) ?>">
    <button class="btn btn-primary w-100">Salva</button>
  </form>
</div>
<script>
let autocomplete;
async function initAutocomplete() {
  const {Autocomplete} = await google.maps.importLibrary('places');
  autocomplete = new Autocomplete(document.getElementById('place-name'), {
    fields: ['address_components','geometry','name','place_id','photos','url','website']
  });
  autocomplete.addListener('place_changed', async () => {
    const place = autocomplete.getPlace();
    if (!place) return;
    document.getElementById('place_id').value = place.place_id || '';
    document.getElementById('lat').value = place.geometry?.location?.lat() || '';
    document.getElementById('lng').value = place.geometry?.location?.lng() || '';
    document.getElementById('url_maps').value = place.url || '';
    document.getElementById('sito_web').value = place.website || '';
    const comp = {locality:'citta', administrative_area_level_1:'regione', country:'paese'};
    if (place.address_components) {
      for (const c of place.address_components) {
        const type = c.types[0];
        if (comp[type]) document.getElementById(comp[type]).value = c.long_name;
      }
    }
    const container = document.getElementById('photo-container');
    container.innerHTML = '';

    // Recupera gli official photo_reference tramite backend
    if (place.place_id) {
      try {
        const resp = await fetch(`ajax/places_photos.php?place_id=${place.place_id}`);
        const data = await resp.json();
        if (data.photos) {
          data.photos.forEach(ph => {
            const ref = ph.photo_reference;
            if (!ref) return;
            const url = ph.thumb_url;
            const label = document.createElement('label');
            label.className = 'd-inline-block';
            label.innerHTML = `<input type="checkbox" name="foto_refs[]" value="${ref}" class="me-1"><img src="${url}" class="img-thumbnail">`;
            container.appendChild(label);
          });
        }
      } catch (err) {
        console.error('Errore nel recupero dei photo_reference', err);
      }
    }
  });
}
window.initAutocomplete = initAutocomplete;
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= $config['GOOGLE_MAPS_API'] ?>&libraries=places&callback=initAutocomplete&loading=async" async defer></script>
<?php include 'includes/footer.php'; ?>
