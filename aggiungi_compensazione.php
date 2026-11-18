<?php
declare(strict_types=1);
require_once 'includes/session_check.php';
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:etichetta.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
$familyId = (int)($_SESSION['id_famiglia_gestione'] ?? 0);
if ($familyId !== 1) { http_response_code(403); exit('Operazione non disponibile per questa famiglia.'); }
$labelId = filter_var($_GET['id_etichetta'] ?? $_POST['id_etichetta'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$tipoDefault = ($_GET['tipo'] ?? '') === 'quota' ? 'quota' : 'compensazione';
$labels = $conn->query("SELECT id_etichetta,descrizione,anno FROM bilancio_etichette WHERE attivo=1 ORDER BY descrizione,anno DESC")->fetch_all(MYSQLI_ASSOC);
// Gli utenti condivisi sono definiti dall'etichetta, non dalla famiglia.
$people = [];
if ($labelId > 0) {
    $stmtPeople = $conn->prepare("SELECT u.id,u.nome,u.cognome
        FROM bilancio_etichette e
        JOIN utenti u ON FIND_IN_SET(CAST(u.id AS CHAR), REPLACE(COALESCE(e.utenti_tra_cui_dividere,''),' ','')) > 0
        WHERE e.id_etichetta=? AND e.attivo=1 AND u.attivo=1
        ORDER BY u.nome,u.cognome");
    $stmtPeople->bind_param('i',$labelId);
    $stmtPeople->execute();
    $people = $stmtPeople->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtPeople->close();
}
if (empty($_SESSION['csrf_conto_etichette'])) $_SESSION['csrf_conto_etichette'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf_conto_etichette'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrf,(string)($_POST['csrf'] ?? ''))) throw new RuntimeException('Sessione scaduta.');
        $labelId = filter_var($_POST['id_etichetta'] ?? 0,FILTER_VALIDATE_INT) ?: 0;
        $personId = filter_var($_POST['id_utente'] ?? 0,FILTER_VALIDATE_INT) ?: 0;
        $type = (string)($_POST['tipo'] ?? '');
        $description = trim((string)($_POST['descrizione'] ?? ''));
        $date = (string)($_POST['data_movimento'] ?? '');
        $raw = (string)($_POST['importo'] ?? '');
        $d = DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$d || $d->format('Y-m-d') !== $date) throw new RuntimeException('Data non valida.');
        if (!in_array($type,['quota','compensazione','pagamento','debito','saldo_debito','rettifica'],true)) throw new RuntimeException('Tipo non valido.');
        if ($description==='' || mb_strlen($description)>255) throw new RuntimeException('Descrizione obbligatoria (massimo 255 caratteri).');
        if (!is_numeric($raw) || !is_finite((float)$raw) || (float)$raw<=0 || (float)$raw>1000000) throw new RuntimeException('Importo positivo non valido.');
        $amount = round((float)$raw,2);
        if ($amount<0.01) throw new RuntimeException('Importo troppo piccolo.');
        $check = $conn->prepare("SELECT 1 FROM bilancio_etichette WHERE id_etichetta=? AND attivo=1");
        $check->bind_param('i',$labelId);$check->execute();$ok=$check->get_result()->num_rows===1;$check->close();
        if (!$ok) throw new RuntimeException('Etichetta non valida.');
        $check = $conn->prepare("SELECT u.nome,u.cognome
            FROM bilancio_etichette e
            JOIN utenti u ON FIND_IN_SET(CAST(u.id AS CHAR), REPLACE(COALESCE(e.utenti_tra_cui_dividere,''),' ','')) > 0
            WHERE e.id_etichetta=? AND u.id=? AND e.attivo=1 AND u.attivo=1 LIMIT 1");
        $check->bind_param('ii',$labelId,$personId);$check->execute();$person=$check->get_result()->fetch_assoc();$check->close();
        if (!$person) throw new RuntimeException('La persona non e presente in Condividi con per questa etichetta.');
        if ($personId === (int)($_SESSION['utente_id'] ?? 0)) throw new RuntimeException('Seleziona un altra persona.');
        $personName = trim($person['nome'].' '.$person['cognome']);
        $idE2o = null;
        if (($_POST['id_e2o'] ?? '')!=='') {
            $idE2o = filter_var($_POST['id_e2o'],FILTER_VALIDATE_INT);
            if (!$idE2o) throw new RuntimeException('Movimento collegato non valido.');
            $check = $conn->prepare('SELECT 1 FROM bilancio_etichette2operazioni WHERE id_etichetta=? AND id_e2o=?');
            $check->bind_param('ii',$labelId,$idE2o);$check->execute();$ok=$check->get_result()->num_rows===1;$check->close();
            if (!$ok) throw new RuntimeException('Movimento non appartenente alla etichetta.');
        }
        if ($idE2o) {
            // Un movimento gia attribuito a questa persona nel sistema legacy
            // non deve essere conteggiato nuovamente nel registro condiviso.
            $check=$conn->prepare('SELECT 1 FROM bilancio_utenti2operazioni_etichettate WHERE id_e2o=? AND id_utente=? LIMIT 1');
            $check->bind_param('ii',$idE2o,$personId);$check->execute();$legacyUsed=$check->get_result()->num_rows>0;$check->close();
            if ($legacyUsed) throw new RuntimeException('Questa quota e gia presente nel conto della persona: evita un duplicato.');
            $check=$conn->prepare('SELECT 1 FROM etichette_conti_condivisi WHERE id_e2o=? AND id_utente=? LIMIT 1');
            $check->bind_param('ii',$idE2o,$personId);$check->execute();$sharedUsed=$check->get_result()->num_rows>0;$check->close();
            if ($sharedUsed) throw new RuntimeException('Movimento gia collegato alla persona nel conto condiviso.');
        }
        if ($type==='compensazione' && $idE2o) throw new RuntimeException('Una compensazione non deve essere collegata a un movimento bancario.');
        if ($type==='pagamento' && !$idE2o) throw new RuntimeException('Per un pagamento realmente ricevuto collega un movimento bancario esistente.');
        if ($type==='saldo_debito' && !$idE2o) throw new RuntimeException('Per il saldo di un debito con denaro collega una uscita reale già registrata.');
        if ($type==='pagamento' && $idE2o) {
            $check = $conn->prepare("SELECT eo.tabella_operazione,COALESCE(be.importo,mr.amount,0) AS importo
                FROM bilancio_etichette2operazioni eo
                LEFT JOIN bilancio_entrate be ON eo.tabella_operazione='bilancio_entrate' AND eo.id_tabella=be.id_entrata
                LEFT JOIN movimenti_revolut mr ON eo.tabella_operazione='movimenti_revolut' AND eo.id_tabella=mr.id_movimento_revolut
                WHERE eo.id_e2o=? AND eo.id_etichetta=? LIMIT 1");
            $check->bind_param('ii',$idE2o,$labelId);$check->execute();$incoming=$check->get_result()->fetch_assoc();$check->close();
            if (!$incoming || !in_array($incoming['tabella_operazione'],['bilancio_entrate','movimenti_revolut'],true) || (float)$incoming['importo']<=0) throw new RuntimeException('Il pagamento richiede una entrata reale positiva.');
        }
        if ($type==='saldo_debito') {
            $check = $conn->prepare("SELECT eo.tabella_operazione,COALESCE(bu.importo,mr.amount,0) AS importo
                FROM bilancio_etichette2operazioni eo
                LEFT JOIN bilancio_uscite bu ON eo.tabella_operazione='bilancio_uscite' AND eo.id_tabella=bu.id_uscita
                LEFT JOIN movimenti_revolut mr ON eo.tabella_operazione='movimenti_revolut' AND eo.id_tabella=mr.id_movimento_revolut
                WHERE eo.id_e2o=? AND eo.id_etichetta=? LIMIT 1");
            $check->bind_param('ii',$idE2o,$labelId);$check->execute();$outgoing=$check->get_result()->fetch_assoc();$check->close();
            if (!$outgoing || !in_array($outgoing['tabella_operazione'],['bilancio_uscite','movimenti_revolut'],true)
                || ($outgoing['tabella_operazione']==='movimenti_revolut' && (float)$outgoing['importo']>=0)) throw new RuntimeException('Il saldo del debito richiede una uscita reale.');
        }
        if (in_array($type,['compensazione','debito','pagamento'],true)) $amount=-$amount;
        if ($type==='rettifica') throw new RuntimeException('Le rettifiche richiedono un controllo dedicato.');
        $stmt = $conn->prepare('INSERT INTO etichette_conti_condivisi (id_etichetta,id_famiglia,controparte,data_movimento,tipo,importo,descrizione,id_e2o,id_utente) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->bind_param('iisssdsii',$labelId,$familyId,$personName,$date,$type,$amount,$description,$idE2o,$personId);
        if (!$stmt->execute()) throw new RuntimeException('Salvataggio non riuscito: '.$stmt->error);
        $stmt->close();
        header('Location: etichetta_conto.php?id_etichetta='.$labelId.'&salvato=1');exit;
    } catch (Throwable $e) { $error=$e->getMessage(); }
}
$availableMovements = [];
if ($labelId>0) {
    $stmtMov = $conn->prepare("SELECT eo.id_e2o,eo.tabella_operazione,
       COALESCE(NULLIF(eo.descrizione_extra,''),be.descrizione_extra,be.descrizione_operazione,
         bu.descrizione_extra,bu.descrizione_operazione,mr.description,'Movimento') AS descrizione,
       COALESCE(be.data_operazione,bu.data_operazione,mr.started_date) AS data_operazione
       FROM bilancio_etichette2operazioni eo
       LEFT JOIN bilancio_entrate be ON eo.tabella_operazione='bilancio_entrate' AND eo.id_tabella=be.id_entrata
       LEFT JOIN bilancio_uscite bu ON eo.tabella_operazione='bilancio_uscite' AND eo.id_tabella=bu.id_uscita
       LEFT JOIN movimenti_revolut mr ON eo.tabella_operazione='movimenti_revolut' AND eo.id_tabella=mr.id_movimento_revolut
       WHERE eo.id_etichetta=? AND (be.id_entrata IS NOT NULL OR bu.id_uscita IS NOT NULL OR mr.id_movimento_revolut IS NOT NULL)
       ORDER BY data_operazione DESC,eo.id_e2o DESC LIMIT 200");
    $stmtMov->bind_param('i',$labelId);
    $stmtMov->execute();
    $availableMovements=$stmtMov->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtMov->close();
}
include 'includes/header.php';
?>
<main class="container pb-5">
  <a class="btn btn-outline-secondary mb-3" href="<?= $labelId ? 'etichetta_conto.php?id_etichetta='.$labelId : 'index.php' ?>">← Indietro</a>
  <h1 class="h4">Registra quota o compensazione</h1>
  <p class="text-muted">Le compensazioni non creano entrate o uscite bancarie.</p>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" class="row g-3">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <div class="col-12 col-md-6">
      <label for="idEtichetta" class="form-label">Etichetta</label>
      <select class="form-select" id="idEtichetta" name="id_etichetta" required onchange="if(this.value) location.href='aggiungi_compensazione.php?id_etichetta='+encodeURIComponent(this.value);">
        <option value="">Scegli etichetta</option>
        <?php foreach ($labels as $l): ?>
          <option value="<?= (int)$l['id_etichetta'] ?>" <?= $labelId===(int)$l['id_etichetta']?'selected':'' ?>><?= htmlspecialchars($l['descrizione'].(!empty($l['anno'])?' '.$l['anno']:'')) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label" for="idPersona">Persona</label>
      <select class="form-select" name="id_utente" id="idPersona" required>
        <option value="">Scegli persona</option>
        <?php foreach ($people as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)($_POST['id_utente'] ?? 0)===(int)$p['id']?'selected':'' ?>><?= htmlspecialchars(trim($p['nome'].' '.$p['cognome'])) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($labelId>0 && !$people): ?>
        <div class="form-text text-warning">Nessuna persona attiva in «Condividi con» per questa etichetta. Aggiorna l'etichetta prima di registrare un'operazione.</div>
      <?php endif; ?>
    </div>
    <div class="col-12 col-md-6">
      <label for="tipo" class="form-label">Operazione</label>
      <select name="tipo" id="tipo" class="form-select">
        <option value="compensazione" <?= $tipoDefault==='compensazione'?'selected':'' ?>>Compensazione: favore o spesa offerta</option>
        <option value="quota" <?= $tipoDefault==='quota'?'selected':'' ?>>Quota dovuta a me</option>
        <option value="pagamento">Pagamento ricevuto (movimento reale)</option>
        <option value="debito">Debito verso la persona</option>
        <option value="saldo_debito">Pagamento del mio debito</option>
      </select>
    </div>
    <div class="col-6 col-md-3"><label for="importo" class="form-label">Importo (€)</label><input id="importo" type="number" min="0.01" max="1000000" step="0.01" name="importo" class="form-control" required></div>
    <div class="col-6 col-md-3"><label for="data" class="form-label">Data</label><input id="data" type="date" name="data_movimento" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
    <div class="col-12"><label for="descrizione" class="form-label">Descrizione</label><input id="descrizione" name="descrizione" class="form-control" maxlength="255" required></div>
    <div class="col-12"><label for="idE2o" class="form-label">Movimento reale collegato (facoltativo)</label>
      <select id="idE2o" name="id_e2o" class="form-select">
        <option value="">Nessuno, operazione senza movimento bancario</option>
        <?php foreach ($availableMovements as $m): ?>
          <option value="<?= (int)$m['id_e2o'] ?>"><?= htmlspecialchars(date('d/m/Y',strtotime($m['data_operazione'])).' · '.mb_strimwidth((string)$m['descrizione'],0,90,'…').' (#'.(int)$m['id_e2o'].')') ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">Per un pagamento reale collega un movimento in entrata. Non duplicare quote già assegnate.</div>
    </div>
    <div class="col-12"><button type="submit" class="btn btn-primary w-100">Salva operazione condivisa</button></div>
  </form>
</main>
<?php include 'includes/footer.php'; ?>
