<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/utility.php';
require_once 'includes/budget_pocket_previsione.php';
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
    $importoMensile = gf_budget_importo_mensile($row, $todaySql);
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
            'quota_13' => 0,
            'quota_14' => 0,
            'scadenza' => null,
            'impegni' => [],
        ];
    }
    $salvadanai[$salv]['importo_mensile'] += $importoMensile;
    $salvadanai[$salv]['stimato'] += $importoStimato;
    if (($row['tipologia'] ?? '') === 'uscita' && ($row['tipologia_spesa'] ?? '') !== 'mensile') {
        // Gross planned expenditure includes the December/July bonus allocations.
        // The ordinary monthly budget remains net of the 13th and 14th.
        $salvadanai[$salv]['obiettivo'] += max(0, $importo);
        $salvadanai[$salv]['quota_13'] += max(0, $da13);
        $salvadanai[$salv]['quota_14'] += max(0, $da14);
        $salvadanai[$salv]['impegni'][] = $row;
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
$annualiConPocket = 0;
while ($row = $resAnnuali->fetch_assoc()) {
    $importo = (float)($row['importo'] ?? 0);
    $da13 = (float)($row['da_13esima'] ?? 0);
    $da14 = (float)($row['da_14esima'] ?? 0);
    $residuo = $importo - ($da13 + $da14) ;
    $importoMensile = gf_budget_importo_mensile($row, $todaySql);
    //$totalAnnualiMensile += $importoMensile;
    
    $dataInizio = $row['data_inizio'] ?: null;
    $dataScadenza = $row['data_scadenza'] ?: null;
    if ($dataInizio && $dataScadenza && $dataInizio < $todaySql && $dataScadenza > $todaySql)
    {
        $quotaAnnualizzata = gf_budget_importo_mensile($row, $todaySql);
        $totalAnnualiMensile += $quotaAnnualizzata;
        if (!empty($row['id_salvadanaio'])) $annualiConPocket += $quotaAnnualizzata;
    }
}
$annualiStmt->close();

// Simple shortcut, one or more trips can point to the same saving pot.
$viaggiSalvadanai = [];
if (has_permission($conn, 'table:viaggi', 'view')) {
    $qTripSavings = $conn->prepare('SELECT p.id_salvadanaio, v.id_viaggio, v.titolo FROM viaggi_pianificazione p JOIN viaggi v ON v.id_viaggio = p.id_viaggio WHERE p.id_salvadanaio IS NOT NULL ORDER BY v.titolo');
    if ($qTripSavings) {
        $qTripSavings->execute();
        foreach ($qTripSavings->get_result()->fetch_all(MYSQLI_ASSOC) as $linked) {
            $sid = (int)$linked['id_salvadanaio'];
            $viaggiSalvadanai[$sid][] = $linked;
        }
        $qTripSavings->close();
    }
}
// Calculate each pot once and reuse the exact same status in desktop and mobile.
$totalePocket = 0.0;
$totaleAttuale = 0.0;
$totaleAtteso = 0.0;
$totaleConsigliato = 0.0;
$rowsPocket = [];
foreach ($salvadanai as $dati) {
    $piano = gf_pocket_previsione($dati['impegni'], (float)$dati['attuale'], 0.0, $todaySql);
    $consigliato = round((float)$dati['importo_mensile'], 2); // Totale mensile identico a budget_anno.php
    $pocket = $dati['modalita'] === 'fisso' ? (float)($dati['fisso'] ?? 0) : $consigliato;
    $previsione = gf_pocket_previsione($dati['impegni'], (float)$dati['attuale'], $pocket, $todaySql);
    $gap = $previsione['peggiore'] === null ? 0.0 : max(0.0, -(float)$previsione['peggiore']['saldo']);
    // "Today" is benchmarked against the original accumulation plan, not a guess from current cash.
    // A past December bonus is counted only if the budget was already active at that December.
    $attesoOggi = 0.0;
    foreach ($dati['impegni'] as $impegno) {
        $start = (string)($impegno['data_inizio'] ?? '');
        $deadline = (string)($impegno['data_scadenza'] ?? '');
        if (!$start || !$deadline || $start > $todaySql) continue;
        $monthsPlanned = max(1, (int)(diff_mesi(substr($start,0,7).'-01', $deadline) ?? 0));
        $monthsElapsed = max(0, (int)(diff_mesi(substr($start,0,7).'-01', $todaySql) ?? 0));
        $monthly = max(0.0, ((float)$impegno['importo'] - (float)$impegno['da_13esima'] - (float)$impegno['da_14esima']) / $monthsPlanned);
        $attesoOggi += $monthly * min($monthsElapsed, $monthsPlanned);
        $decemberPrevious = ((int)substr($todaySql,0,4)-1) . '-12-01';
        $decemberThis = substr($todaySql,0,4) . '-12-01';
        if ((($decemberPrevious >= $start && $decemberPrevious < $todaySql && $decemberPrevious <= $deadline) ||
             ($decemberThis >= $start && $decemberThis < $todaySql && $decemberThis <= $deadline))) {
            $attesoOggi += max(0, (float)$impegno['da_13esima']);
        }
    }
    $dati['atteso_oggi'] = round($attesoOggi, 2);
    // Remaining capital to build, excluding the future bonus but before monthly transfers.
    // This is actionable today, unlike a historical benchmark based on another saving rate.
    $dati['da_accantonare'] = max(0, round($previsione['obiettivo'] - (float)$dati['attuale'] - $previsione['tredicesima_futura'] - $previsione['quattordicesima_futura'], 2));
    $dati['scostamento_oggi'] = round((float)$dati['attuale'] - $attesoOggi, 2);
    $dati['oggi_in_linea'] = $dati['scostamento_oggi'] >= -0.01;
    $dati['pocket'] = $pocket;
    $dati['consigliato'] = $consigliato;
    $dati['scoperto_futuro'] = $gap;
    $dati['eccedenza_attuale'] = $previsione['eccedenza_attuale'];
    $dati['eccedenza_prevista'] = $previsione['eccedenza_prevista'];
    $dati['rischio_data'] = $previsione['peggiore']['data'] ?? null;
    $dati['futuro_in_linea'] = $gap <= 0.01;
    $dati['bonus13_futuro'] = $previsione['tredicesima_futura'];
    $dati['bonus14_futuro'] = $previsione['quattordicesima_futura'];
    $dati['viaggi'] = $viaggiSalvadanai[(int)$dati['id']] ?? [];
    $rowsPocket[] = $dati;
    $totalePocket += $pocket;
    $totaleAttuale += (float)$dati['attuale'];
    $totaleAtteso += $attesoOggi;
    $totaleConsigliato += $consigliato;
}
$totalUsciteMensili = array_sum(array_map(static function ($r) { return (float)$r['importo']; }, $usciteMensili));
$altreQuoteAnnuali = max(0, $totalAnnualiMensile - $annualiConPocket);
$margineConPocket = $totalEntrateMensili - $totalUsciteMensili - $altreQuoteAnnuali - $totalePocket;
?>
<style>
/* One scroll only: never constrain dashboard data to an inner scrolling container. */
.gf-dashboard {width:100%;max-width:none;}
.gf-budget-table {width:100%;font-size:.83rem;}
/* Bootstrap table-dark forces dark colors even with a light user theme.
   Keep the table palette aligned with the selected theme, including all links. */
.gf-dashboard .gf-budget-table, .gf-dashboard .gf-mini-table {
  --bs-table-bg:var(--primary-color);
  --bs-table-color:var(--text-color);
  --bs-table-striped-bg:var(--secondary-color);
  --bs-table-striped-color:var(--text-color);
  --bs-table-border-color:rgba(var(--text-color-rgb),.19);
  --bs-table-hover-color:var(--text-color);
  --bs-table-hover-bg:var(--secondary-color);
  color:var(--text-color);
}
.gf-dashboard .gf-budget-table a,
.gf-dashboard .gf-mini-table a {color:var(--text-color)!important;text-decoration:underline;}
.gf-dashboard .gf-budget-table .gf-trip {color:var(--text-color)!important}
.gf-dashboard .gf-budget-table a:focus-visible {outline:2px solid var(--text-color);outline-offset:2px}
.gf-budget-table td,.gf-budget-table th {padding:.24rem .35rem;vertical-align:middle;}
.gf-budget-table .gf-name {max-width:185px;white-space:normal;line-height:1.2}
.gf-budget-table .gf-number {white-space:nowrap;text-align:right;font-variant-numeric:tabular-nums}
.gf-budget-table .gf-status {white-space:nowrap; font-weight:600}
.gf-budget-table .gf-trip {white-space:nowrap;text-decoration:none}
.gf-budget-table .gf-muted {font-size:.73rem;opacity:.85}
.gf-mini-table {font-size:.84rem; margin-bottom:.35rem}
.gf-mini-table td,.gf-mini-table th {padding:.2rem .4rem;}
.gf-panel {border:1px solid var(--bs-secondary);border-radius:.5rem;padding:.55rem .7rem;height:100%;}
.gf-summary-strong {font-weight:700}
.gf-dashboard .text-white-50 {color:rgba(var(--text-color-rgb),.72)!important}
@media (max-width:991.98px) {
  .gf-budget-mobile-row {border:1px solid var(--bs-secondary);border-radius:.65rem;padding:.7rem;margin-bottom:.5rem;}
  .gf-mini-table {font-size:.9rem}
}
@media (min-width:992px) {
  .gf-dashboard .table-responsive {overflow-x:visible!important;}
  .gf-desktop-panels {display:grid;grid-template-columns:1fr 1fr 1fr;gap:.6rem;}
}
</style>
<main class="gf-dashboard">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
    <h4 class="mb-0">Dashboard Budget</h4>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <form method="get" class="d-flex gap-1 align-items-center">
        <label for="gf-anno" class="small">Anno</label>
        <select id="gf-anno" name="anno" class="form-select form-select-sm" aria-label="Anno del budget">
          <?php foreach ($years as $y): ?>
          <option value="<?= (int)$y['anno'] ?>" <?= (int)$anno === (int)$y['anno'] ? 'selected' : '' ?>><?= (int)$y['anno'] ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-light btn-sm" type="submit">Mostra</button>
      </form>
      <a href="budget_anno.php" class="btn btn-outline-light btn-sm">Budget annuale</a>
      <a href="budget_importi_attuali.php" class="btn btn-outline-light btn-sm">Aggiorna saldi</a>
    </div>
  </div>
  <section aria-label="Stato salvadanai">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
      <h5 class="mb-0">Salvadanai e Pocket Revolut</h5>
      <span class="small">Saldo reale, eccedenze già disponibili e avanzi previsti alle scadenze</span>
    </div>
    <div class="d-none d-lg-block">
      <table class="table table-striped table-sm gf-budget-table mb-1">
        <thead>
          <tr>
            <th>Salvadanaio</th>
            <th class="gf-number">Pocket/mese</th>
            <th class="gf-number">Budget/mese</th>
            <th class="gf-number">Saldo reale</th>
            <th class="gf-number">Da finanziare*</th>
            
            <th>Previsione a scadenza</th>
            <th class="gf-number">13ª / 14ª</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rowsPocket as $d): ?>
          <tr>
            <th scope="row" class="gf-name">
              <a href="budget_anno.php?<?= http_build_query(['id_salvadanaio'=>(int)$d['id'],'scadenza_da'=>'']) ?>"><?= htmlspecialchars($d['nome'] ?? '') ?></a>
              <?php foreach ($d['viaggi'] as $v): ?>
                <a class="gf-trip ms-1" href="vacanze_workspace.php?id=<?= (int)$v['id_viaggio'] ?>" title="Apri vacanza: <?= htmlspecialchars($v['titolo'], ENT_QUOTES) ?>" aria-label="Apri vacanza <?= htmlspecialchars($v['titolo'],ENT_QUOTES) ?>"><i class="bi bi-airplane" aria-hidden="true"></i></a>
              <?php endforeach; ?>
            </th>
            <td class="gf-number"><?= number_format($d['pocket'],2,',','.') ?> €<?= $d['modalita']==='fisso' ? ' <span title="Accantonamento fisso">●</span>' : '' ?></td>
            <td class="gf-number"><?= number_format($d['consigliato'],2,',','.') ?> €</td>
            <td class="gf-number"><?= number_format((float)$d['attuale'],2,',','.') ?> €</td>
            <td class="gf-number"><?= number_format($d['da_accantonare'],2,',','.') ?> €</td>

            <td class="gf-status <?= $d['futuro_in_linea'] ? 'text-success' : 'text-warning' ?>" title="<?= $d['rischio_data'] ? 'Scadenza più critica: ' . htmlspecialchars(date('d/m/Y',strtotime($d['rischio_data'])),ENT_QUOTES) : 'Nessuna spesa con scadenza futura' ?>">
              <?php if ($d['rischio_data'] === null): ?>
                Nessuna scadenza
              <?php elseif (!$d['futuro_in_linea']): ?>
                Mancano <?= number_format($d['scoperto_futuro'],2,',','.') ?> €
              <?php elseif ($d['eccedenza_prevista'] >= 0.01): ?>
                Avanzo previsto +<?= number_format($d['eccedenza_prevista'],2,',','.') ?> €
              <?php else: ?>
                Coperto
              <?php endif; ?>
              <?php if ($d['rischio_data']): ?><span class="gf-muted">(<?= date('d/m/Y',strtotime($d['rischio_data'])) ?>)</span><?php endif; ?>
              <?php if ($d['eccedenza_attuale'] >= 0.01): ?>
                <div class="gf-muted" title="Saldo reale meno tutte le spese future del Pocket, senza contare nuovi versamenti o tredicesima/quattordicesima">Eccedenza oggi +<?= number_format($d['eccedenza_attuale'],2,',','.') ?> €</div>
              <?php endif; ?>
            </td>
            <td class="gf-number"><?= number_format($d['bonus13_futuro'],2,',','.') ?> € / <?= number_format($d['bonus14_futuro'],2,',','.') ?> €</td>

          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="fw-bold">
            <td>Totale</td>
            <td class="gf-number"><?= number_format($totalePocket,2,',','.') ?> €</td>
            <td class="gf-number"><?= number_format($totaleConsigliato,2,',','.') ?> €</td>
            <td class="gf-number"><?= number_format($totaleAttuale,2,',','.') ?> €</td>
            <td class="gf-number"><?= number_format(array_sum(array_column($rowsPocket, 'da_accantonare')),2,',','.') ?> €</td>
            <td colspan="3">I saldi dei diversi Pocket non sono compensabili tra loro.</td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div class="d-lg-none">
      <?php foreach ($rowsPocket as $d): ?>
      <article class="gf-budget-mobile-row">
        <div class="d-flex justify-content-between align-items-center">
          <a class="fw-semibold" href="budget_anno.php?<?= http_build_query(['id_salvadanaio'=>(int)$d['id'],'scadenza_da'=>'']) ?>"><?= htmlspecialchars($d['nome'] ?? '') ?></a>
          <?php foreach ($d['viaggi'] as $v): ?>
            <a class="ms-2" href="vacanze_workspace.php?id=<?= (int)$v['id_viaggio'] ?>" title="<?= htmlspecialchars($v['titolo'],ENT_QUOTES) ?>"><i class="bi bi-airplane"></i></a>
          <?php endforeach; ?>
        </div>
        <div class="d-flex justify-content-between small mt-1"><span>Pocket/mese</span><strong><?= number_format($d['pocket'],2,',','.') ?> €<?= $d['modalita']==='fisso' ? ' (fisso)' : '' ?></strong></div>
        <div class="d-flex justify-content-between small"><span>Budget/mese</span><strong><?= number_format($d['consigliato'],2,',','.') ?> €</strong></div>
        <div class="d-flex justify-content-between small"><span>Saldo accantonato</span><strong><?= number_format($d['attuale'],2,',','.') ?> €</strong></div>
        <div class="d-flex justify-content-between mt-1"><span>Da accantonare*</span><strong><?= number_format($d['da_accantonare'],2,',','.') ?> €</strong></div>
        <div class="d-flex justify-content-between"><span>Alla scadenza</span><strong class="<?= $d['futuro_in_linea'] ? 'text-success':'text-warning' ?>"><?= $d['rischio_data']===null ? 'Nessuna scadenza' : (!$d['futuro_in_linea'] ? 'Mancano '.number_format($d['scoperto_futuro'],2,',','.').' €' : ($d['eccedenza_prevista'] >= 0.01 ? 'Avanzo previsto +'.number_format($d['eccedenza_prevista'],2,',','.').' €' : 'Coperto')) ?></strong></div>
        <?php if ($d['eccedenza_attuale'] >= 0.01): ?><div class="small text-success">Eccedenza oggi +<?= number_format($d['eccedenza_attuale'],2,',','.') ?> €</div><?php endif; ?>
        <?php if ($d['bonus13_futuro'] > 0): ?><div class="small text-white-50">13ª attesa: <?= number_format($d['bonus13_futuro'],2,',','.') ?> € · 14ª attesa: <?= number_format($d['bonus14_futuro'],2,',','.') ?> €</div><?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div>
    <div class="small mb-3">*Da finanziare = spese programmate meno saldo attuale e contributi futuri da tredicesima e quattordicesima. Non considera ancora i prossimi versamenti mensili, che sono invece inclusi nella previsione a scadenza. Eccedenza oggi: saldo reale oltre tutte le spese future censite. Avanzo previsto: saldo residuo dopo spese e versamenti futuri, mostrato solo se tutte le scadenze intermedie sono coperte. Le rate future sono conteggiate dal prossimo giorno 1, la 13ª dal 21 dicembre e la 14ª dal 30 giugno.</div>
  </section>
  <div class="gf-desktop-panels">
    <section class="gf-panel" aria-label="Entrate mensili fisse">
      <h5 class="mb-1">Entrate mensili</h5>
      <table class="table table-striped table-sm gf-mini-table">
        <thead><tr><th>Voce</th><th class="text-end">€/mese</th></tr></thead>
        <tbody>
        <?php foreach ($entrateMensili as $r): ?>
          <tr><td><?= htmlspecialchars($r['descrizione'] ?? '') ?></td><td class="text-end"><?= number_format((float)$r['importo'],2,',','.') ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="fw-bold"><td>Totale entrate</td><td class="text-end"><?= number_format($totalEntrateMensili,2,',','.') ?> €</td></tr></tfoot>
      </table>
    </section>
    <section class="gf-panel" aria-label="Uscite mensili">
      <h5 class="mb-1">Uscite mensili</h5>
      <table class="table table-striped table-sm gf-mini-table">
        <thead><tr><th>Voce</th><th class="text-end">€/mese</th></tr></thead>
        <tbody>
        <?php foreach ($usciteMensili as $r): ?>
          <tr><td><?= htmlspecialchars($r['descrizione'] ?? '') ?></td><td class="text-end"><?= number_format((float)$r['importo'],2,',','.') ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="fw-bold"><td>Totale uscite fisse</td><td class="text-end"><?= number_format($totalUsciteMensili,2,',','.') ?> €</td></tr></tfoot>
      </table>
    </section>
    <section class="gf-panel" aria-label="Riepilogo reddito mensile">
      <h5 class="mb-1">Distribuzione del reddito</h5>
      <table class="table table-striped table-sm gf-mini-table">
        <tbody>
          <tr><td>Entrate mensili</td><td class="text-end"><?= number_format($totalEntrateMensili,2,',','.') ?> €</td></tr>
          <tr><td>Uscite mensili</td><td class="text-end">−<?= number_format($totalUsciteMensili,2,',','.') ?> €</td></tr>
          <tr><td>Accantonamenti Pocket</td><td class="text-end">−<?= number_format($totalePocket,2,',','.') ?> €</td></tr>
          <tr><td>Altre quote annuali</td><td class="text-end">−<?= number_format($altreQuoteAnnuali,2,',','.') ?> €</td></tr>
          <tr class="gf-summary-strong <?= $margineConPocket>=0 ? 'text-success':'text-warning' ?>"><td>Margine dopo Pocket</td><td class="text-end"><?= number_format($margineConPocket,2,',','.') ?> €</td></tr>
          <tr><td>Margine piano teorico</td><td class="text-end"><?= number_format($totalEntrateMensili-$totalUsciteMensili-$totalAnnualiMensile,2,',','.') ?> €</td></tr>
        </tbody>
      </table>
      <p class="small mb-0">Margine dopo Pocket: entrate meno spese mensili, versamenti Pocket impostati e quote annuali non associate a Pocket. Non include le assegnazioni straordinarie da 13ª/14ª come reddito ordinario.</p>
    </section>
  </div>

</main>
<?php include 'includes/footer.php'; ?>
