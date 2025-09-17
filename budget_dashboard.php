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
$salvStmt = $conn->prepare('SELECT b.*, s.nome_salvadanaio, s.importo_attuale
    FROM budget b
    LEFT JOIN salvadanai s ON b.id_salvadanaio = s.id_salvadanaio
    WHERE b.id_famiglia = ?
      AND YEAR(b.data_inizio) <= ?
      AND (b.data_scadenza IS NULL OR YEAR(b.data_scadenza) >= ?)
      AND (b.data_scadenza IS NULL OR b.data_scadenza >= ?)
');
if (!$salvStmt) {
    die("Prepare failed: " . $conn->error);
}
$todaySql = $today->format('Y-m-d');
$salvStmt->bind_param('iiis', $idFamiglia, $anno, $anno, $todaySql);

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
    $dataInizio = $row['data_inizio'] ?: null;
    $dataScadenza = $row['data_scadenza'] ?: null;
    if ($dataScadenza !== null && $dataScadenza < $todaySql) {
        continue;
    }
    $j = $dataScadenza ? diff_mesi($today->format('Y-m-d'), $dataScadenza) : null; // mesi a scadenza
    //$k = $dataInizio ? max(0, diff_mesi($dataInizio, $today->format('Y-m-d'))) : 0; // mesi da inizio
    $primo_del_mese_di_data_inizio = (new DateTime($dataInizio))->modify('first day of this month');
    $k = $dataInizio ? max(0, diff_mesi($primo_del_mese_di_data_inizio->format('Y-m-d'), $today->format('Y-m-d'))) : 0; // mesi da inizio
    $mesi_data_inizio_fine = $dataInizio ? max(0, diff_mesi($primo_del_mese_di_data_inizio->format('Y-m-d'), $dataScadenza)) : 0; // mesi da inizio
    if(strtotime($dataInizio)<time() && strtotime($dataScadenza)>time())
    {
        $importoMensile = round($residuo / $mesi_data_inizio_fine, 2);
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
        $importoStimato = 0;
    }
    $salv = $row['id_salvadanaio'];
    if (!isset($salvadanai[$salv])) {
        $salvadanai[$salv] = [
            'id'      => $salv,
            'nome'    => $row['nome_salvadanaio'],
            'importo_mensile' => 0,
            'stimato' => 0,
            'attuale' => (float)($row['importo_attuale'] ?? 0),
        ];
    }
    $salvadanai[$salv]['importo_mensile'] += $importoMensile;
    $salvadanai[$salv]['stimato'] += $importoStimato;
}
$salvadanai = array_values($salvadanai);
usort($salvadanai, function($a, $b) {
    return strcasecmp($a['nome'], $b['nome']); // ordinamento case-insensitive
});
$salvStmt->close();

// 2. Uscite mensili (solo totale)
$usciteStmt = $conn->prepare("SELECT * FROM budget WHERE id_famiglia = ? AND tipologia = 'uscita' AND tipologia_spesa = 'mensile' AND YEAR(data_inizio) <= ? AND (data_scadenza IS NULL OR YEAR(data_scadenza) >= ?)");
$usciteStmt->bind_param('iii', $idFamiglia, $anno, $anno);
$usciteStmt->execute();
//$usciteStmt->bind_result($totalUsciteMensili);
$usciteMensili = $usciteStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$usciteStmt->close();

// 3. Entrate mensili fisse
$entrateStmt = $conn->prepare("SELECT * FROM budget WHERE id_famiglia = ? AND tipologia = 'entrata' AND tipologia_spesa = 'mensile' AND YEAR(data_inizio) <= ? AND (data_scadenza IS NULL OR data_scadenza >= now())");
$entrateStmt->bind_param('ii', $idFamiglia, $anno);
$entrateStmt->execute();
$entrateMensili = $entrateStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$entrateStmt->close();

$totalEntrateMensili = array_sum(array_column($entrateMensili, 'importo'));

// 4. Uscite annuali (calcolo importo mensile)
$annualiStmt = $conn->prepare("SELECT * FROM budget WHERE id_famiglia = ? AND tipologia = 'uscita' AND tipologia_spesa IN ('una_tantum','fissa') AND YEAR(data_inizio) <= ? AND (data_scadenza IS NULL OR YEAR(data_scadenza) >= ?)");
$annualiStmt->bind_param('iii', $idFamiglia, $anno, $anno);
$annualiStmt->execute();
$resAnnuali = $annualiStmt->get_result();
$totalAnnualiMensile = 0;
while ($row = $resAnnuali->fetch_assoc()) {
    $importo = (float)($row['importo'] ?? 0);
    $da13 = (float)($row['da_13esima'] ?? 0);
    $da14 = (float)($row['da_14esima'] ?? 0);
    $residuo = $importo - ($da13 + $da14) ;
    $importoMensile = round($residuo / 12, 2);
    //$totalAnnualiMensile += $importoMensile;
    
    $dataInizio = $row['data_inizio'] ?: null;
    $dataScadenza = $row['data_scadenza'] ?: null;
    if(strtotime($dataInizio)<time() && strtotime($dataScadenza)>time())
    {
        $totalAnnualiMensile += round($residuo / 12, 2);
    }
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
  <div class="col-6 col-md-4 d-flex">
    <button type="submit" class="btn btn-outline-light w-100">Filtra</button>
    <a href="budget_anno.php" class="btn btn-outline-light w-100 ms-1">Vai a Anno</a>
  </div>
</form>
<div class="row">
  <div class="col-12 col-lg-8">
    <h5>Importi stimati attuali per salvadanaio</h5>
    <table class="table table-dark table-striped table-sm">
      <thead>
        <tr>
          <th>Salvadanaio</th>
          <th class="text-end">Importo mensile</th>
          <th class="text-end">Importo stimato</th>
          <th class="text-end">Importo attuale</th>
          <th class="text-end">Differenza</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $per_totali['stimato'] = 0;
        $per_totali['attuale'] = 0;
        foreach ($salvadanai as $dati):
        $per_totali['mensile'] += $dati['importo_mensile'];
        $per_totali['stimato'] += $dati['stimato'];
        $per_totali['attuale'] += $dati['attuale'];
        ?>
        <tr>
          <td><a href="budget_anno.php?<?= http_build_query(['id_salvadanaio' => $dati['id']]) ?>"><?= htmlspecialchars($dati['nome']) ?></a></td>
          <td class="text-end"><?= number_format($dati['importo_mensile'], 2, ',', '.') ?></td>
          <td class="text-end"><?= number_format($dati['stimato'], 2, ',', '.') ?></td>
          <td class="text-end"><?= number_format($dati['attuale'], 2, ',', '.') ?></td>
          <td class="text-end"><?= number_format($dati['stimato'] - $dati['attuale'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>

        <tr>
          <td></td>
          <td class="text-end"><?= number_format($per_totali['mensile'], 2, ',', '.') ?></td>
          <td class="text-end"><?= number_format($per_totali['stimato'], 2, ',', '.') ?></td>
          <td class="text-end"><?= number_format($per_totali['attuale'], 2, ',', '.') ?></td>
          <td class="text-end"></td>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="col-12 col-lg-4">
    <h5>Entrate mensili fisse</h5>
    <table class="table table-dark table-striped table-sm">
      <thead>
        <tr>
          <th>Descrizione</th>
          <th class="text-end">Importo</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $totalEntrateMensili = 0;
        foreach ($entrateMensili as $r):
        $totalEntrateMensili += $r['importo'];
        ?>
        <tr>
          <td><?= htmlspecialchars($r['descrizione'] ?? '') ?></td>
          <td class="text-end"><?= number_format((float)$r['importo'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <h5>Uscite mensili fisse</h5>
    <table class="table table-dark table-striped table-sm">
      <thead>
        <tr>
          <th>Descrizione</th>
          <th class="text-end">Importo</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $totalUsciteMensili = 0;
        foreach ($usciteMensili as $r):
        $totalUsciteMensili += $r['importo'];
        ?>
        <tr>
          <td><?= htmlspecialchars($r['descrizione'] ?? '') ?></td>
          <td class="text-end"><?= number_format((float)$r['importo'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <h5>Riepilogo</h5>
    <table class="table table-dark table-striped table-sm">
      <tbody>
        <tr>
          <td>Entrate mensili fisse</td>
          <td class="text-end"><?= number_format($totalEntrateMensili, 2, ',', '.') ?></td>
        </tr>
        <tr>
          <td>Uscite mensili fisse</td>
          <td class="text-end"><?= number_format($totalUsciteMensili, 2, ',', '.') ?></td>
        </tr>
        <tr>
          <td>Uscite ricorrenti annuali (mensile)</td>
          <td class="text-end"><?= number_format($totalAnnualiMensile, 2, ',', '.') ?></td>
        </tr>
        <tr>
          <td>Margine (mensile)</td>
          <td class="text-end"><?= number_format($totalEntrateMensili-($totalUsciteMensili+$totalAnnualiMensile), 2, ',', '.') ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
