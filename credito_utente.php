<?php include 'includes/session_check.php'; ?>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/db.php';
include 'includes/header.php';
setlocale(LC_TIME, 'it_IT.UTF-8');

// Utente loggato e famiglia corrente
$loggedUserId = $_SESSION['utente_id'] ?? 0;
$famigliaId   = $_SESSION['id_famiglia_gestione'] ?? 0;

// Verifica permessi dell'utente loggato per cambiare utente
$stmtPerm = $conn->prepare('SELECT u.userlevelid AS lvl, u.admin FROM utenti u WHERE u.id = ?');
$stmtPerm->bind_param('i', $loggedUserId);
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
    $stmtList = $conn->prepare('SELECT u.id, u.nome, u.cognome
                                 FROM utenti u
                                 WHERE u.attivo = 1
                                 ORDER BY u.nome');
    $stmtList->execute();
    $utentiFam = $stmtList->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtList->close();
}

$sqlMov = "SELECT
                CONCAT(ifnull(v.descrizione_extra,v.descrizione_operazione), ' (', v.importo_totale_operazione, ')') AS descrizione,
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
