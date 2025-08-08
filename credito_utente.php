<?php include 'includes/session_check.php'; ?>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:credito_utente.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
include 'includes/header.php';
setlocale(LC_TIME, 'it_IT.UTF-8');

// Utente loggato e famiglia corrente
$loggedUserId = $_SESSION['utente_id'] ?? 0;
$famigliaId   = $_SESSION['id_famiglia_gestione'] ?? 0;
$isAdmin      = ($loggedUserId == 1);

// Verifica permessi dell'utente loggato per cambiare utente
$stmtPerm = $conn->prepare('SELECT u2f.userlevelid AS lvl, u.admin FROM utenti2famiglie u2f JOIN utenti u ON u.id = u2f.id_utente WHERE u.id = ? AND u2f.id_famiglia = ?');
$stmtPerm->bind_param('ii', $loggedUserId, $famigliaId);
$stmtPerm->execute();
$perm = $stmtPerm->get_result()->fetch_assoc();
$stmtPerm->close();
$canChangeUser = (($perm['admin'] ?? 0) == 1) || (($perm['lvl'] ?? 0) >= 2);

// Utente da visualizzare
$idUtente = isset($_GET['id_utente']) ? (int)$_GET['id_utente'] : $loggedUserId;
if ($idUtente !== $loggedUserId && !$canChangeUser) {
    $idUtente = $loggedUserId;
}

// Dati utente selezionato (all'interno della famiglia)
$stmtU = $conn->prepare('SELECT u.id, u.nome, u.cognome
                         FROM utenti u
                         WHERE u.id = ?');
                         $stmtU->bind_param('i', $idUtente);

$stmtU->execute();
$utente = $stmtU->get_result()->fetch_assoc();
$stmtU->close();

if (!$utente) {
    echo '<p class="text-center text-white">Utente non trovato.</p>';
    include 'includes/footer.php';
    return;
}

// Recupera eventuale lista utenti per la select
$utentiFam = [];
if ($canChangeUser) {
    $stmtList = $conn->prepare('SELECT DISTINCT u.id, u.nome, u.cognome
                                 FROM utenti u
                                 JOIN bilancio_utenti2operazioni_etichettate u2o ON u.id = u2o.id_utente
                                 WHERE u.attivo = 1
                                 ORDER BY u.nome');
    $stmtList->execute();
    $utentiFam = $stmtList->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtList->close();
}

$sqlMov = "SELECT
                u2o.id_u2o,
                u2o.id_e2o,
                e2o.id_tabella,
                e2o.tabella_operazione AS tabella,
                e2o.id_etichetta,
                CONCAT(IFNULL(v.descrizione_extra,v.descrizione_operazione), ' (', v.importo_totale_operazione, ')') AS descrizione,
                v.data_operazione,
                v.descrizione AS etichetta_descrizione,
                (CASE
                    WHEN v.id_utente_operazione = u2o.id_utente THEN -(
                        CASE
                            WHEN IFNULL(u2o.importo_utente, 0) <> 0 THEN u2o.importo_utente
                            WHEN v.importo_etichetta <> 0 THEN (v.importo * u2o.quote)
                            ELSE (v.importo_totale_operazione - (v.importo * u2o.quote))
                        END
                    )
                    ELSE (
                        CASE
                            WHEN IFNULL(u2o.importo_utente, 0) <> 0 THEN u2o.importo_utente
                            ELSE (v.importo * u2o.quote)
                        END
                    )
                END) AS saldo_utente
           FROM bilancio_utenti2operazioni_etichettate u2o
           JOIN v_bilancio_etichette2operazioni_a_testa v ON u2o.id_e2o = v.id_e2o
           JOIN bilancio_etichette2operazioni e2o ON e2o.id_e2o = u2o.id_e2o
           WHERE u2o.id_utente = ? AND u2o.saldata = 0
           ORDER BY v.data_operazione DESC";

$stmtMov = $conn->prepare($sqlMov);
$stmtMov->bind_param('i', $idUtente);
$stmtMov->execute();
$resMov = $stmtMov->get_result();
$movimenti = [];
$saldoTot = 0.0;
while ($row = $resMov->fetch_assoc()) {
    $row['saldo_utente'] = (float)$row['saldo_utente'];
    if ($isAdmin) {
        $stmtDet = $conn->prepare("SELECT u2o.id_u2o, u.nome, u.cognome,
                (CASE
                    WHEN v.id_utente_operazione = u2o.id_utente THEN -(
                        CASE
                            WHEN IFNULL(u2o.importo_utente, 0) <> 0 THEN u2o.importo_utente
                            WHEN v.importo_etichetta <> 0 THEN (v.importo * u2o.quote)
                            ELSE (v.importo_totale_operazione - (v.importo * u2o.quote))
                        END
                    )
                    ELSE (
                        CASE
                            WHEN IFNULL(u2o.importo_utente, 0) <> 0 THEN u2o.importo_utente
                            ELSE (v.importo * u2o.quote)
                        END
                    )
                END) AS importo,
                u2o.saldata,
                u2o.data_saldo
           FROM bilancio_utenti2operazioni_etichettate u2o
           JOIN v_bilancio_etichette2operazioni_a_testa v ON u2o.id_e2o = v.id_e2o
           JOIN utenti u ON u.id = u2o.id_utente
           WHERE u2o.id_e2o = ?");
        $stmtDet->bind_param('i', $row['id_e2o']);
        $stmtDet->execute();
        $resDet = $stmtDet->get_result();
        $rowsDet = [];
        while ($r = $resDet->fetch_assoc()) {
            $r['importo'] = (float)$r['importo'];
            $rowsDet[] = $r;
        }
        $stmtDet->close();
        $row['rows'] = $rowsDet;
    }
    $saldoTot += $row['saldo_utente'];
    $movimenti[] = $row;
}
$stmtMov->close();
?>

<div class="text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>

  <?php if ($canChangeUser): ?>
    <form method="get" class="mb-3">
      <select name="id_utente" class="form-select w-auto d-inline" onchange="this.form.submit()">
        <?php foreach ($utentiFam as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= $u['id']==$idUtente ? 'selected' : '' ?>><?= htmlspecialchars(trim(($u['nome'] ?? '').' '.($u['cognome'] ?? ''))) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  <?php endif; ?>

   <h4 class="mb-3">Credito/Debito per: <?= htmlspecialchars(trim(($utente['nome'] ?? '') . ' ' . ($utente['cognome'] ?? ''))) ?></h4>
   <div class="mb-4">Saldo totale: <span><?= ($saldoTot>=0?'+':'') . number_format($saldoTot, 2, ',', '.') ?> €</span></div>

   <?php if ($isAdmin): ?>
     <button id="saldaBtn" class="btn btn-outline-light mb-3">Salda movimenti</button>
   <?php endif; ?>

   <?php if (!empty($movimenti)): ?>
    <?php foreach ($movimenti as $mov): ?>
      <?php $rowsAttr = $isAdmin ? htmlspecialchars(json_encode($mov['rows'] ?? []), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : ''; ?>
      <div class="movement d-flex justify-content-between align-items-start text-white mb-2" data-tabella="<?= htmlspecialchars($mov['tabella']) ?>" data-id-tabella="<?= (int)$mov['id_tabella'] ?>" <?php if ($isAdmin): ?>data-rows='<?= $rowsAttr ?>'<?php endif; ?> onclick="openMovimento(this)" style="cursor:pointer">
        <?php if ($isAdmin): ?>
          <input type="checkbox" class="form-check-input me-2 flex-shrink-0" style="width:1.25rem;height:1.25rem;" data-id-u2o="<?= $mov['id_u2o'] ?>" onclick="event.stopPropagation();">
        <?php endif; ?>
        <div class="flex-grow-1 me-3" style="max-width:calc(100% - 8rem);">
          <div class="descr fw-semibold text-break"><?= htmlspecialchars($mov['descrizione']) ?></div>
          <div class="small"><?= date('d/m/Y H:i', strtotime($mov['data_operazione'])) ?></div>
          <div class="mt-1"><a href="etichetta.php?id_etichetta=<?= urlencode($mov['id_etichetta']) ?>" class="badge bg-secondary text-decoration-none" onclick="event.stopPropagation();"><?= htmlspecialchars($mov['etichetta_descrizione']) ?></a></div>
        </div>
        <div class="text-end flex-shrink-0">
          <div class="amount"><?= ($mov['saldo_utente']>=0?'+':'') . number_format($mov['saldo_utente'], 2, ',', '.') ?> €</div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="text-center text-muted">Nessun movimento trovato per questo utente.</p>
  <?php endif; ?>
</div>

  <div class="modal fade" id="movModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content bg-dark text-white">
        <div class="modal-header">
          <h5 class="modal-title">Dettaglio movimento</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="movInfo"></div>
          <?php if ($isAdmin): ?><div id="movRows" class="mt-3"></div><?php endif; ?>
        </div>
        <div class="modal-footer">
          <a href="#" target="_blank" class="btn btn-primary" id="linkDettaglio">Vai al dettaglio</a>
        </div>
      </div>
    </div>
  </div>

  <?php if ($isAdmin): ?>
  <div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content bg-dark text-white">
        <div class="modal-header">
          <h5 class="modal-title">Conferma</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">Sei sicuro di voler saldare i movimenti selezionati?</div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
          <button type="button" class="btn btn-primary" id="confirmSalda">Conferma</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <script>
  function openMovimento(div) {
    const tabella = div.dataset.tabella;
    const id = div.dataset.idTabella;
    fetch(`ajax/get_movimento.php?tabella=${encodeURIComponent(tabella)}&id=${id}`)
      .then(r => r.json())
      .then(res => {
        if (!res.success) { alert(res.error || 'Errore'); return; }
        const d = res.data;
        const info = document.getElementById('movInfo');
        const imp = Number(d.amount).toLocaleString('it-IT', {minimumFractionDigits:2, maximumFractionDigits:2});
        const date = new Date(d.data_operazione.replace(' ', 'T'));
        let html = `<div><strong>${d.descrizione}</strong></div>`;
        html += `<div>${date.toLocaleString('it-IT')}</div>`;
        html += `<div>${(d.amount>=0?'+':'')}${imp} €</div>`;
        if (d.note) html += `<div class="mt-2">${d.note}</div>`;
        info.innerHTML = html;
        <?php if ($isAdmin): ?>
        const rows = JSON.parse(div.dataset.rows || '[]');
        const container = document.getElementById('movRows');
        container.innerHTML = '';
        rows.forEach(r => {
          const imp2 = Number(r.importo).toLocaleString('it-IT', {minimumFractionDigits:2, maximumFractionDigits:2});
          const status = r.saldata == 1 ? '✔' : '✖';
          const p = document.createElement('div');
          p.textContent = `${r.nome} ${r.cognome}: ${imp2} € ${status}`;
          container.appendChild(p);
        });
        <?php endif; ?>
        document.getElementById('linkDettaglio').href = `dettaglio.php?src=${tabella}&id=${id}`;
        new bootstrap.Modal(document.getElementById('movModal')).show();
      });
  }

  <?php if ($isAdmin): ?>
  document.getElementById('saldaBtn').addEventListener('click', () => {
    const checked = document.querySelectorAll('.movement input[type="checkbox"]:checked');
    if (checked.length === 0) {
      alert('Seleziona almeno un movimento');
      return;
    }
    new bootstrap.Modal(document.getElementById('confirmModal')).show();
  });

  document.getElementById('confirmSalda').addEventListener('click', () => {
    const checked = document.querySelectorAll('.movement input[type="checkbox"]:checked');
    const ids = Array.from(checked).map(cb => cb.dataset.idU2o);
    fetch('ajax/update_u2o_saldata_bulk.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ids: ids})
    }).then(r => r.json()).then(res => {
      if (!res.success) {
        alert(res.error || 'Errore');
        return;
      }
      checked.forEach(cb => cb.closest('.movement').remove());
      bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
    });
  });
  <?php endif; ?>
  </script>

<?php include 'includes/footer.php'; ?>
