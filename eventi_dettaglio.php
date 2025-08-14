<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:eventi.php', 'view')) { http_response_code(403); exit('Accesso negato'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT e.*, t.tipo_evento, t.colore FROM eventi e LEFT JOIN eventi_tipi_eventi t ON e.id_tipo_evento = t.id WHERE e.id = ?");
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

include 'includes/header.php';
?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <div class="d-flex align-items-center mb-3">
    <h4 class="mb-0 me-2" id="eventoTitolo"><?= htmlspecialchars($evento['titolo'] ?? '') ?></h4>
    <?php if ($canUpdate): ?>
      <i class="bi bi-pencil-square" id="editEventoBtn" style="cursor:pointer"></i>
    <?php endif; ?>
  </div>
  <?php if (!empty($evento['data_evento']) || !empty($evento['ora_evento'])): ?>
    <div class="mb-3"><?= htmlspecialchars(trim(($evento['data_evento'] ?? '') . ' ' . ($evento['ora_evento'] ?? ''))) ?></div>
  <?php endif; ?>
  <?php if (!empty($evento['descrizione'])): ?>
    <p><?= nl2br(htmlspecialchars($evento['descrizione'])) ?></p>
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

<style>
#invitatiList .list-group-item,
#ciboList .list-group-item { padding: 0.25rem 0.5rem; }
#invitatiList .inv-row { cursor: pointer; }
#ciboList .cibo-row { cursor: pointer; }
</style>
<script src="js/eventi_dettaglio.js"></script>
<?php include 'includes/footer.php'; ?>
