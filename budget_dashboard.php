<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/utility.php';
include 'includes/header.php';

$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$anno = $_GET['anno'] ?? date('Y');

// Recupero anni disponibili
$yearStmt = $conn->prepare('SELECT DISTINCT YEAR(data_scadenza) AS anno FROM budget WHERE id_famiglia = ? AND data_scadenza IS NOT NULL ORDER BY anno');
$yearStmt->bind_param('i', $idFamiglia);
$yearStmt->execute();
$years = $yearStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$yearStmt->close();

$today = new DateTime('now', new DateTimeZone('Europe/Rome'));

// 1. Importo stimato attuale per salvadanaio
$salvStmt = $conn->prepare('SELECT b.*, s.nome_salvadanaio, s.importo_attuale FROM budget b LEFT JOIN salvadanai s ON b.id_salvadanaio = s.id_salvadanaio WHERE b.id_famiglia = ? AND year(b.data_scadenza) > ? AND YEAR(b.data_inizio) <= ?');
if (!$salvStmt) {
    die("Prepare failed: " . $conn->error);
}
$salvStmt->bind_param('iii', $idFamiglia, $anno, $anno);

if (!$salvStmt->execute()) {
    die("Execute failed: " . $salvStmt->error);
}

$resSalv = $salvStmt->get_result();
$salvadanai = [];
while ($row = $resSalv->fetch_assoc()) {
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
            $importoStimato = $importo;
        } else {
            $importoStimato = round($importoMensile * $k, 2);
        }
    } else {
        $importoStimato = 0;
    }
    $salv = $row['id_salvadanaio'];
    if (!isset($salvadanai[$salv])) {
        $salvadanai[$salv] = [
            'stimato' => 0,
            'attuale' => (float)($row['importo_attuale'] ?? 0),
        ];
    }
    $salvadanai[$salv]['nome'] = $row['nome_salvadanaio'];
    $salvadanai[$salv]['stimato'] += $importoStimato;
}
usort($salvadanai, function($a, $b) {
    return strcasecmp($a['nome'], $b['nome']); // ordinamento case-insensitive
});
$salvStmt->close();

// 2. Uscite mensili (solo totale)
$usciteStmt = $conn->prepare("SELECT SUM(importo) AS totale FROM budget WHERE id_famiglia = ? AND tipologia = 'uscita' AND tipologia_spesa = 'fissa' AND YEAR(data_inizio) <= ? AND (data_scadenza IS NULL OR YEAR(data_scadenza) >= ?)");
$usciteStmt->bind_param('iii', $idFamiglia, $anno, $anno);
$usciteStmt->execute();
$usciteStmt->bind_result($totalUsciteMensili);
$usciteStmt->fetch();
$usciteStmt->close();

// 3. Entrate mensili fisse
$entrateStmt = $conn->prepare("SELECT * FROM budget WHERE id_famiglia = ? AND tipologia = 'entrata' AND tipologia_spesa = 'fissa' AND YEAR(data_inizio) <= ? AND (data_scadenza IS NULL OR YEAR(data_scadenza) >= ?)");
$entrateStmt->bind_param('iii', $idFamiglia, $anno, $anno);
$entrateStmt->execute();
$entrateMensili = $entrateStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$entrateStmt->close();

$totalEntrateMensili = array_sum(array_column($entrateMensili, 'importo'));

// 4. Uscite annuali (calcolo importo mensile)
$annualiStmt = $conn->prepare("SELECT * FROM budget WHERE id_famiglia = ? AND tipologia = 'uscita' AND tipologia_spesa = 'una_tantum' AND YEAR(data_inizio) <= ? AND (data_scadenza IS NULL OR YEAR(data_scadenza) >= ?)");
$annualiStmt->bind_param('iii', $idFamiglia, $anno, $anno);
$annualiStmt->execute();
$resAnnuali = $annualiStmt->get_result();
$totalAnnualiMensile = 0;
while ($row = $resAnnuali->fetch_assoc()) {
    $importo = (float)($row['importo'] ?? 0);
    $da13 = (float)($row['da_13esima'] ?? 0);
    $da14 = (float)($row['da_14esima'] ?? 0);
    $residuo = $importo - ($da13 + $da14);
    $importoMensile = round($residuo / 12, 2);
    $totalAnnualiMensile += $importoMensile;
}
$annualiStmt->close();

// 5. Margine mensile
$margineMensile = $totalEntrateMensili - ($totalUsciteMensili + $totalAnnualiMensile);
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Budget Dashboard</h4>
</div>
<form method="get" class="row g-2 mb-3">
  <div class="col-6 col-md-2">
    <select name="anno" class="form-select bg-dark text-white border-secondary">
      <?php foreach ($years as $y): ?>
      <option value="<?= (int)$y['anno'] ?>" <?= ((int)$anno === (int)$y['anno']) ? 'selected' : '' ?>><?= (int)$y['anno'] ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-2">
    <button type="submit" class="btn btn-outline-light w-100">Filtra</button>
  </div>
</form>
<h5>Importi stimati attuali per salvadanaio</h5>
<table class="table table-dark table-striped table-sm">
  <thead>
    <tr>
      <th>Salvadanaio</th>
      <th class="text-end">Importo stimato</th>
      <th class="text-end">Importo attuale</th>
      <th class="text-end">Differenza</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($salvadanai as $nome => $dati): ?>
    <tr>
      <td><?= htmlspecialchars($dati['nome']) ?></td>
      <td class="text-end"><?= number_format($dati['stimato'], 2, ',', '.') ?></td>
      <td class="text-end"><?= number_format($dati['attuale'], 2, ',', '.') ?></td>
      <td class="text-end"><?= number_format($dati['stimato'] - $dati['attuale'], 2, ',', '.') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<h5>Entrate mensili fisse</h5>
<table class="table table-dark table-striped table-sm">
  <thead>
    <tr>
      <th>Descrizione</th>
      <th class="text-end">Importo</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($entrateMensili as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['descrizione'] ?? '') ?></td>
      <td class="text-end"><?= number_format((float)$r['importo'], 2, ',', '.') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<h5>Totale uscite</h5>
<table class="table table-dark table-striped table-sm">
  <tbody>
    <tr>
      <td>Uscite ricorrenti mensili (annuale)</td>
      <td class="text-end"><?= number_format($totalUsciteMensili * 12, 2, ',', '.') ?></td>
    </tr>
    <tr>
      <td>Uscite ricorrenti annuali (mensile)</td>
      <td class="text-end"><?= number_format($totalAnnualiMensile, 2, ',', '.') ?></td>
    </tr>
  </tbody>
</table>
<h5>Margine mensile</h5>
<p><?= number_format($margineMensile, 2, ',', '.') ?> &euro;</p>
<?php include 'includes/footer.php'; ?>
