<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';
include 'includes/etichette_utils.php';

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
        $stmtM = $conn->prepare("SELECT COALESCE(NULLIF(descrizione_extra,''), descrizione_operazione) AS descrizione, descrizione_extra, data_operazione, importo AS amount FROM bilancio_entrate WHERE id_entrata = ?");
        $stmtM->bind_param('i', $e2o['id_tabella']);
        break;
    case 'bilancio_uscite':
        $stmtM = $conn->prepare("SELECT COALESCE(NULLIF(descrizione_extra,''), descrizione_operazione) AS descrizione, descrizione_extra, data_operazione, -importo AS amount FROM bilancio_uscite WHERE id_uscita = ?");
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
$stmtU = $conn->prepare("SELECT u2o.*, u.nome, u.cognome, v.id_utente_operazione, v.importo_totale_operazione, v.importo_etichetta
                          FROM bilancio_utenti2operazioni_etichettate u2o
                          JOIN utenti u ON u.id = u2o.id_utente
                          JOIN v_bilancio_etichette2operazioni_a_testa v ON v.id_e2o = u2o.id_e2o
                         WHERE u2o.id_e2o = ?");
$stmtU->bind_param('i', $idE2o);
$stmtU->execute();
$resU = $stmtU->get_result();
$u2oRows = [];
while ($r = $resU->fetch_assoc()) {
    $u2oRows[] = $r;
}
$stmtU->close();

$u2oRows = get_utenti_e_quote_operazione_etichettata($idE2o);

// Lista utenti per la select del modal
$stmtUt = $conn->prepare('SELECT id, nome, cognome FROM utenti WHERE attivo = 1 ORDER BY nome');
$stmtUt->execute();
$resUt = $stmtUt->get_result();
$listaUtenti = $resUt->fetch_all(MYSQLI_ASSOC);
$stmtUt->close();

/*
$count = count($u2oRows) ?: 1;
foreach ($u2oRows as &$r) {
    $quote         = $r['quote'] !== null ? (float)$r['quote'] : (1 / $count);
    $importoUtente = (float)($r['importo_utente'] ?? 0);
    $importoEtic   = (float)($r['importo_etichetta'] ?? 0);
    $importoTot    = (float)($r['importo_totale_operazione'] ?? 0);
    $isPagante     = ((int)$r['id_utente_operazione'] === (int)$r['id_utente']);
    $r['calc_importo'] = calcola_importo_quota($isPagante, $importoUtente, $importoEtic, $importoTot, $quote);
}
unset($r);
*/

$descrizione = $e2o['descrizione_extra'] ?: ($mov['descrizione'] ?? '');
$amountValue = $e2o['importo'] !== null ? (float)$e2o['importo'] : $mov['amount'];
if ($e2o['tabella_operazione'] === 'bilancio_uscite' && $amountValue >= 0) {
    $amountValue *= -1;
}
$importo = number_format($amountValue, 2, ',', '.');
$dataOra = date('d/m/Y H:i', strtotime($mov['data_operazione']));
?>
<div class="container text-white">
    <div class="d-flex">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <a href="dettaglio.php?id=<?= $e2o['id_tabella'] ?>&src=<?= $e2o['tabella_operazione'] ?>" class="btn btn-outline-light ms-auto mb-3">Vai al movimento</a>
  </div>
  <h4 class="mb-3">
    <span id="movDescr"><?= htmlspecialchars($descrizione) ?></span>
    <i class="bi bi-pencil ms-2" role="button" onclick="openE2oModal()"></i>
  </h4>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="small"><?= $dataOra ?></div>
    <div class="fw-semibold"><?= ($amountValue >= 0 ? '+' : '') . $importo ?> €</div>
  </div>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">Quote utenti</h5>
    <button class="btn btn-outline-light" onclick="openU2oModal()">Aggiungi nuovo</button>
  </div>
  <ul class="list-group list-group-flush bg-dark mb-3" id="u2oList">
    <?php foreach ($u2oRows as $row): ?>
      <li class="list-group-item bg-dark text-white d-flex justify-content-between align-items-center" role="button" data-u2o-id="<?= (int)$row['id_u2o'] ?>" data-utente-id="<?= (int)$row['id_utente'] ?>" data-quote="<?= $row['quote'] !== null ? htmlspecialchars($row['quote']) : '' ?>" data-saldata="<?= (int)$row['saldata'] ?>" data-data-saldo="<?= htmlspecialchars($row['data_saldo'] ?? '') ?>" onclick="openU2oModal(this)">
        <div class="flex-grow-1"><?= htmlspecialchars($row['nome'] . ' ' . $row['cognome']) ?></div>
        <div class="text-end me-2" style="min-width:80px;"><?= number_format($row['importo_utente'], 2, ',', '.') ?> €</div>
        <button class="btn btn-danger btn-sm ms-2" onclick="event.stopPropagation(); deleteU2o(<?= (int)$row['id_u2o'] ?>)">✕</button>
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

<div class="modal fade" id="editU2oModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="editU2oForm">
      <div class="modal-header">
        <h5 class="modal-title">Quota utente</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_u2o" value="0">
        <div class="mb-3">
          <label class="form-label">Utente</label>
          <select name="id_utente" class="form-select bg-secondary text-white">
            <option value="">Seleziona utente</option>
            <?php foreach ($listaUtenti as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nome'] . ' ' . $u['cognome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Quota</label>
          <input type="number" step="0.01" name="quote" class="form-control bg-secondary text-white">
        </div>
        <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="u2oSaldata" name="saldata">
          <label class="form-check-label" for="u2oSaldata">Saldata</label>
        </div>
        <div class="mb-3">
          <label class="form-label">Data saldo</label>
          <input type="date" name="data_saldo" class="form-control bg-secondary text-white">
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
