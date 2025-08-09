<?php include 'includes/session_check.php'; ?>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/db.php';
require_once 'includes/render_movimento_etichetta.php';
require_once 'includes/etichette_utils.php';
include 'includes/header.php';
setlocale(LC_TIME, 'it_IT.UTF-8');

$etichettaParam = $_GET['id_etichetta'] ?? '';
$mese = $_GET['mese'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$idUtente = $_SESSION['utente_id'] ?? ($_SESSION['id_utente'] ?? 0);
$isAdmin = ($idUtente == 1);
$etichettaInfo = null;

if ($etichettaParam === '') {
    echo '<p class="text-center text-muted">Nessuna etichetta selezionata.</p>';
    include 'includes/footer.php';
    return;
}


$stmtEt = $conn->prepare("SELECT id_etichetta, descrizione, attivo, da_dividere, utenti_tra_cui_dividere FROM bilancio_etichette WHERE id_etichetta = ?");
$stmtEt->bind_param('s', $etichettaParam);
$stmtEt->execute();
$etichettaInfo = $stmtEt->get_result()->fetch_assoc();
$stmtEt->close();

if (!$etichettaInfo) {

    echo '<p class="text-center text-muted">Etichetta non trovata.</p>';
    include 'includes/footer.php';
    return;
}


// Recupera utenti attivi della famiglia corrente per la selezione
$listaUtenti = [];
$famigliaId = $_SESSION['id_famiglia_gestione'] ?? 0;
$stmtUt = $conn->prepare('SELECT u.id, u.nome, u.cognome FROM utenti u WHERE u.attivo = 1 ORDER BY u.nome');
//$stmtUt->bind_param('i', $famigliaId);
$stmtUt->execute();
$resUt = $stmtUt->get_result();
while ($row = $resUt->fetch_assoc()) {
    $listaUtenti[] = $row;
}
$stmtUt->close();

// Lista mesi disponibili per questa etichetta
$mesi = [];
$sqlM = "SELECT DATE_FORMAT(data_operazione,'%Y-%m') AS ym, DATE_FORMAT(data_operazione,'%M %Y') AS label
          FROM (
            SELECT bm.started_date as data_operazione,
                   (SELECT GROUP_CONCAT(e.id_etichetta SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = bm.id_movimento_revolut AND eo.tabella_operazione='movimenti_revolut') AS etichette
            FROM movimenti_revolut bm
            UNION ALL
            SELECT be.data_operazione,
                   (SELECT GROUP_CONCAT(e.id_etichetta SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette
            FROM bilancio_entrate be
            UNION ALL
            SELECT bu.data_operazione,
                   (SELECT GROUP_CONCAT(e.id_etichetta SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = bu.id_uscita AND eo.tabella_operazione='bilancio_uscite') AS etichette
            FROM bilancio_uscite bu
          ) t
          WHERE FIND_IN_SET(?, etichette)
          GROUP BY ym ORDER BY ym DESC";
$stmtM = $conn->prepare($sqlM);
$stmtM->bind_param('s', $etichettaInfo['id_etichetta']);
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
                    SELECT amount, bm.started_date as data_operazione, 
                           (SELECT GROUP_CONCAT(e.id_etichetta SEPARATOR ',')
                              FROM bilancio_etichette2operazioni eo
                              JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                             WHERE eo.id_tabella = bm.id_movimento_revolut AND eo.tabella_operazione='movimenti_revolut') AS etichette
                    FROM movimenti_revolut bm
                    UNION ALL
                    SELECT importo AS amount, data_operazione,
                           (SELECT GROUP_CONCAT(e.id_etichetta SEPARATOR ',')
                              FROM bilancio_etichette2operazioni eo
                              JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                             WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette
                    FROM bilancio_entrate be
                    UNION ALL
                    SELECT -importo AS amount, data_operazione,
                           (SELECT GROUP_CONCAT(e.id_etichetta SEPARATOR ',')
                              FROM bilancio_etichette2operazioni eo
                              JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                             WHERE eo.id_tabella = bu.id_uscita AND eo.tabella_operazione='bilancio_uscite') AS etichette
                    FROM bilancio_uscite bu
                 ) t
                 WHERE FIND_IN_SET(?, etichette) AND DATE_FORMAT(data_operazione,'%Y-%m')=?";
    $stmtTot = $conn->prepare($sqlTot);
    $stmtTot->bind_param('ss', $etichettaInfo['id_etichetta'], $mese);
} else {
    $sqlTot = "SELECT SUM(CASE WHEN amount>=0 THEN amount ELSE 0 END) AS entrate,
                        SUM(CASE WHEN amount<0 THEN amount ELSE 0 END) AS uscite
                 FROM (
                    SELECT amount, bm.started_date as data_operazione, 
                           (SELECT GROUP_CONCAT(e.id_etichetta SEPARATOR ',')
                              FROM bilancio_etichette2operazioni eo
                              JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                             WHERE eo.id_tabella = bm.id_movimento_revolut AND eo.tabella_operazione='movimenti_revolut') AS etichette
                    FROM movimenti_revolut bm
                    UNION ALL
                    SELECT importo AS amount, data_operazione,
                           (SELECT GROUP_CONCAT(e.id_etichetta SEPARATOR ',')
                              FROM bilancio_etichette2operazioni eo
                              JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                             WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette
                    FROM bilancio_entrate be
                    UNION ALL
                    SELECT -importo AS amount, data_operazione,
                           (SELECT GROUP_CONCAT(e.id_etichetta SEPARATOR ',')
                              FROM bilancio_etichette2operazioni eo
                              JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                             WHERE eo.id_tabella = bu.id_uscita AND eo.tabella_operazione='bilancio_uscite') AS etichette
                    FROM bilancio_uscite bu
                 ) t
                 WHERE FIND_IN_SET(?, etichette)";
    $stmtTot = $conn->prepare($sqlTot);
    $stmtTot->bind_param('s', $etichettaInfo['id_etichetta']);
}
$stmtTot->execute();
$totali = $stmtTot->get_result()->fetch_assoc();
$stmtTot->close();

// Movimenti dell'etichetta
  // Movimenti dell'etichetta con id_e2o
  $sqlMov = "SELECT m.*, e2o.id_e2o
             FROM (
                  SELECT id_movimento_revolut AS id, COALESCE(NULLIF(descrizione_extra,''), description) AS descrizione,
                         descrizione_extra, started_date AS data_operazione, amount,
                         (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                            FROM bilancio_etichette2operazioni eo
                            JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                           WHERE eo.id_tabella = v.id_movimento_revolut AND eo.tabella_operazione='movimenti_revolut') AS etichette,
                         id_gruppo_transazione, 'revolut' AS source, 'movimenti_revolut' AS tabella
                  FROM v_movimenti_revolut v
                  UNION ALL
                  SELECT be.id_entrata AS id, COALESCE(NULLIF(be.descrizione_extra,''), be.descrizione_operazione) AS descrizione, be.descrizione_extra,
                         be.data_operazione, be.importo AS amount,
                         (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                            FROM bilancio_etichette2operazioni eo
                            JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                           WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette,
                         be.id_gruppo_transazione, 'ca' AS source, 'bilancio_entrate' AS tabella
                  FROM bilancio_entrate be
                  UNION ALL
                  SELECT bu.id_uscita AS id, COALESCE(NULLIF(bu.descrizione_extra,''), bu.descrizione_operazione) AS descrizione, bu.descrizione_extra,
                         bu.data_operazione, -bu.importo AS amount,
                         (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                            FROM bilancio_etichette2operazioni eo
                            JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                           WHERE eo.id_tabella = bu.id_uscita AND eo.tabella_operazione='bilancio_uscite') AS etichette,
                         bu.id_gruppo_transazione, 'ca' AS source, 'bilancio_uscite' AS tabella
                  FROM bilancio_uscite bu
             ) m
             JOIN bilancio_etichette2operazioni e2o ON e2o.id_tabella = m.id AND e2o.tabella_operazione = m.tabella
             JOIN bilancio_etichette e ON e.id_etichetta = e2o.id_etichetta
             WHERE e.id_etichetta = ?";
  if ($mese !== '') {
      $sqlMov .= " AND DATE_FORMAT(m.data_operazione,'%Y-%m')=?";
      $sqlMov .= " ORDER BY m.data_operazione DESC";
      $stmtMov = $conn->prepare($sqlMov);
      $stmtMov->bind_param('ss', $etichettaInfo['id_etichetta'], $mese);
  } else {
      $sqlMov .= " ORDER BY m.data_operazione DESC";
      $stmtMov = $conn->prepare($sqlMov);
      $stmtMov->bind_param('s', $etichettaInfo['id_etichetta']);
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
                SELECT id_gruppo_transazione, amount, bm.started_date as data_operazione, 
                           (SELECT GROUP_CONCAT(e.id_etichetta SEPARATOR ',')
                              FROM bilancio_etichette2operazioni eo
                              JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                             WHERE eo.id_tabella = bm.id_movimento_revolut AND eo.tabella_operazione='movimenti_revolut') AS etichette
                    FROM movimenti_revolut bm
                UNION ALL
                SELECT be.id_gruppo_transazione, be.importo AS amount, be.data_operazione,
                       (SELECT GROUP_CONCAT(e.id_etichetta SEPARATOR ',')
                          FROM bilancio_etichette2operazioni eo
                          JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                         WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette
                FROM bilancio_entrate be
                UNION ALL
                SELECT bu.id_gruppo_transazione, -bu.importo AS amount, bu.data_operazione,
                       (SELECT GROUP_CONCAT(e.id_etichetta SEPARATOR ',')
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
    $stmtGrp->bind_param('ssi', $etichettaInfo['id_etichetta'], $mese, $categoria);
} elseif ($mese !== '' && $categoria === '0') {
    $stmtGrp = $conn->prepare($sqlGrp);
    $stmtGrp->bind_param('ss', $etichettaInfo['id_etichetta'], $mese);
} elseif ($mese !== '' && $categoria === '') {
    $stmtGrp = $conn->prepare($sqlGrp);
    $stmtGrp->bind_param('ss', $etichettaInfo['id_etichetta'], $mese);
} elseif ($mese === '' && $categoria !== '' && $categoria !== '0') {
    $stmtGrp = $conn->prepare($sqlGrp);
    $stmtGrp->bind_param('si', $etichettaInfo['id_etichetta'], $categoria);
} else {
    $stmtGrp = $conn->prepare($sqlGrp);
    $stmtGrp->bind_param('s', $etichettaInfo['id_etichetta']);
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
    <div class="d-flex">
        <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
      <?php if ($isAdmin): ?>
      <button type="button" class="btn btn-outline-light btn-sm ms-auto mb-3" id="settleBtn" onclick="settleSelected()">Segna saldati</button>
      <!--<button type="button" class="btn btn-outline-light btn-sm ms-auto d-inline mb-3" onclick="toggleSettle()">Seleziona</button>-->
    <?php endif; ?>
    </div>
  <h4 class="mb-3">
    <span id="etichettaDesc"><?= htmlspecialchars($etichettaInfo['descrizione']) ?></span>
      <i class="bi bi-pencil ms-2" role="button" onclick="openEtichettaModal()"></i>
  </h4>
  <form method="get" class="mb-3">
    <input type="hidden" name="id_etichetta" value="<?= htmlspecialchars($etichettaParam) ?>">
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

  <div class=" gap-4 mb-4 flex-wrap">
    <?php if ($movimenti->num_rows > 0): ?>
      <?php while ($mov = $movimenti->fetch_assoc()): ?>
        <?php render_movimento_etichetta($mov,$etichettaInfo['id_etichetta']); ?>
      <?php endwhile; ?>
    <?php else: ?>
      <p class="text-center text-muted">Nessun movimento per questa etichetta.</p>
    <?php endif; ?>
  
    <!-- Modal gestione quote utenti -->
    <div class="modal fade" id="u2oModal" tabindex="-1">
      <div class="modal-dialog">
        <form class="modal-content bg-dark text-white" onsubmit="saveU2o(event)">
          <div class="modal-header">
            <h5 class="modal-title">Quote utenti</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-2 mb-2 fw-bold d-none d-sm-flex" id="u2oHeader">
              <div class="col-sm-4">Utente</div>
              <div class="col-sm-3">Importo</div>
              <div class="col-sm-2 text-center">Saldo</div>
              <div class="col-sm-3">Data</div>
              <div class="col-sm-1"></div>
            </div>
            <div id="u2oRows"></div>
            <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="addU2oRow()">Aggiungi utente</button>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary w-100">Salva</button>
          </div>
        </form>
      </div>
    </div>

    <script>
    let currentIdE2o = null;
    const listaUtenti = <?= json_encode($listaUtenti, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    function openU2oModal(btn) {
      currentIdE2o = btn.dataset.idE2o;
      const rows = JSON.parse(btn.dataset.rows || '[]');
      const container = document.getElementById('u2oRows');
      container.innerHTML = '';
      rows.forEach(r => {
        const div = document.createElement('div');
        div.className = 'row g-2 align-items-center mb-2 u2o-row';
        div.dataset.id = r.id_u2o;
        let options = '<option value="">Seleziona utente</option>';
        listaUtenti.forEach(u => {
          const selected = u.id == r.id_utente ? 'selected' : '';
          options += `<option value="${u.id}" ${selected}>${u.nome} ${u.cognome}</option>`;
        });
        div.innerHTML = `
          <div class="col-12 col-sm-4">
            <label class="form-label d-sm-none">Utente</label>
            <select class="form-select form-select-sm">${options}</select>
          </div>
          <div class="col-6 col-sm-3">
            <label class="form-label d-sm-none">Importo</label>
            <input type="number" step="0.01" class="form-control form-control-sm" value="${r.importo_utente ?? ''}">
          </div>
          <div class="col-6 col-sm-2 text-center">
            <label class="form-label d-sm-none">Saldo</label>
            <input type="checkbox" class="form-check-input" ${r.saldata == 1 ? 'checked' : ''}>
          </div>
          <div class="col-6 col-sm-3">
            <label class="form-label d-sm-none">Data</label>
            <input type="date" class="form-control form-control-sm" value="${r.data_saldo ? r.data_saldo.substring(0,10) : ''}">
          </div>
          <div class="col-6 col-sm-1 text-end"><button type="button" class="btn btn-sm btn-danger" onclick="deleteU2oRow(this)">&times;</button></div>
        `;
        container.appendChild(div);
      });
      new bootstrap.Modal(document.getElementById('u2oModal')).show();
    }

    function addU2oRow() {
      const container = document.getElementById('u2oRows');
      const div = document.createElement('div');
      div.className = 'row g-2 align-items-center mb-2 u2o-row';
      div.dataset.id = 0;
      let options = '<option value="">Seleziona utente</option>';
      listaUtenti.forEach(u => {
        options += `<option value="${u.id}">${u.nome} ${u.cognome}</option>`;
      });
      div.innerHTML = `
        <div class="col-12 col-sm-4">
          <label class="form-label d-sm-none">Utente</label>
          <select class="form-select form-select-sm">${options}</select>
        </div>
        <div class="col-6 col-sm-3">
          <label class="form-label d-sm-none">Importo</label>
          <input type="number" step="0.01" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-sm-2 text-center">
          <label class="form-label d-sm-none">Saldo</label>
          <input type="checkbox" class="form-check-input">
        </div>
        <div class="col-6 col-sm-3">
          <label class="form-label d-sm-none">Data</label>
          <input type="date" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-sm-1 text-end"><button type="button" class="btn btn-sm btn-danger" onclick="deleteU2oRow(this)">&times;</button></div>
      `;
      container.appendChild(div);
    }

    function deleteU2oRow(btn) {
      const row = btn.closest('.u2o-row');
      const id = parseInt(row.dataset.id);
      if (id > 0) {
        if (!confirm('Eliminare questa riga?')) return;
        fetch('ajax/delete_u2o.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: 'id_u2o=' + encodeURIComponent(id)
        }).then(r => r.json()).then(res => {
          if (res.success) row.remove();
        });
      } else {
        row.remove();
      }
    }

    function saveU2o(e) {
      e.preventDefault();
      const rows = [];
      document.querySelectorAll('#u2oRows .u2o-row').forEach(row => {
        const id = parseInt(row.dataset.id);
        const importo = row.querySelector('input[type="number"]').value;
        const saldata = row.querySelector('input[type="checkbox"]').checked ? 1 : 0;
        const dataSaldo = row.querySelector('input[type="date"]').value;
        const utenteId = row.querySelector('select').value;
        rows.push({id_u2o: id, id_e2o: currentIdE2o, id_utente: utenteId, importo_utente: importo, saldata: saldata, data_saldo: dataSaldo});
      });
      fetch('ajax/update_utenti2operazioni_etichettate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({rows})
      }).then(r => r.json()).then(() => location.reload());
    }

    function settleSelected() {
      const today = new Date().toISOString().slice(0,10);
      const rows = [];
      document.querySelectorAll('.settle-checkbox:checked').forEach(cb => {
        const data = JSON.parse(cb.closest('.movement').dataset.rows || '[]');
        data.forEach(r => rows.push({id_u2o: r.id_u2o, saldata: 1, data_saldo: today}));
      });
      if (!rows.length) return;
      fetch('ajax/update_utenti2operazioni_etichettate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({rows})
      }).then(r => r.json()).then(() => location.reload());
    }

    function toggleSettle() {
      document.querySelectorAll('.settle-checkbox').forEach(cb => cb.classList.toggle('d-none'));
      document.querySelectorAll('.label_entrata_uscita').forEach(cb => cb.classList.toggle('d-none'));
      document.getElementById('settleBtn').classList.toggle('d-none');
    }
    </script>


    <?php if (!empty($gruppi)): ?>
      <h5 class="mt-4">Dettaglio per gruppo</h5>
      <form method="get" class="mb-3">
        <input type="hidden" name="id_etichetta" value="<?= htmlspecialchars($etichettaParam) ?>">
        <input type="hidden" name="mese" value="<?= htmlspecialchars($mese) ?>">
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <label for="categoria" class="form-label mb-0">Categoria:</label>
        <select name="categoria" id="categoria" class="form-select form-select-sm" style="max-width:200px;" onchange="this.form.submit()">
          <option value="" <?= $categoria === '' ? 'selected' : '' ?>>Tutte</option>
          <option value="0" <?= $categoria === '0' ? 'selected' : '' ?>>Nessuna categoria</option>
          <?php foreach ($categorie as $cat): ?>
            <option value="<?= $cat['id_categoria'] ?>" <?= $categoria == $cat['id_categoria'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['descrizione_categoria']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
    <div class="table-responsive">
      <table class="table table-dark table-striped align-middle table-sm">
        <thead>
          <tr>
            <!--<th>Categoria</th>-->
            <th>Gruppo</th>
            <th class="text-end">Entrate</th>
            <th class="text-end">Uscite</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($gruppi as $g): ?>
            <tr>
              <!--<td><?= htmlspecialchars($g['categoria']) ?></td>-->
              <td><?= htmlspecialchars($g['gruppo'] ?? $g['id_gruppo_transazione']) ?></td>
              <td class="text-end text-nowrap"><?= ($g['entrate'] > 0 ? '+' : '') . number_format($g['entrate'], 2, ',', '.') ?> €</td>
              <td class="text-end text-nowrap"><?= number_format($g['uscite'], 2, ',', '.') ?> €</td>
            </tr>
          <?php endforeach; ?>
            <tr>
              <!--<td></td>-->
              <td>Totali</td>
              <td class="text-end text-nowrap"><?= '+' . number_format($totali['entrate'] ?? 0, 2, ',', '.') ?> €</td>
              <td class="text-end text-nowrap"><?= number_format($totali['uscite'] ?? 0, 2, ',', '.') ?> €</td>
            </tr>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>


<!-- Modal modifica etichetta-movimento -->
<div class="modal fade" id="editE2oModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="editE2oForm" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">Modifica movimento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_e2o" id="id_e2o">
        <div class="mb-3">
          <label class="form-label">Descrizione extra</label>
          <input type="text" class="form-control bg-secondary text-white" name="descrizione_extra">
        </div>
        <div class="mb-3">
          <label class="form-label">Importo</label>
          <input type="number" step="0.01" class="form-control bg-secondary text-white" name="importo">
        </div>
        <div class="mb-3">
          <label class="form-label">Allegato</label>
          <input type="file" class="form-control" name="allegato">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>

<!-- Modifica etichetta Modal -->
<div class="modal fade" id="editEtichettaModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" onsubmit="saveEtichetta(event)">
      <div class="modal-header">
        <h5 class="modal-title">Modifica etichetta</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="id_etichetta" value="<?= (int)$etichettaInfo['id_etichetta'] ?>">
        <div class="mb-3">
          <label for="descrizione" class="form-label">Descrizione</label>
          <input type="text" class="form-control bg-secondary text-white" id="descrizione" required>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="attivo">
          <label class="form-check-label" for="attivo">Attivo</label>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="da_dividere">
          <label class="form-check-label" for="da_dividere">Da dividere</label>
        </div>
        <div class="mb-3">
          <label class="form-label">Utenti tra cui dividere</label>
          <div id="utenti_tra_cui_dividere" class="form-control bg-secondary text-white" style="max-height:150px; overflow:auto;">
            <?php foreach ($listaUtenti as $u): ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="<?= (int)$u['id'] ?>" id="utente<?= (int)$u['id'] ?>">
                <label class="form-check-label" for="utente<?= (int)$u['id'] ?>">
                  <?= htmlspecialchars(trim(($u['nome'] ?? '') . ' ' . ($u['cognome'] ?? ''))) ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>


<script>

function attachEditHandlers() {
  document.querySelectorAll('.edit-e2o').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('id_e2o').value = btn.dataset.idE2o;
      document.querySelector('#editE2oForm [name="descrizione_extra"]').value = btn.dataset.descrizioneExtra || '';
      document.querySelector('#editE2oForm [name="importo"]').value = btn.dataset.importo || '';
      new bootstrap.Modal(document.getElementById('editE2oModal')).show();
    });
  });

  document.querySelectorAll('.delete-e2o').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.idE2o;
      const rowId = btn.dataset.rowId;
      if (!id) return;
      if (!confirm('Rimuovere questa etichetta dal movimento?')) return;
      fetch('ajax/delete_e2o.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id_e2o=' + encodeURIComponent(id)
      })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          const el = document.getElementById(rowId);
          if (el) el.remove();
        }
      });
    });
  });
}

attachEditHandlers();


document.getElementById('editE2oForm').addEventListener('submit', function(e){
  e.preventDefault();
  const form = e.target;
  const formData = new FormData(form);
  fetch('ajax/update_e2o.php', {method:'POST', body:formData})
    .then(r=>r.json())
    .then(res=>{
      if(res.success){
        if(res.html && res.rowId){
          const el = document.getElementById(res.rowId);
          if(el){
            el.outerHTML = res.html;
            attachEditHandlers();
          }
        }
      }
      bootstrap.Modal.getInstance(document.getElementById('editE2oModal')).hide();
    });
});

  const etichettaData = {
    id: <?= (int)$etichettaInfo['id_etichetta'] ?>,
    descrizione: <?= json_encode($etichettaInfo['descrizione']) ?>,
    attivo: <?= (int)($etichettaInfo['attivo'] ?? 0) ?>,
    da_dividere: <?= (int)($etichettaInfo['da_dividere'] ?? 0) ?>,
    utenti: <?= json_encode($etichettaInfo['utenti_tra_cui_dividere'] ?? '') ?>
  };

  function openEtichettaModal() {
    document.getElementById('descrizione').value = etichettaData.descrizione;
    document.getElementById('attivo').checked = etichettaData.attivo == 1;
    document.getElementById('da_dividere').checked = etichettaData.da_dividere == 1;
    const utentiDiv = document.getElementById('utenti_tra_cui_dividere');
    utentiDiv.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    (etichettaData.utenti || '').split(',').filter(Boolean).forEach(id => {
      const cb = utentiDiv.querySelector('input[value="' + id + '"]');
      if (cb) cb.checked = true;
    });
    new bootstrap.Modal(document.getElementById('editEtichettaModal')).show();
  }

  function saveEtichetta(event) {
    event.preventDefault();
    const selectedUsers = Array.from(document.querySelectorAll('#utenti_tra_cui_dividere input:checked')).map(cb => cb.value).join(',');
    const payload = {
      id_etichetta: etichettaData.id,
      descrizione: document.getElementById('descrizione').value.trim(),
      attivo: document.getElementById('attivo').checked ? 1 : 0,
      da_dividere: document.getElementById('da_dividere').checked ? 1 : 0,
      utenti_tra_cui_dividere: selectedUsers
    };
    fetch('ajax/update_etichetta.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    }).then(r => r.json()).then(resp => {
      if (resp.success) {
        window.location.href = 'etichetta.php?id_etichetta=' + encodeURIComponent(etichettaData.id);
      } else {
        alert(resp.error || 'Errore nel salvataggio');
      }
    });
  }

</script>

<?php include 'includes/footer.php'; ?>
