<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/permissions.php';

$idUtente = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id === 0) {
    $nome_mezzo = $_POST['nome_mezzo'] ?? '';
    $data_immatricolazione = $_POST['data_immatricolazione'] ?? '';
    $attivo = isset($_POST['attivo']) ? 1 : 0;
    $stmt = $conn->prepare("INSERT INTO mezzi (id_utente, id_famiglia, nome_mezzo, data_immatricolazione, attivo) VALUES (?,?,?,?,?)");
    $stmt->bind_param('iissi', $idUtente, $idFamiglia, $nome_mezzo, $data_immatricolazione, $attivo);
    $stmt->execute();
    $stmt->close();
    header('Location: mezzi.php');
    exit;
}
$data = [
    'nome_mezzo' => '',
    'data_immatricolazione' => '',
    'attivo' => 1,
    'id_mezzo' => 0,
    'id_utente' => $idUtente
];
$chilometri = [];
$eventi = [];
$tipiEventi = [];
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM mezzi WHERE id_mezzo = ? AND id_famiglia = ?");
    $stmt->bind_param('ii', $id, $idFamiglia);
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

    $stmtKm = $conn->prepare("SELECT id_chilometro, DATE(data_chilometro) AS data_chilometro, chilometri FROM mezzi_chilometri WHERE id_mezzo = ? ORDER BY data_chilometro DESC");
    $stmtKm->bind_param('i', $id);
    $stmtKm->execute();
    $resKm = $stmtKm->get_result();
    while ($row = $resKm->fetch_assoc()) {
        $chilometri[] = $row;
    }
    $stmtKm->close();

    $stmtEv = $conn->prepare("SELECT me.id_evento, me.id_tipo_evento, me.data_evento, me.km_evento, me.note, mt.tipo_evento FROM mezzi_eventi me JOIN mezzi_eventi_tipi mt ON mt.id_tipo_evento = me.id_tipo_evento WHERE me.id_mezzo = ? ORDER BY me.data_evento DESC");
    $stmtEv->bind_param('i', $id);
    $stmtEv->execute();
    $resEv = $stmtEv->get_result();
    while ($row = $resEv->fetch_assoc()) {
        $eventi[] = $row;
    }
    $stmtEv->close();

    $stmtTipi = $conn->prepare("SELECT id_tipo_evento, tipo_evento FROM mezzi_eventi_tipi WHERE attivo = 1 ORDER BY tipo_evento");
    $stmtTipi->execute();
    $resTipi = $stmtTipi->get_result();
    while ($row = $resTipi->fetch_assoc()) {
        $tipiEventi[] = $row;
    }
    $stmtTipi->close();
}

$isOwner = ($data['id_utente'] ?? $idUtente) == $idUtente;

include 'includes/header.php';

if ($id > 0): ?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-3">
    <span id="mezzoNome"><?= htmlspecialchars($data['nome_mezzo']) ?></span>
    <?php if ($isOwner): ?>
      <i class="bi bi-pencil ms-2" role="button" onclick="openMezzoModal()"></i>
    <?php endif; ?>
  </h4>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="d-flex align-items-center">
      <h5 class="mb-0 me-3">Chilometri</h5>
      <?php if (count($chilometri) > 3): ?>
        <button id="toggleChilometri" class="btn btn-link p-0">Mostra tutti</button>
      <?php endif; ?>
    </div>
    <?php if ($isOwner): ?>
      <button class="btn btn-outline-light btn-sm" onclick="openChilometroModal()">Aggiungi</button>
    <?php endif; ?>
  </div>
  <ul class="list-group list-group-flush bg-dark" id="chilometriList">
    <?php foreach ($chilometri as $idx => $row): ?>
      <li class="list-group-item movement text-white d-flex justify-content-between align-items-center <?= $idx >= 3 ? 'd-none extra-row' : '' ?>"
            data-id="<?= (int)$row['id_chilometro'] ?>"
            data-data="<?= htmlspecialchars($row['data_chilometro']) ?>"
            data-km="<?= (int)$row['chilometri'] ?>">
          <div class="flex-grow-1"><?= date("d/m/Y", strtotime($row['data_chilometro'])) ?></div>
          <div class="text-end me-2" style="min-width: 90px;"><?= number_format((int)$row['chilometri'], 0, ',', '.') ?> km</div>
          <?php if ($isOwner): ?>
            <button class="btn btn-danger btn-sm ms-2" onclick="deleteChilometro(event, <?= (int)$row['id_chilometro'] ?>)">✕</button>
          <?php endif; ?>
        </li>
    <?php endforeach; ?>
  </ul>

  <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
    <h5 class="mb-0">Eventi</h5>
    <?php if ($isOwner): ?>
      <button class="btn btn-outline-light btn-sm" onclick="openEventoModal()">Aggiungi</button>
    <?php endif; ?>
  </div>
  <div id="eventiList">
    <?php foreach ($eventi as $row): ?>
      <div class="mezzo-card movement d-flex justify-content-between align-items-start text-white"
           data-id="<?= (int)$row['id_evento'] ?>"
           data-tipo="<?= (int)$row['id_tipo_evento'] ?>"
           data-data="<?= htmlspecialchars($row['data_evento']) ?>"
           data-km="<?= (int)$row['km_evento'] ?>"
           data-note="<?= htmlspecialchars($row['note'], ENT_QUOTES) ?>">
        <div class="flex-grow-1">
          <div class="fw-semibold"><?= htmlspecialchars($row['tipo_evento']) ?></div>
          <?php if (!empty($row['note'])): ?>
            <div class="small"><?= htmlspecialchars($row['note']) ?></div>
          <?php endif; ?>
        </div>
        <div class="text-end me-2" style="min-width:90px;">
          <div class="small"><?= date('d/m/Y', strtotime($row['data_evento'])) ?></div>
          <?php if (!empty($row['km_evento'])): ?>
            <div class="small"><?= number_format((int)$row['km_evento'], 0, ',', '.') ?> km</div>
          <?php endif; ?>
        </div>
        <?php if ($isOwner): ?>
          <button class="btn btn-danger btn-sm ms-2" onclick="deleteEvento(event, <?= (int)$row['id_evento'] ?>)">✕</button>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Modal modifica mezzo -->
<div class="modal fade" id="editMezzoModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="mezzoForm">
      <div class="modal-header">
        <h5 class="modal-title">Modifica mezzo</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nome mezzo</label>
          <input type="text" name="nome_mezzo" class="form-control bg-secondary text-white" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Data immatricolazione</label>
          <input type="date" name="data_immatricolazione" class="form-control bg-secondary text-white">
        </div>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" name="attivo" id="mezzoAttivo">
          <label class="form-check-label" for="mezzoAttivo">Attivo</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal chilometro -->
<div class="modal fade" id="chilometroModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="chilometroForm">
      <div class="modal-header">
        <h5 class="modal-title">Chilometro</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_chilometro" id="id_chilometro">
        <div class="mb-3">
          <label class="form-label">Data</label>
          <input type="date" name="data_chilometro" class="form-control bg-secondary text-white" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Chilometri</label>
          <input type="number" name="chilometri" class="form-control bg-secondary text-white" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal evento -->
<div class="modal fade" id="eventoModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="eventoForm">
      <div class="modal-header">
        <h5 class="modal-title">Nuovo evento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_evento" id="id_evento">
        <div class="mb-3">
          <label class="form-label">Tipo evento</label>
          <select name="id_tipo_evento" class="form-select bg-secondary text-white">
            <?php foreach ($tipiEventi as $t): ?>
              <option value="<?= (int)$t['id_tipo_evento'] ?>"><?= htmlspecialchars($t['tipo_evento']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Data</label>
          <input type="date" name="data_evento" class="form-control bg-secondary text-white" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Chilometri</label>
          <input type="number" name="km_evento" class="form-control bg-secondary text-white">
        </div>
        <div class="mb-3">
          <label class="form-label">Note</label>
          <input type="text" name="note" class="form-control bg-secondary text-white">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>

<script>
const mezzoData = {
  id: <?= (int)$data['id_mezzo'] ?>,
  nome_mezzo: <?= json_encode($data['nome_mezzo']) ?>,
  data_immatricolazione: <?= json_encode($data['data_immatricolazione']) ?>,
  attivo: <?= (int)$data['attivo'] ?>
};
</script>
<script src="js/mezzo_dettaglio.js"></script>
<?php include 'includes/footer.php'; ?>
<?php else: ?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-4">Nuovo Mezzo</h4>
</div>
<form method="post" class="bg-dark text-white p-3 rounded">
  <div class="mb-3">
    <label class="form-label">Nome mezzo</label>
    <input type="text" name="nome_mezzo" class="form-control bg-dark text-white border-secondary" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Data immatricolazione</label>
    <input type="date" name="data_immatricolazione" class="form-control bg-dark text-white border-secondary">
  </div>
  <div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" id="attivo" name="attivo" checked>
    <label class="form-check-label" for="attivo">Attivo</label>
  </div>
  <button type="submit" class="btn btn-primary w-100">Salva</button>
</form>
<?php include 'includes/footer.php'; ?>
<?php endif; ?>
