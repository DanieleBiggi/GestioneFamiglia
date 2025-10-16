<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$alt = (int)($_GET['alt'] ?? 0);

// Recupera info viaggio per breadcrumb
$stmt = $conn->prepare('SELECT titolo FROM viaggi WHERE id_viaggio=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$viaggio = $stmt->get_result()->fetch_assoc();
if (!$viaggio) {
    echo '<p class="text-danger">Viaggio non trovato</p>';
    include 'includes/footer.php';
    exit;
}

// Recupera alternativa
$altStmt = $conn->prepare('SELECT breve_descrizione FROM viaggi_alternative WHERE id_viaggio_alternativa=? AND id_viaggio=?');
$altStmt->bind_param('ii', $alt, $id);
$altStmt->execute();
$altRow = $altStmt->get_result()->fetch_assoc();
if (!$altRow) {
    echo '<p class="text-danger">Alternativa non trovata</p>';
    include 'includes/footer.php';
    exit;
}
$alt_desc = $altRow['breve_descrizione'];

// Recupera tratte
$trStmt = $conn->prepare('SELECT *, ((COALESCE(distanza_km,0)*COALESCE(consumo_litri_100km,0)/100)*COALESCE(prezzo_carburante_eur_litro,0) + COALESCE(pedaggi_eur,0) + COALESCE(costo_traghetto_eur,0) + COALESCE(costo_volo_eur,0) + COALESCE(costo_noleggio_eur,0) + COALESCE(altri_costi_eur,0)) AS totale FROM viaggi_tratte WHERE id_viaggio=? AND id_viaggio_alternativa=? ORDER BY id_tratta');
$trStmt->bind_param('ii', $id, $alt);
$trStmt->execute();
$trRes = $trStmt->get_result();
$tratte = $trRes->fetch_all(MYSQLI_ASSOC);

// Recupera alloggi
$allStmt = $conn->prepare('SELECT *, DATEDIFF(data_checkout, data_checkin) * COALESCE(costo_notte_eur,0) AS totale FROM viaggi_alloggi WHERE id_viaggio=? AND id_viaggio_alternativa=? ORDER BY id_alloggio');
$allStmt->bind_param('ii', $id, $alt);
$allStmt->execute();
$allRes = $allStmt->get_result();
$alloggi = $allRes->fetch_all(MYSQLI_ASSOC);

// Recupera pasti
$paStmt = $conn->prepare('SELECT * FROM viaggi_pasti WHERE id_viaggio=? AND id_viaggio_alternativa=? ORDER BY giorno_indice, id_pasto');
$paStmt->bind_param('ii', $id, $alt);
$paStmt->execute();
$paRes = $paStmt->get_result();
$pasti = $paRes->fetch_all(MYSQLI_ASSOC);

// Recupera altri costi
$coStmt = $conn->prepare('SELECT * FROM viaggi_altri_costi WHERE id_viaggio=? AND id_viaggio_alternativa=? ORDER BY data, id_costo');
$coStmt->bind_param('ii', $id, $alt);
$coStmt->execute();
$coRes = $coStmt->get_result();
$altri_costi = $coRes->fetch_all(MYSQLI_ASSOC);

$canEditAlt = has_permission($conn, 'ajax:update_viaggi_alternativa', 'update');
$canInsertTratta = has_permission($conn, 'table:viaggi_tratte', 'insert');
$canUpdateTratta = has_permission($conn, 'table:viaggi_tratte', 'update');
$canDeleteAlt = has_permission($conn, 'ajax:delete_viaggi_alternativa', 'delete');
$canInsertAlloggio = has_permission($conn, 'table:viaggi_alloggi', 'insert');
$canUpdateAlloggio = has_permission($conn, 'table:viaggi_alloggi', 'update');
$canInsertPasto = has_permission($conn, 'table:viaggi_pasti', 'insert');
$canUpdatePasto = has_permission($conn, 'table:viaggi_pasti', 'update');
$canInsertCosto = has_permission($conn, 'table:viaggi_altri_costi', 'insert');
$canUpdateCosto = has_permission($conn, 'table:viaggi_altri_costi', 'update');

function computeSummary(array $items, string $field): array {
    $total = 0.0;
    $paid = 0.0;
    foreach ($items as $item) {
        $amount = isset($item[$field]) ? (float)$item[$field] : 0.0;
        $amount = max(round($amount, 2), 0.0);
        $total += $amount;
        if (isset($item['pagato']) && (int)$item['pagato'] === 1) {
            $paid += $amount;
        }
    }
    $total = round($total, 2);
    $paid = round($paid, 2);
    $due = max(round($total - $paid, 2), 0.0);
    return ['total' => $total, 'paid' => $paid, 'due' => $due];
}

function combineSummaries(array $summaries): array {
    $total = 0.0;
    $paid = 0.0;
    foreach ($summaries as $summary) {
        $total += $summary['total'] ?? 0.0;
        $paid += $summary['paid'] ?? 0.0;
    }
    $total = round($total, 2);
    $paid = round($paid, 2);
    $due = max(round($total - $paid, 2), 0.0);
    return ['total' => $total, 'paid' => $paid, 'due' => $due];
}

function formatEuro($value): string {
    $amount = is_numeric($value) ? (float)$value : 0.0;
    return '€' . number_format($amount, 2, ',', '.');
}

function renderSummaryBlock(string $section, array $summary, ?string $title = null, string $classes = 'mb-4'): void {
    $total = $summary['total'] ?? 0.0;
    $paid = $summary['paid'] ?? 0.0;
    $due = $summary['due'] ?? 0.0;
    ?>
    <div class="card bg-dark border-secondary <?= htmlspecialchars($classes) ?>">
      <div class="card-body py-3">
        <?php if ($title !== null): ?>
          <div class="text-uppercase small text-muted mb-2"><?= htmlspecialchars($title) ?></div>
        <?php endif; ?>
        <div class="row text-center summary-block" data-section="<?= htmlspecialchars($section) ?>" data-total="<?= number_format($total, 2, '.', '') ?>" data-paid="<?= number_format($paid, 2, '.', '') ?>">
          <div class="col">
            <div class="text-uppercase small text-muted">Previsto</div>
            <div class="fs-5 summary-total"><?= formatEuro($total) ?></div>
          </div>
          <div class="col">
            <div class="text-uppercase small text-muted">Pagato</div>
            <div class="fs-5 summary-paid"><?= formatEuro($paid) ?></div>
          </div>
          <div class="col">
            <div class="text-uppercase small text-muted">Da pagare</div>
            <div class="fs-5 summary-due"><?= formatEuro($due) ?></div>
          </div>
        </div>
      </div>
    </div>
    <?php
}

$summaryTratte = computeSummary($tratte, 'totale');
$summaryAlloggi = computeSummary($alloggi, 'totale');
$summaryPasti = computeSummary($pasti, 'costo_medio_eur');
$summaryAltri = computeSummary($altri_costi, 'importo_eur');
$summaryOverall = combineSummaries([$summaryTratte, $summaryAlloggi, $summaryPasti, $summaryAltri]);
?>
<div class="container text-white">
  <a href="vacanze_view.php?id=<?= $id ?>" class="btn btn-outline-light mb-3">← Indietro</a>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="vacanze.php">Vacanze</a></li>
        <li class="breadcrumb-item"><a href="vacanze_view.php?id=<?= $id ?>"><?= htmlspecialchars($viaggio['titolo']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($alt_desc) ?></li>
    </ol>
  </nav>
  <?php renderSummaryBlock('overall', $summaryOverall, 'Totale costi previsti'); ?>
  <div class="d-flex justify-content-between mb-3">
      <h4 class="m-0">Tratte - <?= htmlspecialchars($alt_desc) ?>
        <?php if ($canEditAlt): ?>
          <a href="#" class="text-white ms-2" data-bs-toggle="modal" data-bs-target="#altEditModal"><i class="bi bi-pencil"></i></a>
        <?php endif; ?>
      </h4>
      <?php if ($canInsertTratta): ?>
      <a class="btn btn-sm btn-outline-light" href="vacanze_tratte_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>">Aggiungi</a>
      <?php endif; ?>
  </div>
  <?php renderSummaryBlock('tratte', $summaryTratte, 'Riepilogo tratte', 'mb-3'); ?>

  <?php if (empty($tratte)): ?>
    <p class="text-muted">Nessuna tratta.</p>
  <?php else: ?>
    <div class="list-group">
      <?php foreach ($tratte as $row): ?>
        <?php
          $trattaAmount = max((float)($row['totale'] ?? 0), 0.0);
          $trattaPaid = isset($row['pagato']) && (int)$row['pagato'] === 1;
          $trattaToggleId = 'payment-tratta-' . (int)$row['id_tratta'];
          $trattaAmountAttr = number_format($trattaAmount, 2, '.', '');
          $trattaLabelClass = $trattaPaid ? 'text-success' : 'text-warning';
        ?>
        <?php if ($canUpdateTratta): ?>
        <a href="vacanze_tratte_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>&id_tratta=<?= (int)$row['id_tratta'] ?>" class="list-group-item list-group-item-action bg-dark text-white">
        <?php else: ?>
        <div class="list-group-item bg-dark text-white">
        <?php endif; ?>
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
              <div><?= htmlspecialchars($row['descrizione'] ?: $row['tipo_tratta']) ?></div>
              <div class="small text-muted"><?= ucfirst($row['tipo_tratta']) ?></div>
            </div>
            <div class="text-end">
              <div><?= formatEuro($trattaAmount) ?>
                <?php if ($canUpdateTratta): ?><i class="bi bi-pencil ms-2"></i><?php endif; ?>
                <?php if ($canInsertTratta): ?><i class="bi bi-files ms-2 duplicate" data-href="vacanze_tratte_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>&id_tratta=<?= (int)$row['id_tratta'] ?>&duplica=1"></i><?php endif; ?>
              </div>
              <div class="small text-muted mb-1">
                <?php if ($row['tipo_tratta'] === 'auto'): ?>
                  <?php $carb = ($row['distanza_km'] ?? 0) * ($row['consumo_litri_100km'] ?? 0) / 100 * ($row['prezzo_carburante_eur_litro'] ?? 0); ?>
                  Carb <?= formatEuro($carb) ?>, Ped <?= formatEuro($row['pedaggi_eur'] ?? 0) ?>
                <?php else: ?>
                  <?php
                    $labelMap = ['aereo' => 'Volo', 'traghetto' => 'Traghetto', 'treno' => 'Treno'];
                    $costMap = [
                      'aereo' => $row['costo_volo_eur'] ?? 0,
                      'traghetto' => $row['costo_traghetto_eur'] ?? 0,
                      'treno' => $row['altri_costi_eur'] ?? 0,
                    ];
                    $mezzoLabel = $labelMap[$row['tipo_tratta']] ?? 'Mezzo';
                    $mezzoCosto = $costMap[$row['tipo_tratta']] ?? ($row['altri_costi_eur'] ?? 0);
                  ?>
                  <?= $mezzoLabel ?> <?= formatEuro($mezzoCosto) ?>
                <?php endif; ?>
              </div>
              <div class="form-check form-switch form-check-reverse d-inline-flex align-items-center gap-2 small">
                <input class="form-check-input payment-toggle" type="checkbox" role="switch" id="<?= $trattaToggleId ?>"
                  data-table="viaggi_tratte" data-id="<?= (int)$row['id_tratta'] ?>" data-section="tratte" data-amount="<?= $trattaAmountAttr ?>"
                  <?= $trattaPaid ? 'checked' : '' ?><?= $canUpdateTratta ? '' : ' disabled' ?>>
                <label class="form-check-label payment-label <?= $trattaLabelClass ?>" for="<?= $trattaToggleId ?>" data-paid-label="Pagata" data-unpaid-label="Da pagare">
                  <?= $trattaPaid ? 'Pagata' : 'Da pagare' ?>
                </label>
              </div>
            </div>
          </div>
        <?php if ($canUpdateTratta): ?>
        </a>
        <?php else: ?>
        </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between mb-3 mt-4">
      <h4 class="m-0">Alloggi</h4>
      <?php if ($canInsertAlloggio): ?>
      <a class="btn btn-sm btn-outline-light" href="vacanze_alloggi_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>">Aggiungi</a>
      <?php endif; ?>
  </div>
  <?php renderSummaryBlock('alloggi', $summaryAlloggi, 'Riepilogo alloggi', 'mb-3'); ?>

  <?php if (empty($alloggi)): ?>
    <p class="text-muted">Nessun alloggio.</p>
  <?php else: ?>
    <div class="list-group">
      <?php foreach ($alloggi as $row): ?>
        <?php
          $alloggioAmount = max((float)($row['totale'] ?? 0), 0.0);
          $alloggioPaid = isset($row['pagato']) && (int)$row['pagato'] === 1;
          $alloggioToggleId = 'payment-alloggio-' . (int)$row['id_alloggio'];
          $alloggioAmountAttr = number_format($alloggioAmount, 2, '.', '');
          $alloggioLabelClass = $alloggioPaid ? 'text-success' : 'text-warning';
        ?>
        <?php if ($canUpdateAlloggio): ?>
        <a href="vacanze_alloggi_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>&id_alloggio=<?= (int)$row['id_alloggio'] ?>" class="list-group-item list-group-item-action bg-dark text-white">
        <?php else: ?>
        <div class="list-group-item bg-dark text-white">
        <?php endif; ?>
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
              <div><?= htmlspecialchars($row['nome_alloggio'] ?: 'Alloggio') ?></div>
              <?php if (!empty($row['data_checkin']) || !empty($row['data_checkout'])): ?>
                <div class="small text-muted">
                  <?php if (!empty($row['data_checkin'])): ?>Check-in <?= htmlspecialchars($row['data_checkin']) ?><?php endif; ?>
                  <?php if (!empty($row['data_checkout'])): ?><?= !empty($row['data_checkin']) ? ' · ' : '' ?>Check-out <?= htmlspecialchars($row['data_checkout']) ?><?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="text-end">
              <div><?= formatEuro($alloggioAmount) ?>
                <?php if ($canUpdateAlloggio): ?><i class="bi bi-pencil ms-2"></i><?php endif; ?>
                <?php if ($canInsertAlloggio): ?><i class="bi bi-files ms-2 duplicate" data-href="vacanze_alloggi_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>&id_alloggio=<?= (int)$row['id_alloggio'] ?>&duplica=1"></i><?php endif; ?>
              </div>
              <div class="form-check form-switch form-check-reverse d-inline-flex align-items-center gap-2 small mt-1">
                <input class="form-check-input payment-toggle" type="checkbox" role="switch" id="<?= $alloggioToggleId ?>"
                  data-table="viaggi_alloggi" data-id="<?= (int)$row['id_alloggio'] ?>" data-section="alloggi" data-amount="<?= $alloggioAmountAttr ?>"
                  <?= $alloggioPaid ? 'checked' : '' ?><?= $canUpdateAlloggio ? '' : ' disabled' ?>>
                <label class="form-check-label payment-label <?= $alloggioLabelClass ?>" for="<?= $alloggioToggleId ?>" data-paid-label="Pagato" data-unpaid-label="Da pagare">
                  <?= $alloggioPaid ? 'Pagato' : 'Da pagare' ?>
                </label>
              </div>
            </div>
          </div>
        <?php if ($canUpdateAlloggio): ?>
        </a>
        <?php else: ?>
        </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between mb-3 mt-4">
      <h4 class="m-0">Pasti</h4>
      <?php if ($canInsertPasto): ?>
      <a class="btn btn-sm btn-outline-light" href="vacanze_pasti_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>">Aggiungi</a>
      <?php endif; ?>
  </div>
  <?php renderSummaryBlock('pasti', $summaryPasti, 'Riepilogo pasti', 'mb-3'); ?>

  <?php if (empty($pasti)): ?>
    <p class="text-muted">Nessun pasto.</p>
  <?php else: ?>
    <div class="list-group">
      <?php foreach ($pasti as $row): ?>
        <?php
          $pastoAmount = max((float)($row['costo_medio_eur'] ?? 0), 0.0);
          $pastoPaid = isset($row['pagato']) && (int)$row['pagato'] === 1;
          $pastoToggleId = 'payment-pasto-' . (int)$row['id_pasto'];
          $pastoAmountAttr = number_format($pastoAmount, 2, '.', '');
          $pastoLabelClass = $pastoPaid ? 'text-success' : 'text-warning';
        ?>
        <?php if ($canUpdatePasto): ?>
        <a href="vacanze_pasti_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>&id_pasto=<?= (int)$row['id_pasto'] ?>" class="list-group-item list-group-item-action bg-dark text-white">
        <?php else: ?>
        <div class="list-group-item bg-dark text-white">
        <?php endif; ?>
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
              <div><?= htmlspecialchars($row['nome_locale'] ?: ucfirst($row['tipologia'])) ?></div>
              <div class="small text-muted">Giorno <?= (int)($row['giorno_indice'] ?? 0) ?> - <?= ucfirst($row['tipo_pasto']) ?> - <?= ($row['tipologia'] === 'cucinato') ? 'Preparato' : 'Ristorante' ?></div>
            </div>
            <div class="text-end">
              <div><?= formatEuro($pastoAmount) ?>
                <?php if ($canUpdatePasto): ?><i class="bi bi-pencil ms-2"></i><?php endif; ?>
                <?php if ($canInsertPasto): ?><i class="bi bi-files ms-2 duplicate" data-href="vacanze_pasti_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>&id_pasto=<?= (int)$row['id_pasto'] ?>&duplica=1"></i><?php endif; ?>
              </div>
              <div class="form-check form-switch form-check-reverse d-inline-flex align-items-center gap-2 small mt-1">
                <input class="form-check-input payment-toggle" type="checkbox" role="switch" id="<?= $pastoToggleId ?>"
                  data-table="viaggi_pasti" data-id="<?= (int)$row['id_pasto'] ?>" data-section="pasti" data-amount="<?= $pastoAmountAttr ?>"
                  <?= $pastoPaid ? 'checked' : '' ?><?= $canUpdatePasto ? '' : ' disabled' ?>>
                <label class="form-check-label payment-label <?= $pastoLabelClass ?>" for="<?= $pastoToggleId ?>" data-paid-label="Pagato" data-unpaid-label="Da pagare">
                  <?= $pastoPaid ? 'Pagato' : 'Da pagare' ?>
                </label>
              </div>
            </div>
          </div>
        <?php if ($canUpdatePasto): ?>
        </a>
        <?php else: ?>
        </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between mb-3 mt-4">
      <h4 class="m-0">Altri costi</h4>
      <?php if ($canInsertCosto): ?>
      <a class="btn btn-sm btn-outline-light" href="vacanze_altri_costi_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>">Aggiungi</a>
      <?php endif; ?>
  </div>
  <?php renderSummaryBlock('altri', $summaryAltri, 'Riepilogo altri costi', 'mb-3'); ?>

  <?php if (empty($altri_costi)): ?>
    <p class="text-muted">Nessun costo.</p>
  <?php else: ?>
    <div class="list-group">
      <?php foreach ($altri_costi as $row): ?>
        <?php
          $costoAmount = max((float)($row['importo_eur'] ?? 0), 0.0);
          $costoPaid = isset($row['pagato']) && (int)$row['pagato'] === 1;
          $costoToggleId = 'payment-costo-' . (int)$row['id_costo'];
          $costoAmountAttr = number_format($costoAmount, 2, '.', '');
          $costoLabelClass = $costoPaid ? 'text-success' : 'text-warning';
        ?>
        <?php if ($canUpdateCosto): ?>
        <a href="vacanze_altri_costi_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>&id_costo=<?= (int)$row['id_costo'] ?>" class="list-group-item list-group-item-action bg-dark text-white">
        <?php else: ?>
        <div class="list-group-item bg-dark text-white">
        <?php endif; ?>
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
              <div><?= htmlspecialchars($row['descrizione'] ?: 'Costo') ?></div>
              <?php if (!empty($row['data'])): ?><div class="small text-muted"><?= htmlspecialchars($row['data']) ?></div><?php endif; ?>
            </div>
            <div class="text-end">
              <div><?= formatEuro($costoAmount) ?>
                <?php if ($canUpdateCosto): ?><i class="bi bi-pencil ms-2"></i><?php endif; ?>
                <?php if ($canInsertCosto): ?><i class="bi bi-files ms-2 duplicate" data-href="vacanze_altri_costi_dettaglio.php?id=<?= $id ?>&alt=<?= $alt ?>&id_costo=<?= (int)$row['id_costo'] ?>&duplica=1"></i><?php endif; ?>
              </div>
              <div class="form-check form-switch form-check-reverse d-inline-flex align-items-center gap-2 small mt-1">
                <input class="form-check-input payment-toggle" type="checkbox" role="switch" id="<?= $costoToggleId ?>"
                  data-table="viaggi_altri_costi" data-id="<?= (int)$row['id_costo'] ?>" data-section="altri" data-amount="<?= $costoAmountAttr ?>"
                  <?= $costoPaid ? 'checked' : '' ?><?= $canUpdateCosto ? '' : ' disabled' ?>>
                <label class="form-check-label payment-label <?= $costoLabelClass ?>" for="<?= $costoToggleId ?>" data-paid-label="Pagato" data-unpaid-label="Da pagare">
                  <?= $costoPaid ? 'Pagato' : 'Da pagare' ?>
                </label>
              </div>
            </div>
          </div>
        <?php if ($canUpdateCosto): ?>
        </a>
        <?php else: ?>
        </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h4 class="mb-3 mt-4">Mappa</h4>
  <div id="map" style="height:500px"></div>

  <?php if ($canDeleteAlt): ?>
  <div class="mt-4">
    <button type="button" id="deleteAltBtn" class="btn btn-danger w-100">
      <i class="bi bi-trash me-1"></i> Elimina alternativa
    </button>
  </div>
  <?php endif; ?>

  <?php if ($canEditAlt): ?>
  <div class="modal fade" id="altEditModal" tabindex="-1">
    <div class="modal-dialog">
      <form class="modal-content" id="altEditForm">
        <div class="modal-header">
          <h5 class="modal-title">Modifica alternativa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Descrizione breve</label>
            <input type="text" name="breve_descrizione" class="form-control" value="<?= htmlspecialchars($alt_desc) ?>" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary">Salva</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <script>
    const viaggioId = <?= $id ?>;
    const altId = <?= $alt ?>;
    const alloggi = <?= json_encode($alloggi) ?>;
    const tratte = <?= json_encode($tratte) ?>;
    const pasti = <?= json_encode($pasti) ?>;
    const altriCosti = <?= json_encode($altri_costi) ?>;
  </script>
  <script src="js/vacanze_tratte.js"></script>
  <script src="https://maps.googleapis.com/maps/api/js?key=<?= $config['GOOGLE_MAPS_API'] ?? '' ?>&callback=initMap&loading=async" async defer></script>
</div>
<?php include 'includes/footer.php'; ?>
