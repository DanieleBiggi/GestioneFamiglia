<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/utility.php';
require_once 'includes/permissions.php';
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
$salvStmt = $conn->prepare('SELECT b.*, s.nome_salvadanaio, s.importo_attuale, s.data_aggiornamento_manuale, s.accantonamento_modalita, s.accantonamento_fisso
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
$per_totali = [
    'mensile' => 0,
    'stimato' => 0,
    'attuale' => 0,
];
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
            'modalita' => $row['accantonamento_modalita'] ?? 'automatico',
            'fisso' => $row['accantonamento_fisso'] === null ? null : (float)$row['accantonamento_fisso'],
            'data_verifica' => $row['data_aggiornamento_manuale'] ?? null,
            'obiettivo' => 0,
            'scadenza' => null,
        ];
    }
    $salvadanai[$salv]['importo_mensile'] += $importoMensile;
    $salvadanai[$salv]['stimato'] += $importoStimato;
    if (($row['tipologia'] ?? '') === 'uscita' && ($row['tipologia_spesa'] ?? '') !== 'mensile') {
        $salvadanai[$salv]['obiettivo'] += max(0, $residuo);
        if ($dataScadenza && (!$salvadanai[$salv]['scadenza'] || $dataScadenza < $salvadanai[$salv]['scadenza'])) {
            $salvadanai[$salv]['scadenza'] = $dataScadenza;
        }
    }
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

// Salvadanaio collegato a vacanza: vista additiva, fuori dai totali di budget.
// Evita di generare budget fittizi soltanto per mostrarlo in dashboard.
$tripSavings=[];
if (has_permission($conn,'table:viaggi','view')) {
 $qTripSavings=$conn->prepare('SELECT v.id_viaggio,v.titolo,s.id_salvadanaio,s.nome_salvadanaio,s.importo_attuale,p.obiettivo_salvadanaio FROM viaggi_pianificazione p JOIN viaggi v ON v.id_viaggio=p.id_viaggio JOIN salvadanai s ON s.id_salvadanaio=p.id_salvadanaio WHERE p.id_salvadanaio IS NOT NULL ORDER BY v.titolo');
 $qTripSavings->execute();$tripSavings=$qTripSavings->get_result()->fetch_all(MYSQLI_ASSOC);$qTripSavings->close();
}
// 5. Margine mensile
$margineMensile = $totalEntrateMensili - ($totalUsciteMensili + $totalAnnualiMensile);
?>
<div class="d-flex mb-3 justify-content-between align-items-center">
  <h4 class="mb-0">Budget Dashboard</h4>
  <a href="budget_importi_attuali.php" class="btn btn-outline-light btn-sm">Modifica importo attuale</a>
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
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <div>
        <h5 class="mb-1">Accantonamenti e Pocket Revolut</h5>
        <p class="small text-white-50 mb-0">Il valore fisso non cambia quando variano le previsioni. I saldi vanno aggiornati manualmente.</p>
      </div>
      <a href="budget_importi_attuali.php" class="btn btn-outline-light">Aggiorna saldi Pocket</a>
    </div>
    <div class="row g-3" aria-label="Riepilogo accantonamenti per salvadanaio">
      <?php
      $per_totali['stimato'] = 0;
      $per_totali['attuale'] = 0;
      $totalePocket = 0;
      foreach ($salvadanai as $dati):
        $per_totali['mensile'] += $dati['importo_mensile'];
        $per_totali['stimato'] += $dati['stimato'];
        $per_totali['attuale'] += $dati['attuale'];
        $mesiResidui = $dati['scadenza'] ? max(1, diff_mesi($todaySql, $dati['scadenza'])) : 12;
        $consigliato = $dati['scadenza'] ? round(max(0, $dati['obiettivo'] - $dati['attuale']) / $mesiResidui, 2) : $dati['importo_mensile'];
        $pocket = $dati['modalita'] === 'fisso' ? (float)($dati['fisso'] ?? 0) : $consigliato;
        $totalePocket += $pocket;
        $proiezione = $dati['attuale'] + $pocket * $mesiResidui;
        $gap = $dati['obiettivo'] - $proiezione;
      ?>
      <div class="col-12 col-md-6">
        <section class="card bg-dark text-white border-secondary h-100 p-3" aria-label="Salvadanaio <?= htmlspecialchars($dati['nome'] ?? '', ENT_QUOTES) ?>">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <a class="fw-bold text-white text-decoration-underline" href="salvadanaio_dettaglio.php?id=<?= (int)$dati['id'] ?>"><?= htmlspecialchars($dati['nome'] ?? 'Senza nome') ?></a>
            <span class="badge <?= $dati['modalita'] === 'fisso' ? 'bg-info text-dark' : 'bg-secondary' ?>"><?= $dati['modalita'] === 'fisso' ? 'Fisso' : 'Automatico' ?></span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <div class="small text-white-50">Versamento Pocket</div>
              <div class="fs-4 fw-semibold"><?= number_format($pocket, 2, ',', '.') ?> €/mese</div>
            </div>
            <div class="col-6">
              <div class="small text-white-50">Consigliato</div>
              <div class="fs-4 fw-semibold"><?= number_format($consigliato, 2, ',', '.') ?> €/mese</div>
            </div>
            <div class="col-6">
              <div class="small text-white-50">Saldo verificato</div>
              <div class="fw-semibold"><?= number_format($dati['attuale'], 2, ',', '.') ?> €</div>
            </div>
            <div class="col-6">
              <div class="small text-white-50">Saldo stimato oggi</div>
              <div class="fw-semibold"><?= number_format($dati['stimato'], 2, ',', '.') ?> €</div>
            </div>
          </div>
          <div class="small text-white-50">Ultima verifica: <?= $dati['data_verifica'] ? htmlspecialchars(date('d/m/Y', strtotime($dati['data_verifica']))) : 'non disponibile' ?></div>
          <?php if ($dati['scadenza'] && $dati['obiettivo'] > 0): ?>
            <div class="mt-2 small">Proiezione al <?= date('d/m/Y', strtotime($dati['scadenza'])) ?>: <strong><?= number_format($proiezione,2,',','.') ?> €</strong> su <?= number_format($dati['obiettivo'],2,',','.') ?> €</div>
            <div class="progress mt-1" role="progressbar" aria-label="Copertura obiettivo" aria-valuenow="<?= min(100,max(0,round($proiezione/$dati['obiettivo']*100))) ?>" aria-valuemin="0" aria-valuemax="100">
              <div class="progress-bar <?= $gap > 0.01 ? 'bg-warning' : 'bg-success' ?>" style="width:<?= min(100,max(0,round($proiezione/$dati['obiettivo']*100))) ?>%"></div>
            </div>
            <div class="small mt-1 <?= $gap > 0.01 ? 'text-warning' : 'text-success' ?>"><?= $gap > 0.01 ? 'Possibile scoperto: ' . number_format($gap,2,',','.') . ' €' : 'Copertura prevista sufficiente' ?></div>
            <div class="small text-white-50">Proiezione indicativa: non sottrae pagamenti intermedi. Controlla le singole scadenze nel budget.</div>
          <?php endif; ?>
          <a class="btn btn-outline-light mt-3 w-100" href="salvadanaio_dettaglio.php?id=<?= (int)$dati['id'] ?>">Gestisci Pocket</a>
        </section>
      </div>
      <?php endforeach; ?>
    </div>
    <section class="card bg-dark text-white border-secondary p-3 mt-3 mb-4" aria-label="Totali Pocket">
      <div class="row g-2">
        <div class="col-6"><div class="small text-white-50">Accantonamento Pocket complessivo</div><strong><?= number_format($totalePocket,2,',','.') ?> €/mese</strong></div>
        <div class="col-6"><div class="small text-white-50">Saldo attuale complessivo</div><strong><?= number_format($per_totali['attuale'],2,',','.') ?> €</strong></div>
      </div>
    </section>
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
        <tr>
          <td class="fw-bold">Totale</td>
          <td class="text-end fw-bold"><?= number_format($totalUsciteMensili, 2, ',', '.') ?></td>
        </tr>
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
          <td>Uscite totali (mensile)</td>
          <td class="text-end"><?= number_format($totalUsciteMensili + $totalAnnualiMensile, 2, ',', '.') ?></td>
        </tr>
        <tr>
          <td>Margine (mensile)</td>
          <td class="text-end"><?= number_format($totalEntrateMensili - ($totalUsciteMensili + $totalAnnualiMensile), 2, ',', '.') ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php if($tripSavings): ?>
<section class="mb-4" aria-label="Salvadanai delle vacanze">
 <h5>Salvadanai collegati alle vacanze</h5>
 <div class="row g-2">
 <?php foreach($tripSavings as $saving): ?>
 <div class="col-12 col-md-6 col-xl-4"><div class="card p-3 h-100">
  <div class="fw-semibold"><?= htmlspecialchars($saving['nome_salvadanaio'],ENT_QUOTES,'UTF-8') ?></div>
  <div class="small"><?= htmlspecialchars($saving['titolo'],ENT_QUOTES,'UTF-8') ?></div>
  <div class="my-2">Accantonato: <strong><?= number_format((float)$saving['importo_attuale'],2,',','.') ?> €</strong>
  <?php if($saving['obiettivo_salvadanaio']!==null): ?>
  di <?= number_format((float)$saving['obiettivo_salvadanaio'],2,',','.') ?> €
  <?php endif; ?></div>
  <div class="d-flex gap-2 flex-wrap">
   <a href="vacanze_workspace.php?id=<?= (int)$saving['id_viaggio'] ?>" class="btn btn-outline-secondary btn-sm">Apri vacanza</a>
   <a href="salvadanaio_dettaglio.php?id=<?= (int)$saving['id_salvadanaio'] ?>" class="btn btn-outline-secondary btn-sm">Apri salvadanaio</a>
  </div>
 </div></div>
 <?php endforeach; ?>
 </div>
</section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
