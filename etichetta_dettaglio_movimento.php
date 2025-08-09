<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$idE2o = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idE2o <= 0) {
    echo '<p class="text-danger">Movimento non trovato.</p>';
    include 'includes/footer.php';
    exit;
}

$stmt = $conn->prepare("SELECT e2o.*, e.descrizione AS etichetta_descrizione FROM bilancio_etichette2operazioni e2o JOIN bilancio_etichette e ON e.id_etichetta = e2o.id_etichetta WHERE e2o.id_e2o = ?");
$stmt->bind_param('i', $idE2o);
$stmt->execute();
$e2o = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$e2o) {
    echo '<p class="text-danger">Movimento non trovato.</p>';
    include 'includes/footer.php';
    exit;
}

// Dati del movimento originale
$mov = null;
switch ($e2o['tabella_operazione']) {
    case 'movimenti_revolut':
        $stmtM = $conn->prepare("SELECT COALESCE(NULLIF(descrizione_extra,''), description) AS descrizione, descrizione_extra, started_date AS data_operazione, amount FROM v_movimenti_revolut WHERE id_movimento_revolut = ?");
        $stmtM->bind_param('i', $e2o['id_tabella']);
        break;
    case 'bilancio_entrate':
        $stmtM = $conn->prepare("SELECT descrizione_operazione AS descrizione, descrizione_extra, data_operazione, importo AS amount FROM bilancio_entrate WHERE id_entrata = ?");
        $stmtM->bind_param('i', $e2o['id_tabella']);
        break;
    case 'bilancio_uscite':
        $stmtM = $conn->prepare("SELECT descrizione_operazione AS descrizione, descrizione_extra, data_operazione, -importo AS amount FROM bilancio_uscite WHERE id_uscita = ?");
        $stmtM->bind_param('i', $e2o['id_tabella']);
        break;
    default:
        $stmtM = null;
}
if ($stmtM && $stmtM->execute()) {
    $mov = $stmtM->get_result()->fetch_assoc();
    $stmtM->close();
}
if (!$mov) {
    echo '<p class="text-danger">Movimento non trovato.</p>';
    include 'includes/footer.php';
    exit;
}

// Dati per gli utenti
$stmtU = $conn->prepare("SELECT u2o.*, u.nome, u.cognome FROM bilancio_utenti2operazioni_etichettate u2o JOIN utenti u ON u.id = u2o.id_utente WHERE u2o.id_e2o = ?");
$stmtU->bind_param('i', $idE2o);
$stmtU->execute();
$resU = $stmtU->get_result();
$u2oRows = [];
while ($r = $resU->fetch_assoc()) {
    $u2oRows[] = $r;
}
$stmtU->close();

$total = $e2o['importo'] !== null ? (float)$e2o['importo'] : abs($mov['amount']);
$count = count($u2oRows) ?: 1;
foreach ($u2oRows as &$r) {
    $imp = $r['importo_utente'];
    if ($imp === null) {
        if ($r['quote'] !== null) {
            $imp = $total * $r['quote'];
        } else {
            $imp = $total / $count;
        }
    }
    if ($r['utente_pagante']) {
        $imp = -$imp;
    }
    $r['calc_importo'] = $imp;
}
unset($r);

$descrizione = $e2o['descrizione_extra'] ?: ($mov['descrizione'] ?? '');
$amountValue = $e2o['importo'] !== null ? (float)$e2o['importo'] : $mov['amount'];
if ($e2o['tabella_operazione'] === 'bilancio_uscite' && $amountValue >= 0) {
    $amountValue *= -1;
}
$importo = number_format($amountValue, 2, ',', '.');
$dataOra = date('d/m/Y H:i', strtotime($mov['data_operazione']));
?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-3">
    <span id="movDescr"><?= htmlspecialchars($descrizione) ?></span>
    <i class="bi bi-pencil ms-2" role="button" onclick="openE2oModal()"></i>
  </h4>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="small"><?= $dataOra ?></div>
    <div class="fw-semibold"><?= ($amountValue >= 0 ? '+' : '') . $importo ?> €</div>
  </div>
  <h5 class="mb-2">Quote utenti</h5>
  <ul class="list-group list-group-flush bg-dark mb-3" id="u2oList">
    <?php foreach ($u2oRows as $row): ?>
      <li class="list-group-item bg-dark text-white d-flex justify-content-between align-items-center">
        <div class="flex-grow-1"><?= htmlspecialchars($row['nome'] . ' ' . $row['cognome']) ?></div>
        <div class="text-end me-2" style="min-width:80px;"><?= number_format($row['calc_importo'], 2, ',', '.') ?> €</div>
        <button class="btn btn-danger btn-sm ms-2" onclick="deleteU2o(<?= (int)$row['id_u2o'] ?>)">✕</button>
      </li>
    <?php endforeach; ?>
  </ul>
  <button class="btn btn-danger w-100" onclick="deleteE2o()">Elimina</button>
</div>

<div class="modal fade" id="editE2oModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="editE2oForm" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">Modifica movimento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Descrizione extra</label>
          <input type="text" name="descrizione_extra" class="form-control bg-secondary text-white">
        </div>
        <div class="mb-3">
          <label class="form-label">Importo</label>
          <input type="number" step="0.01" name="importo" class="form-control bg-secondary text-white">
        </div>
        <div class="mb-3">
          <label class="form-label">Allegato</label>
          <input type="file" name="allegato" class="form-control bg-secondary text-white">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>

<script>
const e2oData = {
  id: <?= (int)$e2o['id_e2o'] ?>,
  id_etichetta: <?= (int)$e2o['id_etichetta'] ?>,
  descrizione_extra: <?= json_encode($e2o['descrizione_extra']) ?>,
  importo: <?= $e2o['importo'] !== null ? json_encode((float)$e2o['importo']) : 'null' ?>
};
</script>
<script src="js/etichetta_dettaglio_movimento.js"></script>
<?php include 'includes/footer.php'; ?>
