<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
include 'includes/header.php';
setlocale(LC_TIME, 'it_IT.UTF-8');

// Utente loggato e famiglia corrente
$loggedUserId = $_SESSION['utente_id'] ?? 0;
$famigliaId   = $_SESSION['id_famiglia_gestione'] ?? 0;

// Verifica permessi dell'utente loggato per cambiare utente
$stmtPerm = $conn->prepare('SELECT COALESCE(u2f.userlevelid, u.userlevelid) AS lvl, u.admin
                            FROM utenti u
                            LEFT JOIN utenti2famiglie u2f ON u2f.id_utente = u.id AND u2f.id_famiglia = ?
                            WHERE u.id = ?');
$stmtPerm->bind_param('ii', $famigliaId, $loggedUserId);
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
                         JOIN utenti2famiglie u2f ON u.id = u2f.id_utente
                         WHERE u.id = ? AND u2f.id_famiglia = ?');
$stmtU->bind_param('ii', $idUtente, $famigliaId);
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
    $stmtList = $conn->prepare('SELECT u.id, u.nome, u.cognome
                                 FROM utenti u
                                 JOIN utenti2famiglie u2f ON u.id = u2f.id_utente
                                 WHERE u2f.id_famiglia = ?
                                 ORDER BY u.nome');
    $stmtList->bind_param('i', $famigliaId);
    $stmtList->execute();
    $utentiFam = $stmtList->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtList->close();
}

$sqlMov = "SELECT m.descrizione, m.data_operazione, e.descrizione AS etichetta_descrizione,
                  CASE WHEN u2o.importo_utente IS NULL THEN (COALESCE(e2o.importo, ABS(m.amount)) / cnt.cnt) ELSE u2o.importo_utente END AS quota,
                  CASE WHEN u2o.utente_pagante = 1
                       THEN COALESCE(e2o.importo, ABS(m.amount)) - CASE WHEN u2o.importo_utente IS NULL THEN (COALESCE(e2o.importo, ABS(m.amount)) / cnt.cnt) ELSE u2o.importo_utente END
                       ELSE -CASE WHEN u2o.importo_utente IS NULL THEN (COALESCE(e2o.importo, ABS(m.amount)) / cnt.cnt) ELSE u2o.importo_utente END
                  END AS saldo_utente
           FROM (
                SELECT id_movimento_revolut AS id, COALESCE(NULLIF(descrizione_extra,''), description) AS descrizione,
                       started_date AS data_operazione, amount, 'movimenti_revolut' AS tabella
                FROM v_movimenti_revolut
                UNION ALL
                SELECT be.id_entrata AS id, COALESCE(NULLIF(be.descrizione_extra,''), be.descrizione_operazione) AS descrizione,
                       be.data_operazione, be.importo AS amount, 'bilancio_entrate' AS tabella
                FROM bilancio_entrate be
                UNION ALL
                SELECT bu.id_uscita AS id, COALESCE(NULLIF(bu.descrizione_extra,''), bu.descrizione_operazione) AS descrizione,
                       bu.data_operazione, -bu.importo AS amount, 'bilancio_uscite' AS tabella
                FROM bilancio_uscite bu
           ) m
           JOIN bilancio_etichette2operazioni e2o ON e2o.id_tabella = m.id AND e2o.tabella_operazione = m.tabella
           JOIN bilancio_utenti2operazioni_etichettate u2o ON u2o.id_e2o = e2o.id_e2o
           JOIN bilancio_etichette e ON e.id_etichetta = e2o.id_etichetta
           JOIN (SELECT id_e2o, COUNT(*) AS cnt FROM bilancio_utenti2operazioni_etichettate GROUP BY id_e2o) cnt ON cnt.id_e2o = e2o.id_e2o
           WHERE u2o.id_utente = ? AND u2o.saldata = 0
           ORDER BY m.data_operazione DESC";

$stmtMov = $conn->prepare($sqlMov);
$stmtMov->bind_param('i', $idUtente);
$stmtMov->execute();
$resMov = $stmtMov->get_result();
$movimenti = [];
$saldoTot = 0.0;
while ($row = $resMov->fetch_assoc()) {
    $row['quota'] = (float)$row['quota'];
    $row['saldo_utente'] = (float)$row['saldo_utente'];
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

  <?php if (!empty($movimenti)): ?>
    <?php foreach ($movimenti as $mov): ?>
      <div class="movement d-flex justify-content-between align-items-start text-white mb-2">
        <div class="flex-grow-1 me-3">
          <div class="descr fw-semibold"><?= htmlspecialchars($mov['descrizione']) ?></div>
          <div class="small"><?= date('d/m/Y H:i', strtotime($mov['data_operazione'])) ?></div>
          <div class="mt-1"><span class="badge bg-secondary"><?= htmlspecialchars($mov['etichetta_descrizione']) ?></span></div>
        </div>
        <div class="text-end">
          <div class="amount"><?= ($mov['saldo_utente']>=0?'+':'') . number_format($mov['saldo_utente'], 2, ',', '.') ?> €</div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="text-center text-muted">Nessun movimento trovato per questo utente.</p>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
