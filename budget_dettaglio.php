<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$idUtente  = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$data = [
    'tipologia' => '',
    'importo' => '',
    'id_salvadanaio' => 0,
    'descrizione' => '',
    'data_inizio' => '',
    'data_scadenza' => '',
    'tipologia_spesa' => '',
    'id_budget' => 0,
    'id_utente' => $idUtente
];

// Recupera elenco salvadanai
$salvadanai = [];
$stmt = $conn->prepare("SELECT id_salvadanaio, nome_salvadanaio FROM salvadanai WHERE id_famiglia = ?");
$stmt->bind_param('i', $idFamiglia);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $salvadanai[] = $row;
}
$stmt->close();

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM budget WHERE id_budget = ? AND id_famiglia = ?");
    $stmt->bind_param('ii', $id, $idFamiglia);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $data = $res->fetch_assoc();
    } else {
        echo '<p class="text-danger">Record non trovato.</p>';
        include 'includes/footer.php';
        exit;
    }
    $stmt->close();
}

$isOwner = ($data['id_utente'] ?? $idUtente) == $idUtente;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_budget       = isset($_POST['id_budget']) ? (int)$_POST['id_budget'] : 0;
    $tipologia       = $_POST['tipologia'] ?? '';
    $importo         = isset($_POST['importo']) ? (float)$_POST['importo'] : 0;
    $id_salvadanaio  = isset($_POST['id_salvadanaio']) && $_POST['id_salvadanaio'] !== '' ? (int)$_POST['id_salvadanaio'] : null;
    $descrizione     = $_POST['descrizione'] ?? '';
    $data_inizio     = $_POST['data_inizio'] ?? null;
    $data_scadenza   = $_POST['data_scadenza'] ?? null;
    $tipologia_spesa = $_POST['tipologia_spesa'] ?? '';

    if ($id_budget > 0) {
        $stmt = $conn->prepare("UPDATE budget SET tipologia=?, importo=?, id_salvadanaio=?, descrizione=?, data_inizio=?, data_scadenza=?, tipologia_spesa=? WHERE id_budget=? AND id_famiglia=?");
        $stmt->bind_param('sdissssii', $tipologia, $importo, $id_salvadanaio, $descrizione, $data_inizio, $data_scadenza, $tipologia_spesa, $id_budget, $idFamiglia);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO budget (tipologia, importo, id_salvadanaio, descrizione, data_inizio, data_scadenza, tipologia_spesa, id_utente, id_famiglia) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('sdissssii', $tipologia, $importo, $id_salvadanaio, $descrizione, $data_inizio, $data_scadenza, $tipologia_spesa, $idUtente, $idFamiglia);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: budget.php');
    exit;
}
?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-4">Dettaglio Budget</h4>
</div>
<form method="post" class="bg-dark text-white p-3 rounded">
  <div class="mb-3">
    <label class="form-label">Tipologia</label>
    <input type="text" name="tipologia" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['tipologia']) ?>" <?= $isOwner ? '' : 'disabled' ?>>
  </div>
  <div class="mb-3">
    <label class="form-label">Importo</label>
    <input type="number" step="0.01" name="importo" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['importo']) ?>" <?= $isOwner ? '' : 'disabled' ?>>
  </div>
  <div class="mb-3">
    <label class="form-label">Salvadanaio</label>
    <select name="id_salvadanaio" class="form-control bg-dark text-white border-secondary" <?= $isOwner ? '' : 'disabled' ?>>
      <option value="">-- Seleziona --</option>
      <?php foreach ($salvadanai as $s): ?>
        <option value="<?= (int)$s['id_salvadanaio'] ?>" <?= $data['id_salvadanaio'] == $s['id_salvadanaio'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nome_salvadanaio']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label">Descrizione</label>
    <textarea name="descrizione" class="form-control bg-dark text-white border-secondary" rows="3" <?= $isOwner ? '' : 'disabled' ?>><?= htmlspecialchars($data['descrizione']) ?></textarea>
  </div>
  <div class="mb-3">
    <label class="form-label">Data inizio</label>
    <input type="date" name="data_inizio" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['data_inizio']) ?>" <?= $isOwner ? '' : 'disabled' ?>>
  </div>
  <div class="mb-3">
    <label class="form-label">Data scadenza</label>
    <input type="date" name="data_scadenza" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['data_scadenza']) ?>" <?= $isOwner ? '' : 'disabled' ?>>
  </div>
  <div class="mb-3">
    <label class="form-label">Tipologia Spesa</label>
    <input type="text" name="tipologia_spesa" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['tipologia_spesa']) ?>" <?= $isOwner ? '' : 'disabled' ?>>
  </div>
  <input type="hidden" name="id_utente" value="<?= (int)$idUtente ?>">
  <input type="hidden" name="id_famiglia" value="<?= (int)$idFamiglia ?>">
  <?php if ($data['id_budget']): ?>
    <input type="hidden" name="id_budget" value="<?= (int)$data['id_budget'] ?>">
  <?php endif; ?>
  <?php if ($isOwner): ?>
    <button type="submit" class="btn btn-primary w-100">Salva</button>
  <?php endif; ?>
</form>

<?php include 'includes/footer.php'; ?>
