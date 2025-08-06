<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/render_movimento.php';
include 'includes/header.php';
setlocale(LC_TIME, 'it_IT.UTF-8');

$etichetta = $_GET['etichetta'] ?? '';
$mese = $_GET['mese'] ?? '';
$categoria = $_GET['categoria'] ?? '';

if ($etichetta === '') {
    echo '<p class="text-center text-muted">Nessuna etichetta selezionata.</p>';
    include 'includes/footer.php';
    return;
}

// Lista mesi disponibili per questa etichetta
$mesi = [];
$sqlM = "SELECT DATE_FORMAT(data_operazione,'%Y-%m') AS ym, DATE_FORMAT(data_operazione,'%M %Y') AS label
          FROM (
            SELECT started_date AS data_operazione, etichette FROM v_movimenti_revolut
            UNION ALL
            SELECT be.data_operazione,
                   (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette
            FROM bilancio_entrate be
            UNION ALL
            SELECT bu.data_operazione,
                   (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = bu.id_uscita AND eo.tabella_operazione='bilancio_uscite') AS etichette
            FROM bilancio_uscite bu
          ) t
          WHERE FIND_IN_SET(?, etichette)
          GROUP BY ym ORDER BY ym DESC";
$stmtM = $conn->prepare($sqlM);
$stmtM->bind_param('s', $etichetta);
$stmtM->execute();
$resM = $stmtM->get_result();
while ($row = $resM->fetch_assoc()) {
    $mesi[] = $row;
}
$stmtM->close();

// Calcolo dei totali entrate/uscite
if ($mese !== '') {
    $sqlTot = "SELECT SUM(CASE WHEN amount>=0 THEN amount ELSE 0 END) AS entrate,
                        SUM(CASE WHEN amount<0 THEN amount ELSE 0 END) AS uscite
                 FROM (
                    SELECT amount, started_date AS data_operazione, etichette FROM v_movimenti_revolut
                    UNION ALL
                    SELECT importo AS amount, data_operazione,
                           (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                              FROM bilancio_etichette2operazioni eo
                              JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                             WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette
                    FROM bilancio_entrate be
                    UNION ALL
                    SELECT -importo AS amount, data_operazione,
                           (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                              FROM bilancio_etichette2operazioni eo
                              JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                             WHERE eo.id_tabella = bu.id_uscita AND eo.tabella_operazione='bilancio_uscite') AS etichette
                    FROM bilancio_uscite bu
                 ) t
                 WHERE FIND_IN_SET(?, etichette) AND DATE_FORMAT(data_operazione,'%Y-%m')=?";
    $stmtTot = $conn->prepare($sqlTot);
    $stmtTot->bind_param('ss', $etichetta, $mese);
} else {
    $sqlTot = "SELECT SUM(CASE WHEN amount>=0 THEN amount ELSE 0 END) AS entrate,
                        SUM(CASE WHEN amount<0 THEN amount ELSE 0 END) AS uscite
                 FROM (
                    SELECT amount, started_date AS data_operazione, etichette FROM v_movimenti_revolut
                    UNION ALL
                    SELECT importo AS amount, data_operazione,
                           (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                              FROM bilancio_etichette2operazioni eo
                              JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                             WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette
                    FROM bilancio_entrate be
                    UNION ALL
                    SELECT -importo AS amount, data_operazione,
                           (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                              FROM bilancio_etichette2operazioni eo
                              JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                             WHERE eo.id_tabella = bu.id_uscita AND eo.tabella_operazione='bilancio_uscite') AS etichette
                    FROM bilancio_uscite bu
                 ) t
                 WHERE FIND_IN_SET(?, etichette)";
    $stmtTot = $conn->prepare($sqlTot);
    $stmtTot->bind_param('s', $etichetta);
}
$stmtTot->execute();
$totali = $stmtTot->get_result()->fetch_assoc();
$stmtTot->close();

// Movimenti dell'etichetta
if ($mese !== '') {
    $sqlMov = "SELECT * FROM (
                SELECT id_movimento_revolut AS id, COALESCE(NULLIF(descrizione_extra,''), description) AS descrizione,
                       descrizione_extra, started_date AS data_operazione, amount,
                       etichette, id_gruppo_transazione, 'revolut' AS source, 'movimenti_revolut' AS tabella
                FROM v_movimenti_revolut
                UNION ALL
                SELECT be.id_entrata AS id, be.descrizione_operazione AS descrizione, be.descrizione_extra,
                       be.data_operazione, be.importo AS amount,
                       (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                          FROM bilancio_etichette2operazioni eo
                          JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                         WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette,
                       be.id_gruppo_transazione, 'ca' AS source, 'bilancio_entrate' AS tabella
                FROM bilancio_entrate be
                UNION ALL
                SELECT bu.id_uscita AS id, bu.descrizione_operazione AS descrizione, bu.descrizione_extra,
                       bu.data_operazione, -bu.importo AS amount,
                       (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                          FROM bilancio_etichette2operazioni eo
                          JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                         WHERE eo.id_tabella = bu.id_uscita AND eo.tabella_operazione='bilancio_uscite') AS etichette,
                       bu.id_gruppo_transazione, 'ca' AS source, 'bilancio_uscite' AS tabella
                FROM bilancio_uscite bu
              ) t
              WHERE FIND_IN_SET(?, etichette) AND DATE_FORMAT(data_operazione,'%Y-%m')=?
              ORDER BY data_operazione DESC";
    $stmtMov = $conn->prepare($sqlMov);
    $stmtMov->bind_param('ss', $etichetta, $mese);
} else {
    $sqlMov = "SELECT * FROM (
                SELECT id_movimento_revolut AS id, COALESCE(NULLIF(descrizione_extra,''), description) AS descrizione,
                       descrizione_extra, started_date AS data_operazione, amount,
                       etichette, id_gruppo_transazione, 'revolut' AS source, 'movimenti_revolut' AS tabella
                FROM v_movimenti_revolut
                UNION ALL
                SELECT be.id_entrata AS id, be.descrizione_operazione AS descrizione, be.descrizione_extra,
                       be.data_operazione, be.importo AS amount,
                       (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                          FROM bilancio_etichette2operazioni eo
                          JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                         WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette,
                       be.id_gruppo_transazione, 'ca' AS source, 'bilancio_entrate' AS tabella
                FROM bilancio_entrate be
                UNION ALL
                SELECT bu.id_uscita AS id, bu.descrizione_operazione AS descrizione, bu.descrizione_extra,
                       bu.data_operazione, -bu.importo AS amount,
                       (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                          FROM bilancio_etichette2operazioni eo
                          JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                         WHERE eo.id_tabella = bu.id_uscita AND eo.tabella_operazione='bilancio_uscite') AS etichette,
                       bu.id_gruppo_transazione, 'ca' AS source, 'bilancio_uscite' AS tabella
                FROM bilancio_uscite bu
              ) t
              WHERE FIND_IN_SET(?, etichette)
              ORDER BY data_operazione DESC";
    $stmtMov = $conn->prepare($sqlMov);
    $stmtMov->bind_param('s', $etichetta);
}
// Categoria per filtro gruppi
$categorie = [];
$resCat = $conn->query("SELECT id_categoria, descrizione_categoria FROM bilancio_gruppi_categorie ORDER BY descrizione_categoria");
while ($rc = $resCat->fetch_assoc()) {
    $categorie[] = $rc;
}

$stmtMov->execute();
$movimenti = $stmtMov->get_result();
$stmtMov->close();

function tipo_label($t) {
    return [
        'spese_base' => 'Spese Base',
        'divertimento' => 'Divertimento',
        'risparmio' => 'Risparmio',
        '' => 'Altro'
    ][$t] ?? $t;
}

// Dettaglio per gruppo
$sqlGrp = "SELECT m.id_gruppo_transazione, g.descrizione AS gruppo, g.tipo_gruppo,
                  COALESCE(c.descrizione_categoria, 'Nessuna categoria') AS categoria,
                  SUM(CASE WHEN m.amount>=0 THEN m.amount ELSE 0 END) AS entrate,
                  SUM(CASE WHEN m.amount<0 THEN m.amount ELSE 0 END) AS uscite
           FROM (
                SELECT id_gruppo_transazione, amount, started_date AS data_operazione, etichette FROM v_movimenti_revolut
                UNION ALL
                SELECT be.id_gruppo_transazione, be.importo AS amount, be.data_operazione,
                       (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                          FROM bilancio_etichette2operazioni eo
                          JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                         WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette
                FROM bilancio_entrate be
                UNION ALL
                SELECT bu.id_gruppo_transazione, -bu.importo AS amount, bu.data_operazione,
                       (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                          FROM bilancio_etichette2operazioni eo
                          JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                         WHERE eo.id_tabella = bu.id_uscita AND eo.tabella_operazione='bilancio_uscite') AS etichette
                FROM bilancio_uscite bu
           ) m
           LEFT JOIN bilancio_gruppi_transazione g ON m.id_gruppo_transazione = g.id_gruppo_transazione
           LEFT JOIN bilancio_gruppi_categorie c ON g.id_categoria = c.id_categoria
           WHERE FIND_IN_SET(?, m.etichette)";
if ($mese !== '') {
    $sqlGrp .= " AND DATE_FORMAT(m.data_operazione,'%Y-%m')=?";
}
if ($categoria !== '') {
    if ($categoria === '0') {
        $sqlGrp .= " AND g.id_categoria IS NULL";
    } else {
        $sqlGrp .= " AND g.id_categoria = ?";
    }
}
$sqlGrp .= " GROUP BY m.id_gruppo_transazione, g.descrizione, g.tipo_gruppo, categoria ORDER BY categoria, g.descrizione";

if ($mese !== '' && $categoria !== '' && $categoria !== '0') {
    $stmtGrp = $conn->prepare($sqlGrp);
    $stmtGrp->bind_param('ssi', $etichetta, $mese, $categoria);
} elseif ($mese !== '' && $categoria === '0') {
    $stmtGrp = $conn->prepare($sqlGrp);
    $stmtGrp->bind_param('ss', $etichetta, $mese);
} elseif ($mese !== '' && $categoria === '') {
    $stmtGrp = $conn->prepare($sqlGrp);
    $stmtGrp->bind_param('ss', $etichetta, $mese);
} elseif ($mese === '' && $categoria !== '' && $categoria !== '0') {
    $stmtGrp = $conn->prepare($sqlGrp);
    $stmtGrp->bind_param('si', $etichetta, $categoria);
} else {
    $stmtGrp = $conn->prepare($sqlGrp);
    $stmtGrp->bind_param('s', $etichetta);
}

$stmtGrp->execute();
$resGrp = $stmtGrp->get_result();
$gruppi = [];
while ($r = $resGrp->fetch_assoc()) {
    $r['categoria'] = $r['categoria'] ?? 'Nessuna categoria';
    $r['tipo_label'] = tipo_label($r['tipo_gruppo']);
    $gruppi[] = $r;
}
$stmtGrp->close();
?>

<div class="text-white">
  <h4 class="mb-3">Movimenti per etichetta: <?= htmlspecialchars($etichetta) ?></h4>

  <form method="get" class="mb-3">
    <input type="hidden" name="etichetta" value="<?= htmlspecialchars($etichetta) ?>">
    <div class="d-flex gap-2 align-items-center">
      <label for="mese" class="form-label mb-0">Mese:</label>
      <select name="mese" id="mese" class="form-select w-auto" onchange="this.form.submit()">
        <option value="" <?= $mese === '' ? 'selected' : '' ?>>Tutti i mesi</option>
        <?php foreach ($mesi as $m): ?>
          <option value="<?= htmlspecialchars($m['ym']) ?>" <?= $mese === $m['ym'] ? 'selected' : '' ?>><?= ucfirst($m['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <div class="d-flex gap-4 mb-4">
    <div>Entrate: <span><?= '+' . number_format($totali['entrate'] ?? 0, 2, ',', '.') ?> €</span></div>
    <div>Uscite: <span><?= number_format($totali['uscite'] ?? 0, 2, ',', '.') ?> €</span></div>
  </div>

  <?php if ($movimenti->num_rows > 0): ?>
    <?php while ($mov = $movimenti->fetch_assoc()): ?>
      <?php render_movimento($mov); ?>
    <?php endwhile; ?>
  <?php else: ?>
    <p class="text-center text-muted">Nessun movimento per questa etichetta.</p>
  <?php endif; ?>

  <?php if (!empty($gruppi)): ?>
    <h5 class="mt-4">Dettaglio per gruppo</h5>
    <form method="get" class="mb-3">
      <input type="hidden" name="etichetta" value="<?= htmlspecialchars($etichetta) ?>">
      <input type="hidden" name="mese" value="<?= htmlspecialchars($mese) ?>">
      <div class="d-flex gap-2 align-items-center">
        <label for="categoria" class="form-label mb-0">Categoria:</label>
        <select name="categoria" id="categoria" class="form-select w-auto" onchange="this.form.submit()">
          <option value="" <?= $categoria === '' ? 'selected' : '' ?>>Tutte</option>
          <option value="0" <?= $categoria === '0' ? 'selected' : '' ?>>Nessuna categoria</option>
          <?php foreach ($categorie as $cat): ?>
            <option value="<?= $cat['id_categoria'] ?>" <?= $categoria == $cat['id_categoria'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['descrizione_categoria']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
    <div class="table-responsive">
      <table class="table table-dark table-striped align-middle">
        <thead>
          <tr>
            <th>Categoria</th>
            <th>Gruppo</th>
            <th>Tipo</th>
            <th class="text-end">Entrate</th>
            <th class="text-end">Uscite</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($gruppi as $g): ?>
            <tr>
              <td><?= htmlspecialchars($g['categoria']) ?></td>
              <td><?= htmlspecialchars($g['gruppo'] ?? $g['id_gruppo_transazione']) ?></td>
              <td><?= htmlspecialchars($g['tipo_label']) ?></td>
              <td class="text-end"><?= ($g['entrate'] > 0 ? '+' : '') . number_format($g['entrate'], 2, ',', '.') ?> €</td>
              <td class="text-end"><?= number_format($g['uscite'], 2, ',', '.') ?> €</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

