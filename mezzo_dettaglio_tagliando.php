<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/permissions.php';

$idUtente = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$idMezzo = isset($_GET['mezzo']) ? (int)$_GET['mezzo'] : 0;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id === 0) {
    $nome_tagliando = $_POST['nome_tagliando'] ?? '';
    $data_scadenza = $_POST['data_scadenza'] ?? '';
    $attivo = isset($_POST['attivo']) ? 1 : 0;
    $stmt = $conn->prepare("INSERT INTO mezzi_tagliandi (id_mezzo, nome_tagliando, data_scadenza, attivo) VALUES (?,?,?,?)");
    $stmt->bind_param('issi', $idMezzo, $nome_tagliando, $data_scadenza, $attivo);
    $stmt->execute();
    $stmt->close();
    header('Location: mezzo_dettaglio.php?id=' . $idMezzo);
    exit;
}

$data = [
    'nome_tagliando' => '',
    'data_scadenza' => '',
    'attivo' => 1,
    'id_tagliando' => 0,
    'id_mezzo' => $idMezzo,
    'id_utente' => $idUtente
];
$records = [];
if ($id > 0) {
    $stmt = $conn->prepare("SELECT mt.*, m.id_utente FROM mezzi_tagliandi mt JOIN mezzi m ON m.id_mezzo = mt.id_mezzo WHERE mt.id_tagliando = ? AND mt.id_mezzo = ? AND m.id_famiglia = ?");
    $stmt->bind_param('iii', $id, $idMezzo, $idFamiglia);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $data = $res->fetch_assoc();
    } else {
        include 'includes/header.php';
        echo '<p class="text-danger">Record non trovato.</p>';
        include 'includes/footer.php';
        exit;
    }
    $stmt->close();

    $stmtRec = $conn->prepare("SELECT id_m2t, DATE(data_tagliando) AS data_tagliando, km_tagliando FROM mezzi_mezzi2tagliandi WHERE id_tagliando = ? ORDER BY data_tagliando DESC");
    $stmtRec->bind_param('i', $id);
    $stmtRec->execute();
    $resRec = $stmtRec->get_result();
    while ($row = $resRec->fetch_assoc()) {
        $records[] = $row;
    }
    $stmtRec->close();
}

$isOwner = ($data['id_utente'] ?? $idUtente) == $idUtente;

include 'includes/header.php';

if ($id > 0): ?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-3">
    <span id="tagliandoNome"><?= htmlspecialchars($data['nome_tagliando']) ?></span>
    <?php if ($isOwner): ?>
      <i class="bi bi-pencil ms-2" role="button" onclick="openTagliandoModal()"></i>
    <?php endif; ?>
  </h4>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="d-flex align-items-center">
      <h5 class="mb-0 me-3">Interventi</h5>
      <?php if (count($records) > 3): ?>
        <button id="toggleRecords" class="btn btn-link p-0">Mostra tutti</button>
      <?php endif; ?>
    </div>
    <?php if ($isOwner): ?>
      <button class="btn btn-outline-light btn-sm" onclick="openRecordModal()">Aggiungi</button>
    <?php endif; ?>
  </div>
  <ul class="list-group list-group-flush bg-dark" id="recordsList">
    <?php foreach ($records as $idx => $row): ?>
      <li class="list-group-item movement text-white d-flex justify-content-between align-items-center <?= $idx >= 3 ? 'd-none extra-row' : '' ?>"
          data-id="<?= (int)$row['id_m2t'] ?>"
          data-data="<?= htmlspecialchars($row['data_tagliando']) ?>"
          data-km="<?= (int)$row['km_tagliando'] ?>">
        <div class="flex-grow-1"><?= date("d/m/Y", strtotime($row['data_tagliando'])) ?></div>
        <div class="text-end me-2" style="min-width: 90px;"><?= number_format((int)$row['km_tagliando'], 0, ',', '.') ?> km</div>
        <?php if ($isOwner): ?>
          <button class="btn btn-danger btn-sm ms-2" onclick="deleteRecord(event, <?= (int)$row['id_m2t'] ?>)">✕</button>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<!-- Modal tagliando -->
<div class="modal fade" id="tagliandoModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="tagliandoForm">
      <div class="modal-header">
        <h5 class="modal-title">Modifica tagliando</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nome tagliando</label>
          <input type="text" name="nome_tagliando" class="form-control bg-secondary text-white" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Data scadenza</label>
          <input type="date" name="data_scadenza" class="form-control bg-secondary text-white">
        </div>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" name="attivo" id="tagliandoAttivo">
          <label class="form-check-label" for="tagliandoAttivo">Attivo</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal record -->
<div class="modal fade" id="recordModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="recordForm">
      <div class="modal-header">
        <h5 class="modal-title">Intervento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_m2t" id="id_m2t">
        <div class="mb-3">
          <label class="form-label">Data</label>
          <input type="date" name="data_tagliando" class="form-control bg-secondary text-white" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Chilometri</label>
          <input type="number" name="km_tagliando" class="form-control bg-secondary text-white" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>

<script>
const tagliandoData = {
  id: <?= (int)$data['id_tagliando'] ?>,
  id_mezzo: <?= (int)$data['id_mezzo'] ?>,
  nome_tagliando: <?= json_encode($data['nome_tagliando']) ?>,
  data_scadenza: <?= json_encode($data['data_scadenza']) ?>,
  attivo: <?= (int)$data['attivo'] ?>
};
</script>
<script src="js/mezzo_dettaglio_tagliando.js"></script>
<?php include 'includes/footer.php'; ?>
<?php else: ?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-4">Nuovo Tagliando</h4>
</div>
<form method="post" class="bg-dark text-white p-3 rounded">
  <div class="mb-3">
    <label class="form-label">Nome tagliando</label>
    <input type="text" name="nome_tagliando" class="form-control bg-dark text-white border-secondary" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Data scadenza</label>
    <input type="date" name="data_scadenza" class="form-control bg-dark text-white border-secondary">
  </div>
  <div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" id="attivo" name="attivo" checked>
    <label class="form-check-label" for="attivo">Attivo</label>
  </div>
  <button type="submit" class="btn btn-primary w-100">Salva</button>
</form>
<?php include 'includes/footer.php'; ?>
<?php endif; ?>
