<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/permissions.php';
require_once 'includes/render_budget.php';
if (!has_permission($conn, 'page:salvadanai.php', 'view')) { http_response_code(403); exit('Accesso negato'); }

function getEnumValues(mysqli $conn, string $table, string $field): array {
    $values = [];
    $result = $conn->query("SHOW COLUMNS FROM {$table} LIKE '{$field}'");
    if ($result && $row = $result->fetch_assoc()) {
        if (preg_match("/^enum\\('(.*)'\\)$/", $row['Type'], $m)) {
            $values = explode("','", $m[1]);
        }
    }
    return $values;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id === 0) {
    $nome = $_POST['nome_salvadanaio'] ?? '';
    $importo = isset($_POST['importo_attuale']) ? (float)$_POST['importo_attuale'] : 0;
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare('INSERT INTO salvadanai (nome_salvadanaio, importo_attuale, data_aggiornamento_manuale) VALUES (?,?,?)');
    $stmt->bind_param('sds', $nome, $importo, $now);
    $stmt->execute();
    $stmt->close();
    header('Location: salvadanai.php');
    exit;
}

$data = [
    'id_salvadanaio' => 0,
    'nome_salvadanaio' => '',
    'importo_attuale' => 0,
];
$finanze = [];
$eventiDisponibili = [];
$etichetteDisponibili = [];
$budgets = [];
$tipologie = [];
$tipologieSpesa = [];

if ($id > 0) {
    $stmt = $conn->prepare('SELECT * FROM salvadanai WHERE id_salvadanaio = ?');
    $stmt->bind_param('i', $id);
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

    $stmtFin = $conn->prepare('SELECT e2se.id_e2se, e2se.id_evento, e2se.id_etichetta, e.titolo, b.descrizione AS etichetta FROM eventi_eventi2salvadanai_etichette e2se LEFT JOIN eventi e ON e2se.id_evento = e.id LEFT JOIN bilancio_etichette b ON e2se.id_etichetta = b.id_etichetta WHERE e2se.id_salvadanaio = ? ORDER BY e.titolo, b.descrizione');
    $stmtFin->bind_param('i', $id);
    $stmtFin->execute();
    $resFin = $stmtFin->get_result();
    while ($row = $resFin->fetch_assoc()) { $finanze[] = $row; }
    $stmtFin->close();

    $resEv = $conn->query('SELECT id, titolo FROM eventi ORDER BY titolo');
    $eventiDisponibili = $resEv ? $resEv->fetch_all(MYSQLI_ASSOC) : [];

    $resEt = $conn->query('SELECT id_etichetta, descrizione FROM bilancio_etichette ORDER BY descrizione');
    $etichetteDisponibili = $resEt ? $resEt->fetch_all(MYSQLI_ASSOC) : [];

    $stmtBud = $conn->prepare('SELECT * FROM budget WHERE id_salvadanaio = ? ORDER BY data_inizio');
    $stmtBud->bind_param('i', $id);
    $stmtBud->execute();
    $resBud = $stmtBud->get_result();
    while ($rowBud = $resBud->fetch_assoc()) { $budgets[] = $rowBud; }
    $stmtBud->close();

    $tipologie = getEnumValues($conn, 'budget', 'tipologia');
    $tipologieSpesa = getEnumValues($conn, 'budget', 'tipologia_spesa');
}

include 'includes/header.php';
if ($id > 0): ?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-3">
    <span id="salvadanaioNome"><?= htmlspecialchars($data['nome_salvadanaio']) ?></span>
    <i class="bi bi-pencil ms-2" role="button" id="editSalvadanaioBtn"></i>
  </h4>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">Finanze</h5>
    <button type="button" class="btn btn-outline-light btn-sm" id="addSeBtn">Aggiungi</button>
  </div>
  <ul class="list-group list-group-flush bg-dark" id="finanzeList">
    <?php foreach ($finanze as $row): ?>
      <li class="list-group-item bg-dark text-white se-row"
          data-id="<?= (int)$row['id_e2se'] ?>"
          <?= $row['id_evento'] ? 'data-id-evento="' . (int)$row['id_evento'] . '"' : '' ?>
          <?= $row['id_etichetta'] ? 'data-id-etichetta="' . (int)$row['id_etichetta'] . '"' : '' ?>>
        <?php
          $parts = [];
          if (!empty($row['titolo'])) { $parts[] = htmlspecialchars($row['titolo']); }
          if (!empty($row['etichetta'])) { $parts[] = htmlspecialchars($row['etichetta']); }
          echo implode(' - ', $parts);
        ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
    <h5 class="mb-0">Budget</h5>
    <button type="button" class="btn btn-outline-light btn-sm" id="addBudgetBtn">Aggiungi</button>
  </div>
  <div id="budgetList" class="list-group">
    <?php foreach ($budgets as $row): ?>
      <?php render_budget($row); ?>
    <?php endforeach; ?>
  </div>
</div>

<div class="modal fade" id="salvadanaioModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="salvadanaioForm">
      <div class="modal-header">
        <h5 class="modal-title">Modifica Salvadanaio</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_salvadanaio" value="<?= (int)$data['id_salvadanaio'] ?>">
        <div class="mb-3">
          <label class="form-label">Nome salvadanaio</label>
          <input type="text" name="nome_salvadanaio" class="form-control bg-secondary text-white" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Importo attuale</label>
          <input type="number" step="0.01" name="importo_attuale" class="form-control bg-secondary text-white">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="seModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="seForm">
      <div class="modal-header">
        <h5 class="modal-title">Finanza</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_e2se" id="id_e2se">
        <input type="hidden" name="id_salvadanaio" value="<?= (int)$id ?>">
        <div class="mb-3 select-search">
          <label class="form-label">Evento</label>
          <input type="text" class="form-control bg-secondary text-white mb-2" placeholder="Cerca">
          <select name="id_evento" id="seEvento" class="form-select bg-secondary text-white">
            <option value=""></option>
            <?php foreach ($eventiDisponibili as $ev): ?>
              <option value="<?= (int)$ev['id'] ?>"><?= htmlspecialchars($ev['titolo']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3 select-search">
          <label class="form-label">Etichetta</label>
          <input type="text" class="form-control bg-secondary text-white mb-2" placeholder="Cerca">
          <select name="id_etichetta" id="seEtichetta" class="form-select bg-secondary text-white">
            <option value=""></option>
            <?php foreach ($etichetteDisponibili as $et): ?>
              <option value="<?= (int)$et['id_etichetta'] ?>"><?= htmlspecialchars($et['descrizione']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger me-auto" id="deleteSeBtn">Elimina</button>
        <button type="submit" class="btn btn-primary">Salva</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="addSeModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="addSeForm">
      <div class="modal-header">
        <h5 class="modal-title">Aggiungi finanza</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_salvadanaio" value="<?= (int)$id ?>">
        <div class="mb-3 select-search">
          <label class="form-label">Evento</label>
          <input type="text" class="form-control bg-secondary text-white mb-2" placeholder="Cerca">
          <select name="id_evento" class="form-select bg-secondary text-white">
            <option value=""></option>
            <?php foreach ($eventiDisponibili as $ev): ?>
              <option value="<?= (int)$ev['id'] ?>"><?= htmlspecialchars($ev['titolo']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3 select-search">
          <label class="form-label">Etichetta</label>
          <input type="text" class="form-control bg-secondary text-white mb-2" placeholder="Cerca">
          <select name="id_etichetta" class="form-select bg-secondary text-white">
            <option value=""></option>
            <?php foreach ($etichetteDisponibili as $et): ?>
              <option value="<?= (int)$et['id_etichetta'] ?>"><?= htmlspecialchars($et['descrizione']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Aggiungi</button>
      </div>
    </form>
</div>
</div>

<div class="modal fade" id="budgetModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="budgetForm">
      <div class="modal-header">
        <h5 class="modal-title">Nuovo budget</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="budgetId">
        <input type="hidden" name="id_salvadanaio" id="budgetSalvadanaio" value="<?= (int)$id ?>">
        <div class="mb-3">
          <label class="form-label">Descrizione</label>
          <input type="text" name="descrizione" id="budgetDescrizione" class="form-control bg-secondary text-white" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Tipologia</label>
          <select name="tipologia" id="budgetTipologia" class="form-select bg-secondary text-white">
            <option value=""></option>
            <?php foreach ($tipologie as $t): ?>
              <option value="<?= htmlspecialchars($t) ?>"><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Tipologia spesa</label>
          <select name="tipologia_spesa" id="budgetTipologiaSpesa" class="form-select bg-secondary text-white">
            <option value=""></option>
            <?php foreach ($tipologieSpesa as $ts): ?>
              <option value="<?= htmlspecialchars($ts) ?>"><?= ucfirst(str_replace('_',' ', $ts)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Importo</label>
          <input type="number" step="0.01" name="importo" id="budgetImporto" class="form-control bg-secondary text-white" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Data inizio</label>
          <input type="date" name="data_inizio" id="budgetDataInizio" class="form-control bg-secondary text-white">
        </div>
        <div class="mb-3">
          <label class="form-label">Data fine</label>
          <input type="date" name="data_scadenza" id="budgetDataFine" class="form-control bg-secondary text-white">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger me-auto" id="deleteBudget">Elimina</button>
        <button type="submit" class="btn btn-primary">Salva</button>
      </div>
    </form>
  </div>
</div>

<script>
const salvadanaioData = {
  id: <?= (int)$data['id_salvadanaio'] ?>,
  nome_salvadanaio: <?= json_encode($data['nome_salvadanaio']) ?>,
  importo_attuale: <?= json_encode(number_format((float)$data['importo_attuale'], 2, '.', '')) ?>
};
</script>
<script src="js/salvadanaio_dettaglio.js"></script>
<?php include 'includes/footer.php'; ?>
<?php else: ?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-4">Nuovo Salvadanaio</h4>
</div>
<form method="post" class="bg-dark text-white p-3 rounded">
  <div class="mb-3">
    <label class="form-label">Nome salvadanaio</label>
    <input type="text" name="nome_salvadanaio" class="form-control bg-dark text-white border-secondary" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Importo attuale</label>
    <input type="number" step="0.01" name="importo_attuale" class="form-control bg-dark text-white border-secondary">
  </div>
  <button type="submit" class="btn btn-primary w-100">Salva</button>
</form>
<?php include 'includes/footer.php'; ?>
<?php endif; ?>
