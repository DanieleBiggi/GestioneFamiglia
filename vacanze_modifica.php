<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

// Default carburante modificabili qui
$DEFAULT_CONSUMO = 7.0; // L/100km
$DEFAULT_PREZZO = 1.8; // €/L

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id_viaggio'] ?? 0);
    $titolo = $_POST['titolo'] ?? '';
    $id_luogo = $_POST['id_luogo'] !== '' ? (int)$_POST['id_luogo'] : null;
    $data_inizio = $_POST['data_inizio'] ?? null;
    $data_fine = $_POST['data_fine'] ?? null;
    $notti = $_POST['notti'] !== '' ? (int)$_POST['notti'] : null;
    $persone = $_POST['persone'] !== '' ? (int)$_POST['persone'] : null;
    $stato = $_POST['stato'] ?? 'idea';
    $priorita = $_POST['priorita'] !== '' ? (int)$_POST['priorita'] : null;
    $visibilita = $_POST['visibilita'] ?? 'private';
    $token_condivisione = $_POST['token_condivisione'] ?? '';
    $foto_url = $_POST['foto_url'] ?? '';
    $breve_descrizione = $_POST['breve_descrizione'] ?? '';
    $note = $_POST['note'] ?? '';
    $meteo_previsto_json = $_POST['meteo_previsto_json'] ?? '';
    $meteo_aggiornato_il = $_POST['meteo_aggiornato_il'] ?? null;
    if ($meteo_aggiornato_il) {
        $meteo_aggiornato_il = date('Y-m-d H:i:s', strtotime($meteo_aggiornato_il));
    }

    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE viaggi SET titolo=?, id_luogo=?, data_inizio=?, data_fine=?, notti=?, persone=?, stato=?, priorita=?, visibilita=?, token_condivisione=?, foto_url=?, breve_descrizione=?, note=?, meteo_previsto_json=?, meteo_aggiornato_il=? WHERE id_viaggio=?');
        $stmt->bind_param('sissiisisssssssi', $titolo, $id_luogo, $data_inizio, $data_fine, $notti, $persone, $stato, $priorita, $visibilita, $token_condivisione, $foto_url, $breve_descrizione, $note, $meteo_previsto_json, $meteo_aggiornato_il, $id);
    } else {
        $stmt = $conn->prepare('INSERT INTO viaggi (titolo, id_luogo, data_inizio, data_fine, notti, persone, stato, priorita, visibilita, token_condivisione, foto_url, breve_descrizione, note, meteo_previsto_json, meteo_aggiornato_il) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->bind_param('sissiisisssssss', $titolo, $id_luogo, $data_inizio, $data_fine, $notti, $persone, $stato, $priorita, $visibilita, $token_condivisione, $foto_url, $breve_descrizione, $note, $meteo_previsto_json, $meteo_aggiornato_il);
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
    'token_condivisione' => '',
    'foto_url' => '',
    'breve_descrizione' => '',
    'note' => '',
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
  <h4 class="mb-3"><?= $id > 0 ? 'Modifica viaggio' : 'Nuovo viaggio' ?></h4>
  <form method="post" class="bg-dark p-3 rounded">
    <input type="hidden" name="id_viaggio" value="<?= (int)$id ?>">
    <div class="mb-3">
      <label class="form-label">Titolo</label>
      <input type="text" name="titolo" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['titolo']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">ID Luogo</label>
      <input type="number" name="id_luogo" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['id_luogo'] ?? '') ?>">
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
      <label class="form-label">Priorità</label>
      <input type="number" name="priorita" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['priorita'] ?? '') ?>">
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
      <label class="form-label">Token condivisione</label>
      <input type="text" name="token_condivisione" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['token_condivisione']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Foto URL</label>
      <input type="text" name="foto_url" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['foto_url']) ?>">
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
      <label class="form-label">Meteo previsto (JSON)</label>
      <textarea name="meteo_previsto_json" class="form-control bg-dark text-white border-secondary" rows="3"><?= htmlspecialchars($data['meteo_previsto_json']) ?></textarea>
    </div>
    <div class="mb-3">
      <label class="form-label">Meteo aggiornato il</label>
      <input type="datetime-local" name="meteo_aggiornato_il" class="form-control bg-dark text-white border-secondary" value="<?= $data['meteo_aggiornato_il'] ? date('Y-m-d\TH:i', strtotime($data['meteo_aggiornato_il'])) : '' ?>">
    </div>
    <button class="btn btn-primary w-100">Salva</button>
  </form>
  <p class="mt-4 small">Modificare i valori predefiniti di consumo (<?= $DEFAULT_CONSUMO ?> L/100km) e prezzo carburante (<?= $DEFAULT_PREZZO ?> €/L) modificando le variabili all'inizio di questo file.</p>
</div>
<?php include 'includes/footer.php'; ?>
