<?php include 'includes/session_check.php'; ?>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/db.php';
require_once 'includes/utility.php';
include 'includes/header.php';

date_default_timezone_set('Europe/Rome');

$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

// Filtri
$anno = $_GET['anno'] ?? '';
$mese = $_GET['mese'] ?? '';
$tipologiaSpesa = $_GET['tipologia_spesa'] ?? '';
$idSalvadanaio = $_GET['id_salvadanaio'] ?? '';
$defaultScadenzaDa = (new DateTime('first day of last month', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
$scadenzaDa = array_key_exists('scadenza_da', $_GET) ? $_GET['scadenza_da'] : $defaultScadenzaDa;
$scadenzaA = $_GET['scadenza_a'] ?? '';
$search = trim($_GET['q'] ?? '');
$export = isset($_GET['export']);

$conditions = [
    'b.id_famiglia = ?',
    "b.tipologia_spesa <> 'mensile'",
];
$params = [$idFamiglia];
$types  = 'i';

if ($anno !== '') {
    $conditions[] = 'YEAR(b.data_scadenza) = ?';
    $params[] = $anno;
    $types .= 'i';
}
if ($mese !== '') {
    $conditions[] = 'MONTH(b.data_scadenza) = ?';
    $params[] = $mese;
    $types .= 'i';
}
if ($tipologiaSpesa !== '') {
    $conditions[] = 'b.tipologia_spesa = ?';
    $params[] = $tipologiaSpesa;
    $types .= 's';
}
if ($idSalvadanaio !== '') {
    $conditions[] = 'b.id_salvadanaio = ?';
    $params[] = $idSalvadanaio;
    $types .= 'i';
}
if ($scadenzaDa !== '') {
    $conditions[] = 'b.data_scadenza >= ?';
    $params[] = $scadenzaDa;
    $types .= 's';
}
if ($scadenzaA !== '') {
    $conditions[] = 'b.data_scadenza <= ?';
    $params[] = $scadenzaA;
    $types .= 's';
}
if ($search !== '') {
    $conditions[] = '(b.descrizione LIKE ? OR s.nome_salvadanaio LIKE ?)';
    $like = '%'.$search.'%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
$where = implode(' AND ', $conditions);

$sql = "SELECT b.id_budget, b.id_salvadanaio, b.tipologia, b.importo, b.descrizione, b.data_inizio, b.data_scadenza, b.tipologia_spesa, b.da_13esima, b.da_14esima, s.nome_salvadanaio
        FROM budget b
        LEFT JOIN salvadanai s ON b.id_salvadanaio = s.id_salvadanaio
        WHERE $where
        ORDER BY b.data_scadenza";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$today = new DateTime('now', new DateTimeZone('Europe/Rome'));
$rows = [];
$totImporto = $totStimato = $totMensile = 0;
while ($row = $res->fetch_assoc()) {
    $importo = (float)($row['importo'] ?? 0);
    $da13 = (float)($row['da_13esima'] ?? 0);
    $da14 = (float)($row['da_14esima'] ?? 0);
    $residuo = $importo - ($da13 + $da14);
    
    $dataInizio = $row['data_inizio'] ?: null;
    $dataScadenza = $row['data_scadenza'] ?: null;

$j = $dataScadenza ? diff_mesi($today->format('Y-m-d'), $dataScadenza) : null; // mesi a scadenza
$k = $dataInizio ? max(0, diff_mesi($dataInizio, $today->format('Y-m-d'))) : 0; // mesi da inizio

    if(strtotime($dataInizio)<time() && strtotime($dataScadenza)>time())
    {
        $importoMensile = round($residuo / 12, 2);
    }else{
        $importoMensile = 0;
    }
    
    if ($dataScadenza) {
        if ($j < 0) {
            $importoStimato = 0.00;
        } elseif ($j == 0) {
            $importoStimato = $importo;
        } else {
            $importoStimato = round($importoMensile * $k, 2);
        }
    } else {
        $importoStimato = '';
    }

    $rows[] = [
        'id_budget' => (int)($row['id_budget'] ?? 0),
        'id_salvadanaio' => $row['id_salvadanaio'] ?? null,
        'tipologia' => $row['tipologia'] ?? '',
        'importo' => $importo,
        'salvadanaio' => $row['nome_salvadanaio'] ?: ($row['id_salvadanaio'] ?? ''),
        'descrizione' => $row['descrizione'] ?? '',
        'data_inizio' => $dataInizio,
        'data_inizio_fmt' => $dataInizio ? date('d/m/Y', strtotime($dataInizio)) : '',
        'data_scadenza' => $dataScadenza,
        'data_scadenza_fmt' => $dataScadenza ? date('d/m/Y', strtotime($dataScadenza)) : '',
        'tipologia_spesa' => $row['tipologia_spesa'] ?? '',
        'da_13esima' => $da13,
        'da_14esima' => $da14,
        'importo_stimato' => $importoStimato,
        'importo_mensile' => $importoMensile,
    ];

    $totImporto += $importo;
    $totStimato += ($importoStimato === '' ? 0 : $importoStimato);
    $totMensile += $importoMensile;
}
$stmt->close();

// Export CSV
if ($export) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="budget_anno.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Tipologia','Tipologia spesa','Salvadanaio','Descrizione','Inizio','Scadenza','Da 13esima','Da 14esima','Importo','Importo stimato attuale','Importo mensile']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['tipologia'],
            $r['tipologia_spesa'] === 'fissa' ? 'Fissa' : ($r['tipologia_spesa'] === 'una_tantum' ? 'Una Tantum' : ''),
            $r['salvadanaio'],
            $r['descrizione'],
            $r['data_inizio_fmt'],
            $r['data_scadenza_fmt'],
            number_format($r['da_13esima'],2,'.',''),
            number_format($r['da_14esima'],2,'.',''),
            number_format($r['importo'],2,'.',''),
            $r['importo_stimato'] === '' ? '' : number_format($r['importo_stimato'],2,'.',''),
            number_format($r['importo_mensile'],2,'.',''),
        ]);
    }
    fclose($out);
    exit;
}

// Anni per filtro
$yearStmt = $conn->prepare('SELECT DISTINCT YEAR(data_scadenza) AS anno FROM budget WHERE data_scadenza IS NOT NULL ORDER BY anno');
$yearStmt->execute();
$years = $yearStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$yearStmt->close();

$salStmt = $conn->prepare('SELECT DISTINCT s.id_salvadanaio, s.nome_salvadanaio
                            FROM salvadanai s
                            ORDER BY s.nome_salvadanaio');
$salStmt->execute();
$salvadanai = $salStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$salStmt->close();
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Budget per anno</h4>
</div>
<form method="get" class="row g-2 mb-3">
  <div class="col-6 col-md-2">
    <select name="anno" class="form-select bg-dark text-white border-secondary">
      <option value="">Anno</option>
      <?php foreach ($years as $y): ?>
      <option value="<?= (int)$y['anno'] ?>" <?= ($anno !== '' && (int)$anno === (int)$y['anno']) ? 'selected' : '' ?>><?= (int)$y['anno'] ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-2">
    <select name="mese" class="form-select bg-dark text-white border-secondary">
      <option value="">Mese</option>
      <?php for ($m=1;$m<=12;$m++): ?>
      <option value="<?= $m ?>" <?= ($mese !== '' && (int)$mese === $m) ? 'selected' : '' ?>><?= $m ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="col-6 col-md-2">
    <select name="tipologia_spesa" class="form-select bg-dark text-white border-secondary">
      <option value="">Tipologia spesa</option>
      <option value="fissa" <?= $tipologiaSpesa === 'fissa' ? 'selected' : '' ?>>Fissa</option>
      <option value="una_tantum" <?= $tipologiaSpesa === 'una_tantum' ? 'selected' : '' ?>>Una Tantum</option>
    </select>
  </div>
  <div class="col-6 col-md-2">
    <select name="id_salvadanaio" class="form-select bg-dark text-white border-secondary">
      <option value="">Salvadanaio</option>
      <?php foreach ($salvadanai as $s): ?>
      <option value="<?= (int)$s['id_salvadanaio'] ?>" <?= ($idSalvadanaio !== '' && (int)$idSalvadanaio === (int)$s['id_salvadanaio']) ? 'selected' : '' ?>><?= htmlspecialchars($s['nome_salvadanaio']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-2">
    <input type="date" name="scadenza_da" value="<?= htmlspecialchars($scadenzaDa) ?>" class="form-control bg-dark text-white border-secondary" placeholder="Scadenza da" />
  </div>
  <div class="col-6 col-md-2">
    <input type="date" name="scadenza_a" value="<?= htmlspecialchars($scadenzaA) ?>" class="form-control bg-dark text-white border-secondary" placeholder="Scadenza a" />
  </div>
  <div class="col-6 col-md-2">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control bg-dark text-white border-secondary" placeholder="Cerca" />
  </div>
  <div class="col-6 col-md-1">
    <button type="submit" class="btn btn-outline-light w-100">Filtra</button>
  </div>
  <div class="col-6 col-md-1">
    <a href="budget_anno.php" class="btn btn-outline-light w-100">Reset filtri</a>
  </div>
  <div class="col-6 col-md-2">
    <a class="btn btn-outline-light w-100" href="?<?= http_build_query(array_merge($_GET, ['export' => 1])) ?>">Export CSV</a>
  </div>
  <div class="col-6 col-md-2">
    <a class="btn btn-outline-light w-100" href="budget_dashboard.php">Dashboard</a>
  </div>
</form>
<div class="table-responsive" style="width:90vw;margin-left:calc(-45vw + 50%);">
<table class="table table-dark table-striped table-sm w-100" id="budgetTable">
  <thead>
    <tr>
      <th></th>
      <th></th>
      <th>Salvadanaio</th>
      <th>Descrizione</th>
      <th>Inizio</th>
      <th>Scadenza</th>
      <th class="text-end">Da 13esima</th>
      <th class="text-end">Da 14esima</th>
      <th class="text-end">Importo</th>
      <th class="text-end">Importo stimato attuale</th>
      <th class="text-end">Importo mensile</th>
      <th class="text-center">Azioni</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
    <tr data-id="<?= (int)$r['id_budget'] ?>" data-salvadanaio="<?= htmlspecialchars((string)$r['id_salvadanaio']) ?>" data-descrizione="<?= htmlspecialchars($r['descrizione'], ENT_QUOTES) ?>" data-inizio="<?= htmlspecialchars($r['data_inizio']) ?>" data-scadenza="<?= htmlspecialchars($r['data_scadenza']) ?>" data-da13="<?= number_format($r['da_13esima'],2,'.','') ?>" data-da14="<?= number_format($r['da_14esima'],2,'.','') ?>" data-importo="<?= number_format($r['importo'],2,'.','') ?>" data-tipologia="<?= htmlspecialchars($r['tipologia']) ?>" data-tipologia-spesa="<?= htmlspecialchars($r['tipologia_spesa']) ?>">
      <td class="text-center">
        <?php if (strtolower($r['tipologia']) === 'entrata'): ?>
          <i class="bi bi-arrow-down-circle text-success"></i>
        <?php elseif (strtolower($r['tipologia']) === 'uscita'): ?>
          <i class="bi bi-arrow-up-circle text-danger"></i>
        <?php endif; ?>
      </td>
      <td class="text-center">
        <?php if ($r['tipologia_spesa'] === 'fissa'): ?>
          <i class="bi bi-arrow-repeat"></i>
        <?php elseif ($r['tipologia_spesa'] === 'una_tantum'): ?>
          <i class="bi bi-1-circle"></i>
        <?php endif; ?>
      </td>
      <td><?= htmlspecialchars($r['salvadanaio']) ?></td>
      <td><?= htmlspecialchars($r['descrizione']) ?></td>
      <td data-sort="<?= htmlspecialchars($r['data_inizio']) ?>"><?= htmlspecialchars($r['data_inizio_fmt']) ?></td>
      <td data-sort="<?= htmlspecialchars($r['data_scadenza']) ?>"><?= htmlspecialchars($r['data_scadenza_fmt']) ?></td>
      <td class="text-end" data-sort="<?= number_format($r['da_13esima'],2,'.','') ?>"><?= number_format($r['da_13esima'],2,',','.') ?></td>
      <td class="text-end" data-sort="<?= number_format($r['da_14esima'],2,'.','') ?>"><?= number_format($r['da_14esima'],2,',','.') ?></td>
      <td class="text-end" data-sort="<?= number_format($r['importo'],2,'.','') ?>"><?= number_format($r['importo'],2,',','.') ?></td>
      <td class="text-end" data-sort="<?= $r['importo_stimato'] === '' ? '' : number_format($r['importo_stimato'],2,'.','') ?>">
        <?= $r['importo_stimato'] === '' ? '' : number_format($r['importo_stimato'],2,',','.') ?>
      </td>
      <td class="text-end" data-sort="<?= number_format($r['importo_mensile'],2,'.','') ?>"><?= number_format($r['importo_mensile'],2,',','.') ?></td>
      <td class="text-center">
        <i class="bi bi-pencil-square text-warning edit-btn" role="button"></i>
        <i class="bi bi-files text-info duplicate-btn ms-2" role="button"></i>
        <i class="bi bi-trash text-danger delete-btn ms-2" role="button"></i>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot class="table-dark" style="position: sticky; bottom: 0;">
    <tr>
      <th colspan="8">Totali</th>
      <th class="text-end"><?= number_format($totImporto,2,',','.') ?></th>
      <th class="text-end"><?= number_format($totStimato,2,',','.') ?></th>
      <th class="text-end"><?= number_format($totMensile,2,',','.') ?></th>
  <th></th>
  </tr>
  </tfoot>
</table>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="editBudgetModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="editBudgetForm">
      <div class="modal-header">
        <h5 class="modal-title">Modifica budget</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="editBudgetId">
        <input type="hidden" name="tipologia" id="editTipologia">
        <input type="hidden" name="tipologia_spesa" id="editTipologiaSpesa">
        <div class="mb-3">
          <label class="form-label">Salvadanaio</label>
          <select name="id_salvadanaio" id="editSalvadanaio" class="form-select bg-secondary text-white">
            <option value=""></option>
            <?php foreach ($salvadanai as $s): ?>
              <option value="<?= (int)$s['id_salvadanaio'] ?>"><?= htmlspecialchars($s['nome_salvadanaio']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Descrizione</label>
          <input type="text" name="descrizione" id="editDescrizione" class="form-control bg-secondary text-white" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Inizio</label>
          <input type="date" name="data_inizio" id="editDataInizio" class="form-control bg-secondary text-white">
        </div>
        <div class="mb-3">
          <label class="form-label">Scadenza</label>
          <input type="date" name="data_scadenza" id="editDataScadenza" class="form-control bg-secondary text-white">
        </div>
        <div class="mb-3">
          <label class="form-label">Da 13esima</label>
          <input type="number" step="0.01" name="da_13esima" id="editDa13" class="form-control bg-secondary text-white">
        </div>
        <div class="mb-3">
          <label class="form-label">Da 14esima</label>
          <input type="number" step="0.01" name="da_14esima" id="editDa14" class="form-control bg-secondary text-white">
        </div>
        <div class="mb-3">
          <label class="form-label">Importo</label>
          <input type="number" step="0.01" name="importo" id="editImporto" class="form-control bg-secondary text-white" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Salva</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Delete -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title">Conferma eliminazione</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">Sei sicuro di voler eliminare questo budget?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Elimina</button>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function(){
  // Ordinamento cliccando sull'intestazione
  document.querySelectorAll('#budgetTable th').forEach(function(th, idx){
    th.addEventListener('click', function(){
      const table = th.closest('table');
      const tbody = table.querySelector('tbody');
      const rows = Array.from(tbody.querySelectorAll('tr'));
      const asc = !th.classList.contains('asc');
      document.querySelectorAll('#budgetTable th').forEach(th2=>th2.classList.remove('asc','desc'));
      th.classList.add(asc ? 'asc' : 'desc');
      rows.sort(function(a,b){
        const aVal = a.children[idx].dataset.sort || a.children[idx].innerText;
        const bVal = b.children[idx].dataset.sort || b.children[idx].innerText;
        const aNum = parseFloat(aVal.replace(',', '.'));
        const bNum = parseFloat(bVal.replace(',', '.'));
        if (!isNaN(aNum) && !isNaN(bNum)) {
          return asc ? aNum - bNum : bNum - aNum;
        }
        return asc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
      });
      rows.forEach(r => tbody.appendChild(r));
    });
  });

  const editModalEl = document.getElementById('editBudgetModal');
  const editForm = document.getElementById('editBudgetForm');
  const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
  const fields = {
    id: document.getElementById('editBudgetId'),
    tipologia: document.getElementById('editTipologia'),
    tipologiaSpesa: document.getElementById('editTipologiaSpesa'),
    salvadanaio: document.getElementById('editSalvadanaio'),
    descrizione: document.getElementById('editDescrizione'),
    inizio: document.getElementById('editDataInizio'),
    scadenza: document.getElementById('editDataScadenza'),
    da13: document.getElementById('editDa13'),
    da14: document.getElementById('editDa14'),
    importo: document.getElementById('editImporto')
  };

  const deleteModalEl = document.getElementById('deleteModal');
  const deleteModal = deleteModalEl ? new bootstrap.Modal(deleteModalEl) : null;
  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
  let deleteId = null;

  document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const tr = btn.closest('tr');
      fields.id.value = tr.dataset.id;
      fields.tipologia.value = tr.dataset.tipologia;
      fields.tipologiaSpesa.value = tr.dataset.tipologiaSpesa;
      fields.salvadanaio.value = tr.dataset.salvadanaio;
      fields.descrizione.value = tr.dataset.descrizione;
      fields.inizio.value = tr.dataset.inizio;
      fields.scadenza.value = tr.dataset.scadenza;
      fields.da13.value = tr.dataset.da13;
      fields.da14.value = tr.dataset.da14;
      fields.importo.value = tr.dataset.importo;
      editModal?.show();
    });
  });

  document.querySelectorAll('.duplicate-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const tr = btn.closest('tr');
      fields.id.value = '';
      fields.tipologia.value = tr.dataset.tipologia;
      fields.tipologiaSpesa.value = tr.dataset.tipologiaSpesa;
      fields.salvadanaio.value = tr.dataset.salvadanaio;
      fields.descrizione.value = tr.dataset.descrizione;
      fields.inizio.value = tr.dataset.inizio;
      fields.scadenza.value = tr.dataset.scadenza;
      fields.da13.value = tr.dataset.da13;
      fields.da14.value = tr.dataset.da14;
      fields.importo.value = tr.dataset.importo;
      if(editModalEl.querySelector('.modal-title')){
        editModalEl.querySelector('.modal-title').textContent = 'Duplica budget';
      }
      editModal?.show();
    });
  });

  editModalEl?.addEventListener('hidden.bs.modal', () => {
    fields.id.value = '';
    if(editModalEl.querySelector('.modal-title')){
      editModalEl.querySelector('.modal-title').textContent = 'Modifica budget';
    }
  });

  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const tr = btn.closest('tr');
      deleteId = tr.dataset.id;
      deleteModal?.show();
    });
  });

  confirmDeleteBtn?.addEventListener('click', () => {
    if(!deleteId) return;
    const fd = new FormData();
    fd.append('action','delete');
    fd.append('id', deleteId);
    fetch('ajax/budget_manage.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res => {
        deleteModal?.hide();
        if(res.success){ location.reload(); } else { alert(res.error || 'Errore'); }
      });
  });

  editForm?.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(editForm);
    fd.append('action','save');
    fetch('ajax/budget_manage.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res => {
        if(res.success){ editModal?.hide(); location.reload(); }
        else { alert(res.error || 'Errore'); }
      });
  });
});
</script>
<?php include 'includes/footer.php'; ?>
