<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:eventi.php', 'view')) { http_response_code(403); exit('Accesso negato'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT e.*, t.tipo_evento, t.colore, t.colore_testo FROM eventi e LEFT JOIN eventi_tipi_eventi t ON e.id_tipo_evento = t.id WHERE e.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$evento = $res->fetch_assoc();
$stmt->close();

if (!$evento) {
    include 'includes/header.php';
    echo '<p class="text-danger">Record non trovato.</p>';
    include 'includes/footer.php';
    exit;
}

$canUpdate = has_permission($conn, 'table:eventi', 'update');
$tipiRes = $conn->query('SELECT id, tipo_evento FROM eventi_tipi_eventi ORDER BY tipo_evento');
$tipi = $tipiRes ? $tipiRes->fetch_all(MYSQLI_ASSOC) : [];

$famiglieEvento = [];
$stmtFam = $conn->prepare('SELECT id_famiglia FROM eventi_eventi2famiglie WHERE id_evento = ?');
$stmtFam->bind_param('i', $id);
$stmtFam->execute();
$resFam = $stmtFam->get_result();
while ($row = $resFam->fetch_assoc()) { $famiglieEvento[] = (int)$row['id_famiglia']; }
$stmtFam->close();
$allFamRes = $conn->query('SELECT id_famiglia, nome_famiglia FROM famiglie ORDER BY nome_famiglia');
$allFamiglie = $allFamRes ? $allFamRes->fetch_all(MYSQLI_ASSOC) : [];

$periodo = '';
if (!empty($evento['data_evento']) || !empty($evento['ora_evento'])) {
    $start = trim(($evento['data_evento'] ?? '') . ' ' . ($evento['ora_evento'] ?? ''));
    $endPart = trim(($evento['data_fine'] ?? '') . ' ' . ($evento['ora_fine'] ?? ''));
    $periodo = $endPart && $endPart !== $start ? $start . ' - ' . $endPart : $start;
}

// Luoghi collegati all'evento
$luoghi = [];
$stmtLuogo = $conn->prepare("SELECT e2l.id_e2l, l.indirizzo FROM eventi_eventi2luogo e2l JOIN eventi_luogo l ON e2l.id_luogo = l.id WHERE e2l.id_evento = ? ORDER BY l.indirizzo");
$stmtLuogo->bind_param('i', $id);
$stmtLuogo->execute();
$resLuogo = $stmtLuogo->get_result();
while ($row = $resLuogo->fetch_assoc()) { $luoghi[] = $row; }
$stmtLuogo->close();

// Luoghi disponibili per l'aggiunta
$luoghiDisponibili = [];
$stmtLuogoDisp = $conn->prepare('SELECT id, indirizzo FROM eventi_luogo WHERE id NOT IN (SELECT id_luogo FROM eventi_eventi2luogo WHERE id_evento = ?) ORDER BY indirizzo');
$stmtLuogoDisp->bind_param('i', $id);
$stmtLuogoDisp->execute();
$resLuogoDisp = $stmtLuogoDisp->get_result();
while ($row = $resLuogoDisp->fetch_assoc()) { $luoghiDisponibili[] = $row; }
$stmtLuogoDisp->close();

// Tutti i luoghi (per datalist di modifica)
$allLuoghiRes = $conn->query('SELECT id, indirizzo FROM eventi_luogo ORDER BY indirizzo');
$allLuoghi = $allLuoghiRes ? $allLuoghiRes->fetch_all(MYSQLI_ASSOC) : [];

// Invitati già collegati all'evento con stato e note
$invitati = [];
$stmtInv = $conn->prepare("SELECT e2i.id_e2i, i.nome, i.cognome, e2i.partecipa, e2i.forse, e2i.assente, e2i.note FROM eventi_eventi2invitati e2i JOIN eventi_invitati i ON e2i.id_invitato = i.id WHERE e2i.id_evento = ? ORDER BY i.cognome, i.nome");
$stmtInv->bind_param('i', $id);
$stmtInv->execute();
$resInv = $stmtInv->get_result();
while ($row = $resInv->fetch_assoc()) { $invitati[] = $row; }
$stmtInv->close();

// Invitati disponibili per l'aggiunta (solo attivi e collegati alla famiglia)
$invitatiDisponibili = [];
$stmtDisp = $conn->prepare("SELECT i.id, ifnull(u.nome,i.nome) as nome, ifnull(u.cognome,i.cognome) as cognome FROM eventi_invitati i JOIN eventi_invitati2famiglie f ON i.id = f.id_invitato LEFT JOIN utenti u ON i.id_utente = u.id AND u.attivo = 1 WHERE f.id_famiglia = ? AND f.attivo = 1 AND i.id NOT IN (SELECT id_invitato FROM eventi_eventi2invitati WHERE id_evento = ?) ORDER BY i.cognome, i.nome");
$famiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$stmtDisp->bind_param('ii', $famiglia, $id);
$stmtDisp->execute();
$resDisp = $stmtDisp->get_result();
while ($row = $resDisp->fetch_assoc()) { $invitatiDisponibili[] = $row; }
$stmtDisp->close();

$showAddRule = !empty($evento['creator_email']) && (!empty($evento['id_tipo_evento']) || count($invitati) > 0);

// Cibo collegato all'evento
$cibi = [];
$stmtCibo = $conn->prepare("SELECT e2c.id_e2c, c.piatto, c.um, e2c.quantita FROM eventi_eventi2cibo e2c JOIN eventi_cibo c ON e2c.id_cibo = c.id WHERE e2c.id_evento = ? ORDER BY c.piatto");
$stmtCibo->bind_param('i', $id);
$stmtCibo->execute();
$resCibo = $stmtCibo->get_result();
while ($row = $resCibo->fetch_assoc()) { $cibi[] = $row; }
$stmtCibo->close();

// Cibo disponibile per l'aggiunta
$ciboDisponibile = [];
$stmtCd = $conn->prepare("SELECT id, piatto FROM eventi_cibo WHERE id_famiglia = ? AND attivo = 1 ORDER BY piatto");
$stmtCd->bind_param('i', $famiglia);
$stmtCd->execute();
$resCd = $stmtCd->get_result();
while ($row = $resCd->fetch_assoc()) { $ciboDisponibile[] = $row; }
$stmtCd->close();

// Salvadanai ed etichette collegati all'evento
$salvEt = [];
$stmtSE = $conn->prepare("SELECT e2se.id_e2se, s.nome_salvadanaio, b.descrizione AS etichetta, e2se.id_salvadanaio, e2se.id_etichetta FROM eventi_eventi2salvadanai_etichette e2se LEFT JOIN salvadanai s ON e2se.id_salvadanaio = s.id_salvadanaio LEFT JOIN bilancio_etichette b ON e2se.id_etichetta = b.id_etichetta WHERE e2se.id_evento = ? ORDER BY s.nome_salvadanaio, b.descrizione");
$stmtSE->bind_param('i', $id);
$stmtSE->execute();
$resSE = $stmtSE->get_result();
while ($row = $resSE->fetch_assoc()) { $salvEt[] = $row; }
$stmtSE->close();

// Calcolo importi e totale finanze
$finanze = [];
$totaleFinanze = 0.0;
foreach ($salvEt as $row) {
    $importo = 0.0;
    if (!empty($row['id_salvadanaio'])) {
        $stmtBud = $conn->prepare('SELECT SUM(importo) AS totale FROM budget WHERE id_famiglia = ? AND id_salvadanaio = ?');
        $stmtBud->bind_param('ii', $famiglia, $row['id_salvadanaio']);
        $stmtBud->execute();
        $resBud = $stmtBud->get_result();
        $importo = (float)($resBud->fetch_assoc()['totale'] ?? 0);
        $stmtBud->close();
    }
    $totaleFinanze += $importo;
    $row['importo'] = $importo;
    $finanze[] = $row;
}

// Salvadanai disponibili
$salvadanaiTutti = [];
$resSalvAll = $conn->query('SELECT id_salvadanaio, nome_salvadanaio FROM salvadanai ORDER BY nome_salvadanaio');
$salvadanaiTutti = $resSalvAll ? $resSalvAll->fetch_all(MYSQLI_ASSOC) : [];
$salvadanaiAttivi = [];
$resSalvAttivi = $conn->query('SELECT id_salvadanaio, nome_salvadanaio FROM salvadanai WHERE data_scadenza IS NULL OR data_scadenza > CURDATE() ORDER BY nome_salvadanaio');
$salvadanaiAttivi = $resSalvAttivi ? $resSalvAttivi->fetch_all(MYSQLI_ASSOC) : [];

// Etichette disponibili
$etichetteTutte = [];
$resEtAll = $conn->query('SELECT id_etichetta, descrizione FROM bilancio_etichette ORDER BY descrizione');
$etichetteTutte = $resEtAll ? $resEtAll->fetch_all(MYSQLI_ASSOC) : [];
$etichetteAttive = [];
$resEtAttive = $conn->query('SELECT id_etichetta, descrizione FROM bilancio_etichette WHERE attivo = 1 ORDER BY descrizione');
$etichetteAttive = $resEtAttive ? $resEtAttive->fetch_all(MYSQLI_ASSOC) : [];

include 'includes/header.php';
?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <div class="d-flex align-items-center mb-3 justify-content-between">
    <h4 class="mb-0" id="eventoTitolo"><?= htmlspecialchars($evento['titolo'] ?? '') ?></h4>
    <div>
      <?php if ($showAddRule): ?>
        <i class="bi bi-star me-2" id="addRuleBtn" data-id="<?= $id ?>" style="cursor:pointer"></i>
      <?php endif; ?>
      <?php if ($canUpdate): ?>
        <i class="bi bi-pencil-square" id="editEventoBtn" style="cursor:pointer"></i>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($periodo !== ''): ?>
    <div class="mb-3"><?= htmlspecialchars($periodo) ?></div>
  <?php endif; ?>
  <?php if (!empty($evento['descrizione'])): ?>
    <p><?= nl2br(htmlspecialchars($evento['descrizione'])) ?></p>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="d-flex align-items-center">
      <h5 class="mb-0 me-3">Luoghi</h5>
    </div>
    <button type="button" class="btn btn-outline-light btn-sm" id="addLuogoBtn">Aggiungi luogo</button>
  </div>
  <ul class="list-group list-group-flush bg-dark" id="luoghiList">
    <?php foreach ($luoghi as $idx => $row): ?>
      <li class="list-group-item bg-dark text-white <?= $idx >= 3 ? 'd-none extra-row' : '' ?> luogo-row"
          data-id="<?= (int)$row['id_e2l'] ?>"
          data-luogo="<?= htmlspecialchars($row['indirizzo'], ENT_QUOTES) ?>">
        <?= htmlspecialchars($row['indirizzo']) ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (count($luoghi) > 3): ?>
    <div class="text-center mt-3">
      <button id="toggleLuoghi" class="btn btn-outline-light btn-sm">Mostra tutti</button>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="d-flex align-items-center">
      <h5 class="mb-0 me-3">Invitati</h5>
    </div>
    <button type="button" class="btn btn-outline-light btn-sm" id="addInvitatoBtn">Aggiungi invitato</button>
  </div>
  <ul class="list-group list-group-flush bg-dark" id="invitatiList">
    <?php foreach ($invitati as $idx => $row):
      $stato = $row['partecipa'] ? 'partecipa' : ($row['forse'] ? 'forse' : ($row['assente'] ? 'assente' : ''));
      $icon = 'bi-circle';
      $color = 'text-secondary';
      if ($stato === 'partecipa') { $icon = 'bi-check-circle'; $color = 'text-success'; }
      elseif ($stato === 'forse') { $icon = 'bi-question-circle'; $color = 'text-warning'; }
      elseif ($stato === 'assente') { $icon = 'bi-x-circle'; $color = 'text-danger'; }
    ?>
      <li class="list-group-item bg-dark text-white <?= $idx >= 3 ? 'd-none extra-row' : '' ?> inv-row d-flex align-items-center"
          data-id="<?= (int)$row['id_e2i'] ?>"
          data-stato="<?= $stato ?>"
          data-note="<?= htmlspecialchars($row['note'] ?? '', ENT_QUOTES) ?>">
        <i class="bi <?= $icon ?> me-2 <?= $color ?>"></i>
        <span class="flex-grow-1"><?= htmlspecialchars($row['nome'] . ' ' . $row['cognome']) ?></span>
        <?php if (!empty($row['note'])): ?>
          <small class="ms-2"><?= htmlspecialchars($row['note']) ?></small>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (count($invitati) > 3): ?>
    <div class="text-center mt-3">
      <button id="toggleInvitati" class="btn btn-outline-light btn-sm">Mostra tutti</button>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
    <div class="d-flex align-items-center">
      <h5 class="mb-0 me-3">Cibo</h5>
    </div>
    <button type="button" class="btn btn-outline-light btn-sm" id="addCiboBtn">Aggiungi cibo</button>
  </div>
  <ul class="list-group list-group-flush bg-dark" id="ciboList">
    <?php foreach ($cibi as $idx => $row): ?>
      <li class="list-group-item bg-dark text-white <?= $idx >= 3 ? 'd-none extra-row' : '' ?> cibo-row"
          data-id="<?= (int)$row['id_e2c'] ?>"
          data-piatto="<?= htmlspecialchars($row['piatto'], ENT_QUOTES) ?>"
          data-quantita="<?= htmlspecialchars($row['quantita'] ?? '', ENT_QUOTES) ?>">
        <?= htmlspecialchars($row['piatto']) ?><?php if ($row['quantita'] !== null) echo ' - ' . htmlspecialchars($row['quantita']) . ' ' . htmlspecialchars($row['um']); ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (count($cibi) > 3): ?>
    <div class="text-center mt-3">
      <button id="toggleCibo" class="btn btn-outline-light btn-sm">Mostra tutti</button>
    </div>
  <?php endif; ?>
  <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
    <div class="d-flex align-items-center">
      <h5 class="mb-0 me-3">Finanze</h5>
    </div>
    <button type="button" class="btn btn-outline-light btn-sm" id="addSeBtn">Aggiungi</button>
  </div>
  <div class="text-end fw-bold mb-2">Totale <?= number_format($totaleFinanze,2,',','.') ?> &euro;</div>
  <ul class="list-group list-group-flush bg-dark" id="seList">
    <?php foreach ($finanze as $idx => $row): ?>
      <li class="list-group-item bg-dark text-white <?= $idx >= 3 ? 'd-none extra-row' : '' ?> se-row"
          data-id="<?= (int)$row['id_e2se'] ?>"
          data-id-salvadanaio="<?= (int)($row['id_salvadanaio'] ?? 0) ?>"
          data-id-etichetta="<?= (int)($row['id_etichetta'] ?? 0) ?>">
        <span class="importo me-2"><?= number_format((float)$row['importo'],2,',','.') ?> &euro;</span>
        <span class="descrizione">
          <?php
            $parts = [];
            if(!empty($row['id_salvadanaio'])){
              $parts[] = '<a href="budget_anno.php?id_salvadanaio='.(int)$row['id_salvadanaio'].'" class="text-white">'.htmlspecialchars($row['nome_salvadanaio'] ?? '').'</a>';
            }
            if(!empty($row['id_etichetta'])){
              $parts[] = '<a href="etichetta.php?id_etichetta='.(int)$row['id_etichetta'].'" class="text-white">'.htmlspecialchars($row['etichetta'] ?? '').'</a>';
            }
            echo implode(' - ', $parts);
          ?>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
    <?php if (count($finanze) > 3): ?>
      <div class="text-center mt-3">
        <button id="toggleSe" class="btn btn-outline-light btn-sm">Mostra tutti</button>
      </div>
    <?php endif; ?>
    <button type="button" class="btn btn-danger w-100 mt-4" id="deleteEventoBtn" data-id="<?= (int)$id ?>">Elimina</button>
  </div>

  <?php if ($canUpdate): ?>
  <div class="modal fade" id="eventoModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="eventoForm">
      <div class="modal-header">
        <h5 class="modal-title">Modifica evento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <div class="mb-3">
          <label class="form-label">Titolo</label>
          <input type="text" name="titolo" class="form-control bg-secondary text-white" value="<?= htmlspecialchars($evento['titolo'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Descrizione</label>
          <textarea name="descrizione" class="form-control bg-secondary text-white"><?= htmlspecialchars($evento['descrizione'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Data</label>
          <input type="date" name="data_evento" class="form-control bg-secondary text-white" value="<?= htmlspecialchars($evento['data_evento'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Ora</label>
          <input type="time" name="ora_evento" class="form-control bg-secondary text-white" value="<?= htmlspecialchars($evento['ora_evento'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Data fine</label>
          <input type="date" name="data_fine" class="form-control bg-secondary text-white" value="<?= htmlspecialchars($evento['data_fine'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Ora fine</label>
          <input type="time" name="ora_fine" class="form-control bg-secondary text-white" value="<?= htmlspecialchars($evento['ora_fine'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Tipo evento</label>
          <select name="id_tipo_evento" class="form-select bg-secondary text-white">
            <option value="">-- nessuno --</option>
            <?php foreach ($tipi as $tipo): ?>
              <option value="<?= (int)$tipo['id'] ?>" <?= ($evento['id_tipo_evento'] ?? null) == $tipo['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tipo['tipo_evento']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Famiglie</label>
          <?php foreach ($allFamiglie as $fam): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="famiglie[]" id="fam<?= (int)$fam['id_famiglia'] ?>" value="<?= (int)$fam['id_famiglia'] ?>" <?= in_array((int)$fam['id_famiglia'], $famiglieEvento, true) ? 'checked' : '' ?>>
              <label class="form-check-label" for="fam<?= (int)$fam['id_famiglia'] ?>"><?= htmlspecialchars($fam['nome_famiglia']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Modal modifica luogo -->
<div class="modal fade" id="luogoModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="luogoForm">
      <div class="modal-header">
        <h5 class="modal-title">Luogo</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_e2l" id="id_e2l">
        <div class="mb-3">
          <label class="form-label">Luogo</label>
          <input type="text" name="luogo" id="luogoNome" list="luoghiOptionsEdit" class="form-control bg-secondary text-white">
          <datalist id="luoghiOptionsEdit">
            <?php foreach ($allLuoghi as $l): ?>
              <option value="<?= htmlspecialchars($l['indirizzo']) ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <div class="form-text text-white-50">Inizia a digitare e seleziona un luogo dai suggerimenti</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger me-auto" id="deleteLuogoBtn">Elimina</button>
        <button type="submit" class="btn btn-primary">Salva</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal aggiungi luogo -->
<div class="modal fade" id="addLuogoModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="addLuogoForm">
      <div class="modal-header">
        <h5 class="modal-title">Aggiungi luogo</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_evento" value="<?= (int)$id ?>">
        <div class="mb-3">
          <label class="form-label">Luogo</label>
          <input type="text" name="luogo" list="luoghiOptions" class="form-control bg-secondary text-white">
          <datalist id="luoghiOptions">
            <?php foreach ($luoghiDisponibili as $l): ?>
              <option value="<?= htmlspecialchars($l['indirizzo']) ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <div class="form-text text-white-50">Inizia a digitare e seleziona un luogo dai suggerimenti</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Aggiungi</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal modifica invitato -->
<div class="modal fade" id="invitatoModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="invitatoForm">
      <div class="modal-header">
        <h5 class="modal-title">Invitato</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_e2i" id="id_e2i">
        <div class="mb-3">
          <label class="form-label">Stato</label>
          <div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="stato" id="statoPartecipa" value="partecipa">
              <label class="form-check-label" for="statoPartecipa"><i class="bi bi-check-circle text-success"></i> Partecipa</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="stato" id="statoForse" value="forse">
              <label class="form-check-label" for="statoForse"><i class="bi bi-question-circle text-warning"></i> Forse</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="stato" id="statoAssente" value="assente">
              <label class="form-check-label" for="statoAssente"><i class="bi bi-x-circle text-danger"></i> Assente</label>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Note</label>
          <textarea name="note" id="note" class="form-control bg-secondary text-white"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger me-auto" id="deleteInvitatoBtn">Elimina</button>
        <button type="submit" class="btn btn-primary">Salva</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal aggiungi invitato -->
<div class="modal fade" id="addInvitatoModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="addInvitatoForm">
      <div class="modal-header">
        <h5 class="modal-title">Aggiungi invitato</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_evento" value="<?= (int)$id ?>">
        <div class="mb-3">
          <label class="form-label">Invitato</label>
          <input type="text" name="invitato" list="invitatiOptions" class="form-control bg-secondary text-white">
          <datalist id="invitatiOptions">
            <?php foreach ($invitatiDisponibili as $inv): ?>
              <option value="<?= htmlspecialchars($inv['nome'] . ' ' . $inv['cognome']) ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <div class="form-text text-white-50">Inizia a digitare e seleziona un invitato dai suggerimenti</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Stato</label>
          <div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="stato" id="addStatoPartecipa" value="partecipa">
              <label class="form-check-label" for="addStatoPartecipa"><i class="bi bi-check-circle text-success"></i> Partecipa</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="stato" id="addStatoForse" value="forse" checked>
              <label class="form-check-label" for="addStatoForse"><i class="bi bi-question-circle text-warning"></i> Forse</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="stato" id="addStatoAssente" value="assente">
              <label class="form-check-label" for="addStatoAssente"><i class="bi bi-x-circle text-danger"></i> Assente</label>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Note</label>
          <textarea name="note" class="form-control bg-secondary text-white"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Aggiungi</button>
      </div>
    </form>
</div>
</div>

<!-- Modal modifica salvadanaio/etichetta -->
<div class="modal fade" id="seModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="seForm">
      <div class="modal-header">
        <h5 class="modal-title">Salvadanaio &amp; Etichetta</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_e2se" id="id_e2se">
        <input type="hidden" name="id_evento" value="<?= (int)$id ?>">
        <div class="mb-3 select-search">
          <label class="form-label">Salvadanaio</label>
          <input type="text" class="form-control bg-secondary text-white mb-2" placeholder="Cerca">
          <select name="id_salvadanaio" id="seSalvadanaio" class="form-select bg-secondary text-white">
            <option value=""></option>
            <?php foreach ($salvadanaiTutti as $s): ?>
              <option value="<?= (int)$s['id_salvadanaio'] ?>"><?= htmlspecialchars($s['nome_salvadanaio']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3 select-search">
          <label class="form-label">Etichetta</label>
          <input type="text" class="form-control bg-secondary text-white mb-2" placeholder="Cerca">
          <select name="id_etichetta" id="seEtichetta" class="form-select bg-secondary text-white">
            <option value=""></option>
            <?php foreach ($etichetteTutte as $et): ?>
              <option value="<?= (int)$et['id_etichetta'] ?>"><?= htmlspecialchars($et['descrizione']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger me-auto" id="deleteSeBtn">Elimina</button>
        <button type="submit" class="btn btn-primary">Salva</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal aggiungi salvadanaio/etichetta -->
<div class="modal fade" id="addSeModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="addSeForm">
      <div class="modal-header">
        <h5 class="modal-title">Aggiungi salvadanaio/etichetta</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_evento" value="<?= (int)$id ?>">
        <div class="mb-3 select-search">
          <label class="form-label">Salvadanaio</label>
          <input type="text" class="form-control bg-secondary text-white mb-2" placeholder="Cerca">
          <select name="id_salvadanaio" class="form-select bg-secondary text-white">
            <option value=""></option>
            <?php foreach ($salvadanaiAttivi as $s): ?>
              <option value="<?= (int)$s['id_salvadanaio'] ?>"><?= htmlspecialchars($s['nome_salvadanaio']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3 select-search">
          <label class="form-label">Etichetta</label>
          <input type="text" class="form-control bg-secondary text-white mb-2" placeholder="Cerca">
          <select name="id_etichetta" class="form-select bg-secondary text-white">
            <option value=""></option>
            <?php foreach ($etichetteAttive as $et): ?>
              <option value="<?= (int)$et['id_etichetta'] ?>"><?= htmlspecialchars($et['descrizione']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Aggiungi</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal modifica cibo -->
<div class="modal fade" id="ciboModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="ciboForm">
      <div class="modal-header">
        <h5 class="modal-title">Cibo</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_e2c" id="id_e2c">
        <div class="mb-3">
          <label class="form-label">Cibo</label>
          <input type="text" name="cibo" id="ciboNome" list="ciboOptionsEdit" class="form-control bg-secondary text-white">
          <datalist id="ciboOptionsEdit">
            <?php foreach ($ciboDisponibile as $c): ?>
              <option value="<?= htmlspecialchars($c['piatto']) ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <div class="form-text text-white-50">Inizia a digitare e seleziona un cibo dai suggerimenti</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Quantità</label>
          <input type="number" step="0.01" name="quantita" id="ciboQuantita" class="form-control bg-secondary text-white">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal aggiungi cibo -->
<div class="modal fade" id="addCiboModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="addCiboForm">
      <div class="modal-header">
        <h5 class="modal-title">Aggiungi cibo</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id_evento" value="<?= (int)$id ?>">
        <div class="mb-3">
          <label class="form-label">Cibo</label>
          <input type="text" name="cibo" list="ciboOptions" class="form-control bg-secondary text-white">
          <datalist id="ciboOptions">
            <?php foreach ($ciboDisponibile as $c): ?>
              <option value="<?= htmlspecialchars($c['piatto']) ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <div class="form-text text-white-50">Inizia a digitare e seleziona un cibo dai suggerimenti</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Quantità</label>
          <input type="number" step="0.01" name="quantita" class="form-control bg-secondary text-white">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Aggiungi</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal elimina evento -->
<div class="modal fade" id="deleteEventoModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title">Conferma eliminazione</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">Sei sicuro di voler eliminare questo evento?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteEventoBtn">Elimina</button>
      </div>
    </div>
  </div>
</div>

<style>
#luoghiList .list-group-item,
#invitatiList .list-group-item,
#ciboList .list-group-item,
#seList .list-group-item { padding: 0.25rem 0.5rem; }
#seList .importo { min-width: 80px; text-align: right; display: inline-block; }
#luoghiList .luogo-row { cursor: pointer; }
#invitatiList .inv-row { cursor: pointer; }
#ciboList .cibo-row { cursor: pointer; }
#seList .se-row { cursor: pointer; }
</style>
<script src="js/eventi_dettaglio.js"></script>
<?php include 'includes/footer.php'; ?>
