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
    $data_inizio = $_POST['data_inizio'] ?? null;
    $data_fine = $_POST['data_fine'] ?? null;
    $stato = $_POST['stato'] ?? 'idea';
    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE viaggi SET titolo=?, data_inizio=?, data_fine=?, stato=? WHERE id_viaggio=?');
        $stmt->bind_param('ssssi', $titolo, $data_inizio, $data_fine, $stato, $id);
    } else {
        $stmt = $conn->prepare('INSERT INTO viaggi (titolo, data_inizio, data_fine, stato) VALUES (?,?,?,?)');
        $stmt->bind_param('ssss', $titolo, $data_inizio, $data_fine, $stato);
    }
    $stmt->execute();
    $id = $id ?: $stmt->insert_id;
    header('Location: vacanze_view.php?id=' . $id);
    exit;
}

$data = ['titolo' => '', 'data_inizio' => '', 'data_fine' => '', 'stato' => 'idea'];
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
      <label class="form-label">Data inizio</label>
      <input type="date" name="data_inizio" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['data_inizio']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Data fine</label>
      <input type="date" name="data_fine" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['data_fine']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Stato</label>
      <select name="stato" class="form-select bg-dark text-white border-secondary">
        <?php foreach(['idea','shortlist','pianificato','prenotato','fatto','scartato'] as $s): ?>
        <option value="<?= $s ?>" <?= $data['stato']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary w-100">Salva</button>
  </form>
  <p class="mt-4 small">Modificare i valori predefiniti di consumo (<?= $DEFAULT_CONSUMO ?> L/100km) e prezzo carburante (<?= $DEFAULT_PREZZO ?> €/L) modificando le variabili all'inizio di questo file.</p>
</div>
<?php include 'includes/footer.php'; ?>
