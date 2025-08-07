<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/render_movimento_etichetta.php';
include 'includes/header.php';
setlocale(LC_TIME, 'it_IT.UTF-8');

$etichettaParam = $_GET['etichetta'] ?? '';
$mese = $_GET['mese'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$etichettaInfo = null;

if ($etichettaParam === '') {
    echo '<p class="text-center text-muted">Nessuna etichetta selezionata.</p>';
    include 'includes/footer.php';
    return;
}


$stmtEt = $conn->prepare("SELECT id_etichetta, descrizione, attivo, da_dividere, utenti_tra_cui_dividere FROM bilancio_etichette WHERE descrizione = ?");
$stmtEt->bind_param('s', $etichettaParam);
$stmtEt->execute();
$etichettaInfo = $stmtEt->get_result()->fetch_assoc();
$stmtEt->close();

if (!$etichettaInfo) {

    echo '<p class="text-center text-muted">Etichetta non trovata.</p>';
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
$stmtM->bind_param('s', $etichettaInfo);
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
             WHERE e.descrizione = ?";
  if ($mese !== '') {
      $sqlMov .= " AND DATE_FORMAT(m.data_operazione,'%Y-%m')=?";
      $sqlMov .= " ORDER BY m.data_operazione DESC";
      $stmtMov = $conn->prepare($sqlMov);
      $stmtMov->bind_param('ss', $etichetta, $mese);
  } else {
      $sqlMov .= " ORDER BY m.data_operazione DESC";
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
    <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>    
  <h4 class="mb-3">Movimenti per etichetta: <span id="etichettaDesc"><?= htmlspecialchars($etichetta) ?></span><i class="bi bi-pencil ms-2" role="button" onclick="openEtichettaModal()"></i></h4>

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
        <?php render_movimento_etichetta($mov); ?>
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
            <div id="u2oRows"></div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary w-100">Salva</button>
          </div>
        </form>
      </div>
    </div>

    <script>
    let currentIdE2o = null;

    function openU2oModal(btn) {
      currentIdE2o = btn.dataset.idE2o;
      const rows = JSON.parse(btn.dataset.rows || '[]');
      const container = document.getElementById('u2oRows');
      container.innerHTML = '';
      rows.forEach(r => {
        const div = document.createElement('div');
        div.className = 'row g-2 align-items-center mb-2 u2o-row';
        div.dataset.id = r.id_u2o;
        div.innerHTML = `
          <div class="col-5">${r.nome} ${r.cognome}${r.utente_pagante == 1 ? ' (P)' : ''}</div>
          <div class="col-3"><input type="number" step="0.01" class="form-control form-control-sm" value="${r.importo_utente ?? ''}"></div>
          <div class="col-2 text-center"><input type="checkbox" class="form-check-input" ${r.saldata == 1 ? 'checked' : ''}></div>
          <div class="col-2"><input type="date" class="form-control form-control-sm" value="${r.data_saldo ? r.data_saldo.substring(0,10) : ''}"></div>
        `;
        container.appendChild(div);
      });
      new bootstrap.Modal(document.getElementById('u2oModal')).show();
    }

    function saveU2o(e) {
      e.preventDefault();
      const rows = [];
      document.querySelectorAll('#u2oRows .u2o-row').forEach(row => {
        const id = parseInt(row.dataset.id);
        const importo = row.querySelector('input[type="number"]').value;
        const saldata = row.querySelector('input[type="checkbox"]').checked ? 1 : 0;
        const dataSaldo = row.querySelector('input[type="date"]').value;
        rows.push({id_u2o: id, importo_utente: importo, saldata: saldata, data_saldo: dataSaldo});
      });
      fetch('ajax/update_utenti2operazioni_etichettate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({rows})
      }).then(r => r.json()).then(() => location.reload());
    }
    </script>


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
          <label for="utenti_tra_cui_dividere" class="form-label">Utenti tra cui dividere</label>
          <input type="text" class="form-control bg-secondary text-white" id="utenti_tra_cui_dividere">

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
}


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
    document.getElementById('utenti_tra_cui_dividere').value = etichettaData.utenti || '';
    new bootstrap.Modal(document.getElementById('editEtichettaModal')).show();
  }

  function saveEtichetta(event) {
    event.preventDefault();
    const payload = {
      id_etichetta: etichettaData.id,
      descrizione: document.getElementById('descrizione').value.trim(),
      attivo: document.getElementById('attivo').checked ? 1 : 0,
      da_dividere: document.getElementById('da_dividere').checked ? 1 : 0,
      utenti_tra_cui_dividere: document.getElementById('utenti_tra_cui_dividere').value.trim()
    };
    fetch('ajax/update_etichetta.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    }).then(r => r.json()).then(resp => {
      if (resp.success) {
        window.location.href = 'etichetta.php?etichetta=' + encodeURIComponent(payload.descrizione);
      } else {
        alert(resp.error || 'Errore nel salvataggio');
      }
    });
  }

</script>

<?php include 'includes/footer.php'; ?>

