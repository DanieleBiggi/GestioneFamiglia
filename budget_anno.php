<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/utility.php';
include 'includes/header.php';

date_default_timezone_set('Europe/Rome');

$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

// Filtri
$anno = $_GET['anno'] ?? '';
$mese = $_GET['mese'] ?? '';
$tipologiaSpesa = $_GET['tipologia_spesa'] ?? '';
$search = trim($_GET['q'] ?? '');
$export = isset($_GET['export']);

$conditions = ['b.id_famiglia = ?'];
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
if ($search !== '') {
    $conditions[] = '(b.descrizione LIKE ? OR s.nome_salvadanaio LIKE ?)';
    $like = '%'.$search.'%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
$where = implode(' AND ', $conditions);

$sql = "SELECT b.*, s.nome_salvadanaio FROM budget b LEFT JOIN salvadanai s ON b.id_salvadanaio = s.id_salvadanaio WHERE $where ORDER BY b.data_scadenza";
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
    $importoMensile = round($residuo / 12, 2);

    $dataInizio = $row['data_inizio'] ?: null;
    $dataScadenza = $row['data_scadenza'] ?: null;

$j = $dataScadenza ? diff_mesi($today->format('Y-m-d'), $dataScadenza) : null; // mesi a scadenza
$k = $dataInizio ? max(0, diff_mesi($dataInizio, $today->format('Y-m-d'))) : 0; // mesi da inizio

    if ($dataScadenza) {
        if ($j < 0) {
            $importoStimato = 0.00;
        } elseif ($j == 0) {
            $importoStimato = '';
        } else {
            $importoStimato = round($importoMensile * $k, 2);
        }
    } else {
        $importoStimato = '';
    }

    $rows[] = [
        'tipologia' => $row['tipologia'] ?? '',
        'importo' => $importo,
        'salvadanaio' => $row['nome_salvadanaio'] ?: ($row['id_salvadanaio'] ?? ''),
        'descrizione' => $row['descrizione'] ?? '',
        'data_inizio' => $dataInizio,
        'data_scadenza' => $dataScadenza,
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
            number_format($r['importo'],2,'.',''),
            $r['salvadanaio'],
            $r['descrizione'],
            $r['data_inizio'],
            $r['data_scadenza'],
            $r['tipologia_spesa'] === 'fissa' ? 'Fissa' : ($r['tipologia_spesa'] === 'una_tantum' ? 'Una Tantum' : ''),
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
$yearStmt = $conn->prepare('SELECT DISTINCT YEAR(data_scadenza) AS anno FROM budget WHERE id_famiglia = ? AND data_scadenza IS NOT NULL ORDER BY anno');
$yearStmt->bind_param('i', $idFamiglia);
$yearStmt->execute();
$years = $yearStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$yearStmt->close();
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
</form>
<div class="table-responsive" style="width:100vw;margin-left:calc(-50vw + 50%);">
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
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
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
      <td><?= htmlspecialchars($r['data_inizio']) ?></td>
      <td><?= htmlspecialchars($r['data_scadenza']) ?></td>
      <td class="text-end" data-sort="<?= number_format($r['da_13esima'],2,'.','') ?>"><?= number_format($r['da_13esima'],2,',','.') ?></td>
      <td class="text-end" data-sort="<?= number_format($r['da_14esima'],2,'.','') ?>"><?= number_format($r['da_14esima'],2,',','.') ?></td>
      <td class="text-end" data-sort="<?= number_format($r['importo'],2,'.','') ?>"><?= number_format($r['importo'],2,',','.') ?></td>
      <td class="text-end" data-sort="<?= $r['importo_stimato'] === '' ? '' : number_format($r['importo_stimato'],2,'.','') ?>">
        <?= $r['importo_stimato'] === '' ? '' : number_format($r['importo_stimato'],2,',','.') ?>
      </td>
      <td class="text-end" data-sort="<?= number_format($r['importo_mensile'],2,'.','') ?>"><?= number_format($r['importo_mensile'],2,',','.') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot class="table-dark" style="position: sticky; bottom: 0;">
    <tr>
      <th colspan="8">Totali</th>
      <th class="text-end"><?= number_format($totImporto,2,',','.') ?></th>
      <th class="text-end"><?= number_format($totStimato,2,',','.') ?></th>
      <th class="text-end"><?= number_format($totMensile,2,',','.') ?></th>
    </tr>
  </tfoot>
</table>
</div>
<script>
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
</script>
<?php include 'includes/footer.php'; ?>
