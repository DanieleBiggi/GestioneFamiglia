<?php
declare(strict_types=1);
require_once 'includes/session_check.php';
require_once 'includes/db.php';
require_once 'includes/permissions.php';

// Legacy labels and Revolut movements are scoped to family 1.
$familyId = (int)($_SESSION['id_famiglia_gestione'] ?? 0);
if ($familyId !== 1) { http_response_code(403); exit('Conti condivisi non disponibili per questa famiglia.'); }
$id = filter_var($_GET['id_etichetta'] ?? $_POST['id_etichetta'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Etichetta non valida.'); }
$stmt = $conn->prepare('SELECT id_etichetta, descrizione, tipo_utilizzo FROM bilancio_etichette WHERE id_etichetta=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$etichetta = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$etichetta) { http_response_code(404); exit('Etichetta non trovata.'); }
if (empty($_SESSION['csrf_conto_etichette'])) $_SESSION['csrf_conto_etichette'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf_conto_etichette'];
$errore = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Sessione scaduta. Aggiorna la pagina.'); }
    $azione = $_POST['azione'] ?? '';
    try {
        if ($azione === 'modalita') {
            $tipo = $_POST['tipo_utilizzo'] ?? '';
            if (!in_array($tipo, ['spese', 'conto'], true)) throw new RuntimeException('Modalità non valida.');
            $stmt = $conn->prepare('UPDATE bilancio_etichette SET tipo_utilizzo=? WHERE id_etichetta=?');
            $stmt->bind_param('si', $tipo, $id);
            if (!$stmt->execute()) throw new RuntimeException('Salvataggio non riuscito.');
            $stmt->close();
        } elseif ($azione === 'aggiungi') {
            $persona = trim((string)($_POST['controparte'] ?? ''));
            $descrizione = trim((string)($_POST['descrizione'] ?? ''));
            $tipo = $_POST['tipo'] ?? '';
            $data = (string)($_POST['data_movimento'] ?? '');
            $raw = $_POST['importo'] ?? '';
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
            if (!$dt || $dt->format('Y-m-d') !== $data) throw new RuntimeException('Data non valida.');
            if ($persona === '' || mb_strlen($persona) > 120 || $descrizione === '' || mb_strlen($descrizione) > 255) throw new RuntimeException('Inserisci persona e descrizione.');
            if (!in_array($tipo, ['quota','compensazione','pagamento','debito','saldo_debito','rettifica'], true)) throw new RuntimeException('Tipo non valido.');
            if (!is_scalar($raw) || !is_numeric($raw)) throw new RuntimeException('Importo non valido.');
            $importo = round((float)$raw, 2);
            if (abs($importo) < 0.01 || abs($importo) > 1000000) throw new RuntimeException('Importo fuori intervallo.');
            if ($tipo !== 'rettifica' && $importo <= 0) throw new RuntimeException('Inserisci un importo positivo.');
            if ($tipo === 'compensazione' || $tipo === 'pagamento' || $tipo === 'debito') $importo = -abs($importo);
            if ($tipo === 'quota' || $tipo === 'saldo_debito') $importo = abs($importo);
            // Optionally link the allocation to the actual movement already assigned to this label.
            // The bank movement itself is never duplicated or modified.
            $idE2o = null;
            if (($_POST['id_e2o'] ?? '') !== '') {
                $idE2o = filter_var($_POST['id_e2o'], FILTER_VALIDATE_INT);
                if (!$idE2o || $idE2o < 1) throw new RuntimeException('Movimento collegato non valido.');
                $check = $conn->prepare('SELECT 1 FROM bilancio_etichette2operazioni WHERE id_e2o=? AND id_etichetta=? LIMIT 1');
                $check->bind_param('ii', $idE2o, $id);
                $check->execute();
                $exists = (bool)$check->get_result()->fetch_row();
                $check->close();
                if (!$exists) throw new RuntimeException('Il movimento non appartiene a questa etichetta.');
                $dup = $conn->prepare('SELECT 1 FROM etichette_conti_condivisi WHERE id_e2o=? AND controparte=? LIMIT 1');
                $dup->bind_param('is', $idE2o, $persona);
                $dup->execute();
                $already = (bool)$dup->get_result()->fetch_row();
                $dup->close();
                if ($already) throw new RuntimeException('Questo movimento è già attribuito alla persona indicata.');
            }
            $stmt = $conn->prepare('INSERT INTO etichette_conti_condivisi (id_etichetta,id_famiglia,controparte,data_movimento,tipo,importo,descrizione,id_e2o) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->bind_param('iisssdsi', $id, $familyId, $persona, $data, $tipo, $importo, $descrizione, $idE2o);
            if (!$stmt->execute()) throw new RuntimeException('Registrazione non riuscita.');
            $stmt->close();
        } elseif ($azione === 'elimina') {
            $riga = filter_var($_POST['id_riga'] ?? null, FILTER_VALIDATE_INT);
            if (!$riga) throw new RuntimeException('Registrazione non valida.');
            $stmt = $conn->prepare('DELETE FROM etichette_conti_condivisi WHERE id=? AND id_etichetta=? AND id_famiglia=?');
            $stmt->bind_param('iii', $riga, $id, $familyId);
            if (!$stmt->execute()) throw new RuntimeException('Eliminazione non riuscita.');
            $stmt->close();
        } else {
            throw new RuntimeException('Operazione non valida.');
        }
        header('Location: etichetta_conto.php?id_etichetta=' . $id . '&salvato=1');
        exit;
    } catch (Throwable $e) {
        $errore = $e->getMessage();
    }
}
$stmt = $conn->prepare('SELECT controparte, ROUND(SUM(importo),2) AS saldo, COUNT(*) AS numero FROM etichette_conti_condivisi WHERE id_etichetta=? AND id_famiglia=? GROUP BY controparte ORDER BY controparte');
$stmt->bind_param('ii', $id, $familyId);
$stmt->execute();
$saldi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$stmt = $conn->prepare('SELECT id, controparte, data_movimento, tipo, importo, descrizione, id_e2o FROM etichette_conti_condivisi WHERE id_etichetta=? AND id_famiglia=? ORDER BY data_movimento DESC,id DESC LIMIT 200');
$stmt->bind_param('ii', $id, $familyId);
$stmt->execute();
$righe = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$persone = array_column($saldi, 'controparte');
// Read only the bank movements already linked to the current label.
// Assignment is always manual: no guess about the share owed by each participant.
$stmt = $conn->prepare("SELECT eo.id_e2o,
    COALESCE(NULLIF(eo.descrizione_extra,''), mr.description, bu.descrizione_operazione, be.descrizione_operazione, 'Movimento') AS descrizione,
    COALESCE(mr.started_date,bu.data_operazione,be.data_operazione) AS data_operazione,
    CASE WHEN eo.importo IS NOT NULL THEN eo.importo
         WHEN eo.tabella_operazione='bilancio_uscite' THEN -ABS(bu.importo)
         WHEN eo.tabella_operazione='bilancio_entrate' THEN ABS(be.importo)
         ELSE mr.amount END AS importo
    FROM bilancio_etichette2operazioni eo
    LEFT JOIN v_movimenti_revolut_filtrati mr ON eo.tabella_operazione='movimenti_revolut' AND eo.id_tabella=mr.id_movimento_revolut
    LEFT JOIN bilancio_uscite bu ON eo.tabella_operazione='bilancio_uscite' AND eo.id_tabella=bu.id_uscita
    LEFT JOIN bilancio_entrate be ON eo.tabella_operazione='bilancio_entrate' AND eo.id_tabella=be.id_entrata
    WHERE eo.id_etichetta=? AND (mr.id_movimento_revolut IS NOT NULL OR bu.id_uscita IS NOT NULL OR be.id_entrata IS NOT NULL)
    ORDER BY data_operazione DESC,eo.id_e2o DESC LIMIT 300");
$stmt->bind_param('i', $id);
$stmt->execute();
$movimentiEtichetta = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$stmt = $conn->prepare('SELECT COUNT(DISTINCT id_e2o) AS numero FROM etichette_conti_condivisi WHERE id_etichetta=? AND id_famiglia=? AND id_e2o IS NOT NULL');
$stmt->bind_param('ii', $id, $familyId);
$stmt->execute();
$movimentiCollegati = (int)($stmt->get_result()->fetch_assoc()['numero'] ?? 0);
$stmt->close();
include 'includes/header.php';
?>
<style>
.conto-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:.75rem }
.conto-panel { border:1px solid var(--bs-secondary);border-radius:1rem;padding:1rem;background:rgba(255,255,255,.04) }
.conto-input { min-height:46px }
.conto-item { display:flex;justify-content:space-between;gap:1rem;align-items:start;border-bottom:1px solid #555;padding:.9rem 0 }
.conto-item:last-child {border-bottom:0}
@media(max-width:576px) { .conto-action {width:100%;min-height:44px} .conto-item {flex-direction:column} .conto-item form {width:100%} }
</style>
<main class="container text-white pb-5">
  <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center mb-3">
    <div>
      <a class="text-white-50" href="etichetta.php?id_etichetta=<?= $id ?>">← Torna all'etichetta</a>
      <h1 class="h4 mt-2">Conto condiviso · <?= htmlspecialchars($etichetta['descrizione']) ?></h1>
      <p class="small text-white-50 mb-0">Attribuisci le quote ai movimenti già etichettati e registra le compensazioni informali. Nessun movimento bancario fittizio.</p>
    </div>
  </div>
  <?php if ($errore): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($errore) ?></div><?php endif; ?>
  <?php if (isset($_GET['salvato'])): ?><div class="alert alert-success" role="status">Modifica salvata.</div><?php endif; ?>
  <section class="conto-panel mb-3">
    <h2 class="h6">Modalità etichetta</h2>
    <form method="post" class="row g-2 align-items-end">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="id_etichetta" value="<?= $id ?>">
      <input type="hidden" name="azione" value="modalita">
      <div class="col-12 col-sm-8">
        <label for="tipoUtilizzo" class="form-label">Utilizzo principale</label>
        <select name="tipo_utilizzo" id="tipoUtilizzo" class="form-select conto-input">
          <option value="spese" <?= $etichetta['tipo_utilizzo']==='spese'?'selected':'' ?>>Raggruppamento spese</option>
          <option value="conto" <?= $etichetta['tipo_utilizzo']==='conto'?'selected':'' ?>>Conto tra persone</option>
        </select>
      </div>
      <div class="col-12 col-sm-4"><button class="btn btn-outline-light w-100 conto-input">Salva modalità</button></div>
    </form>
    <p class="small text-white-50 mt-2 mb-0">La modalità è solo una classificazione: i movimenti e le etichette esistenti restano invariati.</p>
  </section>
  <div class="alert alert-secondary py-2 small mb-3" role="status">
    Movimenti etichettati disponibili: <strong><?= count($movimentiEtichetta) ?></strong>.
    Collegati al conto condiviso: <strong><?= $movimentiCollegati ?></strong>.
    I saldi qui sotto comprendono solo le quote e le compensazioni esplicitamente attribuite, non l'intero costo degli abbonamenti.
  </div>
  <h2 class="h5">Saldi per persona</h2>
  <div class="conto-grid mb-4">
    <?php foreach ($saldi as $r): $saldo=(float)$r['saldo']; ?>
      <article class="conto-panel">
        <div class="fw-bold"><?= htmlspecialchars($r['controparte']) ?></div>
        <div class="fs-4 fw-bold <?= $saldo>0?'text-warning':($saldo<0?'text-info':'text-success') ?>"><?= number_format(abs($saldo),2,',','.') ?> €</div>
        <div class="small"><?= $saldo>0?'Ti deve':($saldo<0?'Devi tu':'In pari') ?></div>
        <div class="small text-white-50"><?= (int)$r['numero'] ?> registrazioni</div>
      </article>
    <?php endforeach; ?>
    <?php if (!$saldi): ?><div class="conto-panel text-white-50">Nessun saldo ancora registrato. Aggiungi una quota o compensazione per iniziare.</div><?php endif; ?>
  </div>
  <section class="conto-panel mb-4">
    <h2 class="h5">Registra quota o compensazione</h2>
    <p class="small text-white-50">Per Disney+ o Netflix scegli il movimento già etichettato e inserisci solo la quota attribuita all'amico. Una cena offerta senza movimento bancario può essere una compensazione indipendente. Non importiamo automaticamente i vecchi valori.</p>
    <form method="post" class="row g-3">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="id_etichetta" value="<?= $id ?>">
      <input type="hidden" name="azione" value="aggiungi">
      <div class="col-12 col-md-6">
        <label class="form-label" for="contoPersona">Persona</label>
        <input class="form-control conto-input" id="contoPersona" name="controparte" list="contoPersone" maxlength="120" required placeholder="Es. Bocci">
        <datalist id="contoPersone"><?php foreach ($persone as $persona): ?><option value="<?= htmlspecialchars($persona, ENT_QUOTES) ?>"></option><?php endforeach; ?></datalist>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label" for="contoTipo">Operazione</label>
        <select class="form-select conto-input" id="contoTipo" name="tipo" required>
          <option value="quota">Quota dovuta a me (+)</option>
          <option value="compensazione">Compensazione, es. cena offerta (−)</option>
          <option value="pagamento">Pagamento ricevuto (−)</option>
          <option value="debito">Debito verso questa persona (−)</option>
          <option value="saldo_debito">Pagamento del mio debito (+)</option>
          <option value="rettifica">Rettifica (+/−)</option>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label" for="contoMovimento">Movimento già presente nell'etichetta (facoltativo)</label>
        <select class="form-select conto-input" id="contoMovimento" name="id_e2o">
          <option value="">Nessuno: quota o compensazione manuale</option>
          <?php foreach ($movimentiEtichetta as $mov): ?>
            <option value="<?= (int)$mov['id_e2o'] ?>"><?= htmlspecialchars(date('d/m/Y',strtotime($mov['data_operazione'])) . ' · ' . mb_strimwidth($mov['descrizione'],0,65,'…') . ' · ' . number_format((float)$mov['importo'],2,',','.') . ' €') ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text text-white-50">Il collegamento identifica l'operazione originale. L'importo attribuito è soltanto la quota del partecipante.</div>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label" for="contoImporto">Importo (€)</label>
        <input type="number" step="0.01" inputmode="decimal" class="form-control conto-input" id="contoImporto" name="importo" required placeholder="Es. 15.00">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label" for="contoData">Data</label>
        <input type="date" class="form-control conto-input" id="contoData" name="data_movimento" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label" for="contoDescrizione">Motivo</label>
        <input class="form-control conto-input" id="contoDescrizione" maxlength="255" name="descrizione" required placeholder="Es. Quota Disney+ 2026">
      </div>
      <div class="col-12"><button class="btn btn-primary w-100 py-3">Registra nel conto condiviso</button></div>
    </form>
  </section>
  <h2 class="h5">Storico delle compensazioni</h2>
  <section class="conto-panel">
    <?php foreach ($righe as $r): ?>
      <div class="conto-item">
        <div>
          <div class="fw-semibold"><?= htmlspecialchars($r['controparte']) ?> · <?= htmlspecialchars($r['descrizione']) ?></div>
          <div class="small text-white-50"><?= date('d/m/Y',strtotime($r['data_movimento'])) ?> · <?= htmlspecialchars($r['tipo']) ?><?= $r['id_e2o'] !== null ? ' · movimento etichettato #'.(int)$r['id_e2o'] : ' · registrazione manuale' ?></div>
          <div class="fw-semibold <?= (float)$r['importo']>=0?'text-warning':'text-info' ?>"><?= (float)$r['importo']>0?'+':'' ?><?= number_format((float)$r['importo'],2,',','.') ?> €</div>
        </div>
        <form method="post" onsubmit="return confirm('Eliminare questa registrazione? Il saldo verrà ricalcolato.');">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="id_etichetta" value="<?= $id ?>">
          <input type="hidden" name="azione" value="elimina">
          <input type="hidden" name="id_riga" value="<?= (int)$r['id'] ?>">
          <button type="submit" class="btn btn-outline-danger btn-sm conto-action">Elimina</button>
        </form>
      </div>
    <?php endforeach; ?>
    <?php if (!$righe): ?><p class="text-white-50 mb-0">Nessuna registrazione presente.</p><?php endif; ?>
  </section>
</main>
<?php include 'includes/footer.php'; ?>
