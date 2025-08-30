<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

// Default carburante modificabili qui
$DEFAULT_CONSUMO = 7.0; // L/100km
$DEFAULT_PREZZO = 1.8; // €/L

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$luoghi = [];
$res = $conn->query("SELECT id_luogo, nome, lat, lng FROM viaggi_luoghi ORDER BY nome");
if ($res) {
    $luoghi = $res->fetch_all(MYSQLI_ASSOC);
}
$foto_luoghi = [];
$resFoto = $conn->query("SELECT id_luogo, photo_reference FROM viaggi_luogo_foto");
if ($resFoto) {
    while ($r = $resFoto->fetch_assoc()) {
        $foto_luoghi[$r['id_luogo']][] = $r['photo_reference'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id_viaggio'] ?? 0);
    $titolo = $_POST['titolo'] ?? '';
    $id_luogo = $_POST['id_luogo'] !== '' ? (int)$_POST['id_luogo'] : null;
    $nuovo_luogo = trim($_POST['nuovo_luogo'] ?? '');
    if ($nuovo_luogo !== '') {
        $stmt = $conn->prepare('INSERT INTO viaggi_luoghi (nome) VALUES (?)');
        $stmt->bind_param('s', $nuovo_luogo);
        $stmt->execute();
        $id_luogo = $stmt->insert_id;
    }
    $data_inizio = $_POST['data_inizio'] ?? null;
    $data_fine = $_POST['data_fine'] ?? null;
    $notti = $_POST['notti'] !== '' ? (int)$_POST['notti'] : null;
    $persone = $_POST['persone'] !== '' ? (int)$_POST['persone'] : null;
    $stato = $_POST['stato'] ?? 'idea';
    $priorita = $_POST['priorita'] !== '' ? (int)$_POST['priorita'] : null;
    $visibilita = $_POST['visibilita'] ?? 'private';
    $breve_descrizione = $_POST['breve_descrizione'] ?? '';
    $note = $_POST['note'] ?? '';
    $meteo_previsto_json = $_POST['meteo_previsto_json'] ?? '';
    $meteo_aggiornato_il = $_POST['meteo_aggiornato_il'] ?? null;
    $foto_url = $_POST['foto_url'] ?? null;
    if ($meteo_aggiornato_il) {
        $meteo_aggiornato_il = date('Y-m-d H:i:s', strtotime($meteo_aggiornato_il));
    }

    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE viaggi SET titolo=?, id_luogo=?, data_inizio=?, data_fine=?, notti=?, persone=?, stato=?, priorita=?, visibilita=?, breve_descrizione=?, note=?, foto_url=?, meteo_previsto_json=?, meteo_aggiornato_il=? WHERE id_viaggio=?');
        $stmt->bind_param('sissiisisssssssi', $titolo, $id_luogo, $data_inizio, $data_fine, $notti, $persone, $stato, $priorita, $visibilita, $breve_descrizione, $note, $foto_url, $meteo_previsto_json, $meteo_aggiornato_il, $id);
    } else {
        $stmt = $conn->prepare('INSERT INTO viaggi (titolo, id_luogo, data_inizio, data_fine, notti, persone, stato, priorita, visibilita, breve_descrizione, note, foto_url, meteo_previsto_json, meteo_aggiornato_il) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->bind_param('sissiisisssssss', $titolo, $id_luogo, $data_inizio, $data_fine, $notti, $persone, $stato, $priorita, $visibilita, $breve_descrizione, $note, $foto_url, $meteo_previsto_json, $meteo_aggiornato_il);
    }
  $stmt->execute();
  $id = $id ?: $stmt->insert_id;
  header('Location: vacanze_view.php?id=' . $id);
  exit;
}

$data = [
    'titolo' => '',
    'id_luogo' => null,
    'data_inizio' => '',
    'data_fine' => '',
    'notti' => null,
    'persone' => null,
    'stato' => 'idea',
    'priorita' => null,
    'visibilita' => 'private',
    'breve_descrizione' => '',
    'note' => '',
    'foto_url' => '',
    'meteo_previsto_json' => '',
    'meteo_aggiornato_il' => ''
];
if ($id > 0) {
    $stmt = $conn->prepare('SELECT * FROM viaggi WHERE id_viaggio=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $data = $res->fetch_assoc();
    }
}
?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="vacanze.php">Vacanze</a></li>
      <?php if ($id > 0): ?>
        <li class="breadcrumb-item"><a href="vacanze_view.php?id=<?= $id ?>"><?= htmlspecialchars($data['titolo']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page">Modifica</li>
      <?php else: ?>
        <li class="breadcrumb-item active" aria-current="page">Nuovo viaggio</li>
      <?php endif; ?>
    </ol>
  </nav>
  <h4 class="mb-3"><?= $id > 0 ? 'Modifica viaggio' : 'Nuovo viaggio' ?></h4>
  <form method="post" class="bg-dark p-3 rounded">
    <input type="hidden" name="id_viaggio" value="<?= (int)$id ?>">
    <div class="mb-3">
      <label class="form-label">Titolo</label>
      <input type="text" name="titolo" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['titolo']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Luogo <a href="vacanze_luogo_modifica.php" id="gestisci-luogo" class="btn btn-sm btn-outline-light ms-2" target="_blank" rel="noopener noreferrer">Gestisci</a></label>
      <select name="id_luogo" class="form-select bg-dark text-white border-secondary">
        <option value="">-- Seleziona --</option>
        <?php foreach($luoghi as $l): ?>
        <option value="<?= $l['id_luogo'] ?>" data-lat="<?= $l['lat'] ?>" data-lng="<?= $l['lng'] ?>" data-fotos='<?= htmlspecialchars(json_encode($foto_luoghi[$l['id_luogo']] ?? [])) ?>' <?= $data['id_luogo']==$l['id_luogo'] ? 'selected' : '' ?>><?= htmlspecialchars($l['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="nuovo_luogo" class="form-control bg-dark text-white border-secondary mt-2" placeholder="Aggiungi nuovo luogo">
      <div class="mt-2">
        <label class="form-label">Foto</label>
        <select name="foto_url" id="foto-url-select" class="form-select bg-dark text-white border-secondary">
          <option value="">-- Nessuna --</option>
        </select>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label">Data inizio</label>
      <input type="date" name="data_inizio" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['data_inizio']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Data fine</label>
      <input type="date" name="data_fine" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['data_fine']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Notti</label>
      <input type="number" name="notti" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['notti'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Persone</label>
      <input type="number" name="persone" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['persone'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Stato</label>
      <select name="stato" class="form-select bg-dark text-white border-secondary">
        <?php foreach(['idea','shortlist','pianificato','prenotato','fatto','scartato'] as $s): ?>
        <option value="<?= $s ?>" <?= $data['stato']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Priorità</label><br>
      <?php foreach ([3=>'Bassa',2=>'Media',1=>'Alta'] as $val => $label): ?>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="priorita" id="priorita<?= $val ?>" value="<?= $val ?>" <?= (string)$data['priorita'] === (string)$val ? 'checked' : '' ?>>
        <label class="form-check-label" for="priorita<?= $val ?>"><?= $label ?></label>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="mb-3">
      <label class="form-label">Visibilità</label>
      <select name="visibilita" class="form-select bg-dark text-white border-secondary">
        <?php foreach(['private','shared','public'] as $v): ?>
        <option value="<?= $v ?>" <?= $data['visibilita']===$v?'selected':'' ?>><?= ucfirst($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Breve descrizione</label>
      <input type="text" name="breve_descrizione" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['breve_descrizione']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Note</label>
      <textarea name="note" class="form-control bg-dark text-white border-secondary" rows="3"><?= htmlspecialchars($data['note']) ?></textarea>
    </div>
    <div class="mb-3">
      <button type="button" id="aggiorna-meteo" class="btn btn-secondary w-100">Aggiorna meteo</button>
      <p class="small mt-2" id="meteo-info"><?= $data['meteo_aggiornato_il'] ? 'Ultimo aggiornamento: '.htmlspecialchars($data['meteo_aggiornato_il']) : 'Meteo non aggiornato' ?></p>
    </div>
    <input type="hidden" name="meteo_previsto_json" value="<?= htmlspecialchars($data['meteo_previsto_json']) ?>">
    <input type="hidden" name="meteo_aggiornato_il" value="<?= htmlspecialchars($data['meteo_aggiornato_il']) ?>">
    <button class="btn btn-primary w-100">Salva</button>
  </form>
  <p class="mt-4 small">Modificare i valori predefiniti di consumo (<?= $DEFAULT_CONSUMO ?> L/100km) e prezzo carburante (<?= $DEFAULT_PREZZO ?> €/L) modificando le variabili all'inizio di questo file.</p>
</div>
<script>
const luogoSel = document.querySelector('select[name="id_luogo"]');
const fotoSel = document.getElementById('foto-url-select');
const gestisciBtn = document.getElementById('gestisci-luogo');
const fotoSelezionata = <?= json_encode($data['foto_url']) ?>;
function aggiornaFoto(){
  const opt = luogoSel.options[luogoSel.selectedIndex];
  let fotos = [];
  try { fotos = JSON.parse(opt.getAttribute('data-fotos') || '[]'); } catch(e){}
  fotoSel.innerHTML = '<option value="">-- Nessuna --</option>';
  fotos.forEach(ref => {
    const url = `https://maps.googleapis.com/maps/api/place/photo?maxwidth=400&photoreference=${ref}&key=<?= $config['GOOGLE_MAPS_API'] ?>`;
    const option = document.createElement('option');
    option.value = url;
    option.textContent = ref;
    if (url === fotoSelezionata) option.selected = true;
    fotoSel.appendChild(option);
  });
}
function aggiornaGestisci(){
  const id = luogoSel.value;
  gestisciBtn.href = id ? `vacanze_luogo_modifica.php?id=${id}` : 'vacanze_luogo_modifica.php';
  gestisciBtn.target = '_blank';
  gestisciBtn.rel = 'noopener noreferrer';
}
luogoSel.addEventListener('change', () => { aggiornaFoto(); aggiornaGestisci(); });
document.addEventListener('DOMContentLoaded', () => { aggiornaFoto(); aggiornaGestisci(); });
</script>
<script>
document.getElementById('aggiorna-meteo').addEventListener('click', async function(){
  const sel = document.querySelector('select[name="id_luogo"]');
  const opt = sel.options[sel.selectedIndex];
  const lat = opt.dataset.lat;
  const lng = opt.dataset.lng;
  if(!lat || !lng){
    alert('Coordinate non disponibili per questo luogo');
    return;
  }
  const url = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lng}&daily=weathercode,temperature_2m_max,temperature_2m_min&timezone=auto`;
  try {
    const resp = await fetch(url);
    if(!resp.ok) throw new Error();
    const data = await resp.json();
    document.querySelector('input[name="meteo_previsto_json"]').value = JSON.stringify(data);
    const now = new Date().toISOString().slice(0,19).replace('T',' ');
    document.querySelector('input[name="meteo_aggiornato_il"]').value = now;
    document.getElementById('meteo-info').textContent = 'Ultimo aggiornamento: ' + now;
  } catch(e){
    alert('Impossibile aggiornare il meteo');
  }
});
</script>
<?php include 'includes/footer.php'; ?>
