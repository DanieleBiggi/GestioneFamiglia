<?php
include 'includes/session_check.php';
include 'includes/db.php';

$id  = $_GET['id']  ?? null;
$src = $_GET['src'] ?? 'movimenti_revolut';
if (!$id) {
  die("ID mancante");
}

$allowedSources = ['movimenti_revolut', 'bilancio_entrate', 'bilancio_uscite'];
if (!in_array($src, $allowedSources, true)) {
  die("Origine dati non supportata");
}

$movimento = null;
if ($src === 'bilancio_entrate') {
  $stmt = $conn->prepare("
    SELECT be.descrizione_operazione AS description,
           be.descrizione_extra,
           be.note AS note,
           be.importo AS amount,
           be.data_operazione AS started_date,
           be.id_gruppo_transazione,
           g.descrizione AS gruppo_descrizione,
           COALESCE(c.descrizione_categoria, 'Nessuna categoria') AS categoria_descrizione,
           g.tipo_gruppo,
           GROUP_CONCAT(e.descrizione SEPARATOR ', ') AS etichette,
           be.mezzo
    FROM bilancio_entrate be
    LEFT JOIN bilancio_gruppi_transazione g ON be.id_gruppo_transazione = g.id_gruppo_transazione
    LEFT JOIN bilancio_gruppi_categorie c ON g.id_categoria = c.id_categoria
    LEFT JOIN bilancio_etichette2operazioni eo ON eo.id_tabella = be.id_entrata AND eo.tabella_operazione = 'bilancio_entrate'
    LEFT JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
    WHERE be.id_entrata = ?
    GROUP BY be.id_entrata
  ");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result    = $stmt->get_result();
  $movimento = $result->fetch_assoc();
  $stmt->close();
} elseif ($src === 'bilancio_uscite') {
  $stmt = $conn->prepare("
    SELECT bu.descrizione_operazione AS description,
           bu.descrizione_extra,
           bu.note AS note,
           -bu.importo AS amount,
           bu.data_operazione AS started_date,
           bu.id_gruppo_transazione,
           g.descrizione AS gruppo_descrizione,
           COALESCE(c.descrizione_categoria, 'Nessuna categoria') AS categoria_descrizione,
           g.tipo_gruppo,
           GROUP_CONCAT(e.descrizione SEPARATOR ', ') AS etichette,
           bu.mezzo
    FROM bilancio_uscite bu
    LEFT JOIN bilancio_gruppi_transazione g ON bu.id_gruppo_transazione = g.id_gruppo_transazione
    LEFT JOIN bilancio_gruppi_categorie c ON g.id_categoria = c.id_categoria
    LEFT JOIN bilancio_etichette2operazioni eo ON eo.id_tabella = bu.id_uscita AND eo.tabella_operazione = 'bilancio_uscite'
    LEFT JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
    WHERE bu.id_uscita = ?
    GROUP BY bu.id_uscita
  ");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result    = $stmt->get_result();
  $movimento = $result->fetch_assoc();
  $stmt->close();
} else {
  $stmt = $conn->prepare("
    SELECT m.*, g.descrizione AS gruppo_descrizione,
           COALESCE(c.descrizione_categoria, 'Nessuna categoria') AS categoria_descrizione,
           g.tipo_gruppo,
           GROUP_CONCAT(e.descrizione SEPARATOR ', ') AS etichette
    FROM movimenti_revolut m
    LEFT JOIN bilancio_gruppi_transazione g ON m.id_gruppo_transazione = g.id_gruppo_transazione
    LEFT JOIN bilancio_gruppi_categorie c ON g.id_categoria = c.id_categoria
    LEFT JOIN bilancio_etichette2operazioni eo ON eo.id_tabella = m.id_movimento_revolut AND eo.tabella_operazione = 'movimenti_revolut'
    LEFT JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
    WHERE m.id_movimento_revolut = ?
    GROUP BY m.id_movimento_revolut
  ");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result    = $stmt->get_result();
  $movimento = $result->fetch_assoc();
  $stmt->close();
}

if (!$movimento) {
  die("Movimento non trovato");
}
$movimento['categoria_descrizione'] = sanitize_string($movimento['categoria_descrizione'] ?? 'Nessuna categoria');

// Etichette disponibili
$etichette = [];
$res = $conn->query("SELECT id_etichetta, descrizione, attivo FROM bilancio_etichette ORDER BY attivo DESC, descrizione ASC");
while ($row = $res->fetch_assoc()) {
  $row['attivo'] = (int)$row['attivo'];
  $etichette[] = $row;
}

function sanitize_string($str) {
  $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8'); // normalizza la stringa in UTF-8
  $str = preg_replace('/[\x00-\x1F\x7F]/u', '', $str); // rimuove caratteri di controllo
  return $str;
}

foreach ($etichette as &$et) {
  $et['descrizione'] = sanitize_string($et['descrizione']);
}
unset($et); 

function tipo_label($tipo) {
  return [
    'spese_base'   => 'Spese Base',
    'divertimento' => 'Divertimento',
    'risparmio'    => 'Risparmio',
    ''             => 'Altro'
  ][$tipo] ?? $tipo;
}

// Gruppi transazione
$gruppi = [];
$filtroCategoria = $_GET['categoria'] ?? $_POST['categoria'] ?? null;
$sqlGruppi = "SELECT g.id_gruppo_transazione, g.descrizione, g.tipo_gruppo, g.attivo,
              COALESCE(c.descrizione_categoria, 'Nessuna categoria') AS categoria
              FROM bilancio_gruppi_transazione g
              LEFT JOIN bilancio_gruppi_categorie c ON g.id_categoria = c.id_categoria";
if ($filtroCategoria !== null && $filtroCategoria !== '') {
  if ($filtroCategoria === '0') {
    $sqlGruppi .= " WHERE g.id_categoria IS NULL";
  } else {
    $sqlGruppi .= " WHERE g.id_categoria = ?";
  }
}
$sqlGruppi .= " ORDER BY categoria, g.descrizione";
if (isset($filtroCategoria) && $filtroCategoria !== '' && $filtroCategoria !== '0') {
  $stmtGrp = $conn->prepare($sqlGruppi);
  $stmtGrp->bind_param('i', $filtroCategoria);
  $stmtGrp->execute();
  $res2 = $stmtGrp->get_result();
  $stmtGrp->close();
} else {
  $res2 = $conn->query($sqlGruppi);
}
while ($row = $res2->fetch_assoc()) {

  $row['attivo'] = (int)$row['attivo'];

  $row['descrizione'] = sanitize_string($row['descrizione']);
  $row['categoria'] = sanitize_string($row['categoria']);
  $row['tipo_label'] = tipo_label($row['tipo_gruppo']);
  $gruppi[] = $row;
}

$movimento['tipo_gruppo_label'] = tipo_label($movimento['tipo_gruppo'] ?? '');

include 'includes/header.php';
?>

<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-4">Dettaglio movimento</h4>

  <ul class="list-group list-group-flush">
    <li class="list-group-item bg-dark text-white d-flex justify-content-between align-items-center">
      <span>Descrizione</span>
      <span><?= htmlspecialchars($movimento['description']) ?></span>
    </li>
    <li class="list-group-item bg-dark text-white d-flex justify-content-between align-items-center">
      <span>Importo</span>
      <span><?= number_format($movimento['amount'], 2, ',', '.') ?> €</span>
    </li>
    <li class="list-group-item bg-dark text-white d-flex justify-content-between align-items-center">
      <span>Data</span>
      <span><?= date('d/m/Y H:i', strtotime($movimento['started_date'])) ?></span>
    </li>

    <!-- Campo modificabile: descrizione_extra -->
    <li class="list-group-item bg-dark text-white d-flex justify-content-between align-items-center">
      <span>Descrizione Extra</span>
      <span>
        <?= htmlspecialchars($movimento['descrizione_extra']) ?>
        <i class="bi bi-pencil ms-2" onclick="openModal('descrizione_extra', '<?= htmlspecialchars($movimento['descrizione_extra'], ENT_QUOTES) ?>')"></i>
      </span>
    </li>

    <!-- Campo modificabile: note -->
    <li class="list-group-item bg-dark text-white d-flex justify-content-between align-items-center">
      <span>Note</span>
      <span>
        <?= htmlspecialchars($movimento['note']) ?>
        <i class="bi bi-pencil ms-2" onclick="openModal('note', '<?= htmlspecialchars($movimento['note'], ENT_QUOTES) ?>')"></i>
      </span>
    </li>

    <!-- Campo modificabile: gruppo transazione -->
    <li class="list-group-item bg-dark text-white d-flex justify-content-between align-items-center">
      <span>Gruppo</span>
      <span>
        <?= htmlspecialchars($movimento['gruppo_descrizione']) ?>
        <small>(<?= htmlspecialchars($movimento['categoria_descrizione']) ?> - <?= htmlspecialchars($movimento['tipo_gruppo_label']) ?>)</small>
        <i class="bi bi-pencil ms-2" onclick="openGruppiModal(<?= $movimento['id_gruppo_transazione'] ?>)"></i>
      </span>
    </li>

    <!-- Campo modificabile: etichette -->
    <li class="list-group-item bg-dark text-white d-flex justify-content-between align-items-center">
      <span>Etichette</span>
      <span>
        <?= htmlspecialchars($movimento['etichette']) ?>
        <i class="bi bi-pencil ms-2" onclick="openEtichetteModal()"></i>
      </span>
    </li>
  </ul>
  <?php if (in_array($src, ['bilancio_entrate', 'bilancio_uscite'], true) && ($movimento['mezzo'] ?? '') === 'contanti'): ?>
    <button class="btn btn-danger w-100 mt-3" id="deleteMovimento">Elimina movimento</button>
  <?php endif; ?>
</div>

<!-- Modal generico -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" onsubmit="saveField(event)">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Modifica</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="fieldName">
        <textarea id="fieldValue" class="form-control bg-secondary text-white"></textarea>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>

<!-- Gruppi modal -->
<div class="modal fade" id="gruppiModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content bg-dark text-white" onsubmit="saveField(event)">
      <div class="modal-header">
        <h5 class="modal-title">Seleziona gruppo</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex mb-2">
          <input type="text" class="form-control me-2" placeholder="Cerca..." oninput="filterGruppi(this.value)">
          <div class="form-check ms-2">
            <input type="checkbox" class="form-check-input" id="toggleInactiveGruppi" onchange="toggleInactiveGruppi()">
            <label class="form-check-label" for="toggleInactiveGruppi">Vedi anche disattivi</label>
          </div>
        </div>
        <div id="gruppiList" class="row g-3"></div>
        <input type="hidden" id="groupFieldName" value="id_gruppo_transazione">
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>

<!-- Etichette modal -->
<div class="modal fade" id="etichetteModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title">Seleziona etichette</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center gap-2 mb-2">
          <input type="text" class="form-control flex-grow-1" placeholder="Cerca..." oninput="filterEtichette(this.value)">
          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="toggleInactive" onchange="toggleInactiveEtichette()">
            <label class="form-check-label" for="toggleInactive">Vedi anche disattive</label>
          </div>
        </div>
        <div id="etichetteList" class="row row-cols-1 row-cols-md-3 g-2"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary w-100" onclick="saveEtichette()">Salva</button>
      </div>
    </div>
  </div>
</div>

<script>
const etichette = <?= json_encode($etichette, JSON_UNESCAPED_UNICODE) ?>;
const gruppi = <?= json_encode($gruppi, JSON_UNESCAPED_UNICODE) ?>;
const idMovimento = <?= (int)$id ?>;
const srcMovimento = <?= json_encode($src) ?>;

let currentGroupId;
let mostraGruppiInattivi = false;
let filtroGruppi = '';
let mostraVecchie = false;
let filtroEtichette = '';

function openModal(field, value) {
  document.getElementById('fieldName').value = field;
  document.getElementById('fieldValue').value = value;
  new bootstrap.Modal(document.getElementById('editModal')).show();
}

function saveField(event) {
  event.preventDefault();
  const fieldEl = document.getElementById('fieldName') || document.getElementById('groupFieldName');
  const field = fieldEl ? fieldEl.value : '';
  let value;
  if (field === 'id_gruppo_transazione') {
    const checked = document.querySelector('#gruppiList input[name="gruppo"]:checked');
    value = checked ? checked.value : null;
  } else {
    value = document.getElementById('fieldValue').value;
  }

  fetch('ajax/update_field.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ id: idMovimento, field, value, src: srcMovimento })
  }).then(() => location.reload());
}

function openGruppiModal(selectedId) {
  currentGroupId = selectedId;
  mostraGruppiInattivi = false;
  filtroGruppi = '';
  document.getElementById('toggleInactiveGruppi').checked = false;
  document.querySelector('#gruppiModal input[type="text"]').value = '';
  renderGruppiList();
  new bootstrap.Modal(document.getElementById('gruppiModal')).show();
}

function renderGruppiList() {
  const list = document.getElementById('gruppiList');
  list.innerHTML = '';

  const categories = new Map();
  for (let g of gruppi) {
    if (!mostraGruppiInattivi && g.attivo != 1) continue;
    const testo = `${g.descrizione} ${g.categoria} ${g.tipo_label}`.toLowerCase();
    if (filtroGruppi && !testo.includes(filtroGruppi)) continue;
    if (!categories.has(g.categoria)) categories.set(g.categoria, []);
    categories.get(g.categoria).push(g);
  }

  for (const [cat, items] of categories) {
    const col = document.createElement('div');
    col.className = 'col-12 col-md-6 col-lg-4 d-flex category-container';

    const box = document.createElement('div');
    box.className = 'category-box w-100';

    const title = document.createElement('div');
    title.className = 'category-title';
    title.textContent = cat;
    box.appendChild(title);

    items.forEach(g => {
      const div = document.createElement('div');
      div.className = 'form-check';
      div.innerHTML = `
        <input class="form-check-input" type="radio" name="gruppo" id="gr_${g.id_gruppo_transazione}" value="${g.id_gruppo_transazione}" ${g.id_gruppo_transazione == currentGroupId ? 'checked' : ''}>
        <label class="form-check-label" for="gr_${g.id_gruppo_transazione}">${g.descrizione}</label>
      `;
      box.appendChild(div);
    });

    col.appendChild(box);
    list.appendChild(col);
  }
}

function filterGruppi(value) {
  filtroGruppi = value.toLowerCase();
  renderGruppiList();
}

function toggleInactiveGruppi() {
  mostraGruppiInattivi = document.getElementById('toggleInactiveGruppi').checked;
  renderGruppiList();
}

function renderEtichetteList() {

  const list = document.getElementById('etichetteList');
  const selected = new Set(Array.from(list.querySelectorAll('input:checked')).map(e => e.value));
  list.innerHTML = '';
  for (let e of etichette) {
    if (!mostraVecchie && e.attivo != 1) continue;
    if (filtroEtichette && !e.descrizione.toLowerCase().includes(filtroEtichette)) continue;
    const div = document.createElement('div');
    div.className = 'col form-check';
    div.innerHTML = `
      <input class="form-check-input" type="checkbox" id="et_${e.id_etichetta}" value="${e.id_etichetta}" ${selected.has(String(e.id_etichetta)) ? 'checked' : ''}>
      <label class="form-check-label" for="et_${e.id_etichetta}">${e.descrizione}</label>
    `;
    list.appendChild(div);
  }
}

function openEtichetteModal() {
  mostraVecchie = false;
  filtroEtichette = '';
  document.getElementById('toggleInactive').checked = false;
  document.querySelector('#etichetteModal input[type="text"]').value = '';
  renderEtichetteList();
  new bootstrap.Modal(document.getElementById('etichetteModal')).show();
}

function filterEtichette(value) {
  filtroEtichette = value.toLowerCase();
  renderEtichetteList();
}

function toggleInactiveEtichette() {
  mostraVecchie = document.getElementById('toggleInactive').checked;
  renderEtichetteList();
}

function saveEtichette() {
  const selected = Array.from(document.querySelectorAll('#etichetteList input:checked')).map(e => e.value);
  fetch('ajax/update_etichette.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ id: idMovimento, etichette: selected, src: srcMovimento })
  }).then(() => location.reload());
}

document.getElementById('deleteMovimento')?.addEventListener('click', () => {
  if (!confirm('Sei sicuro di voler eliminare questo movimento?')) return;
  fetch('ajax/delete_movimento.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ id: idMovimento, src: srcMovimento })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      //window.location.href = 'tutti_movimenti.php';
      history.back();
    }
  });
});
</script>

<?php include 'includes/footer.php'; ?>
