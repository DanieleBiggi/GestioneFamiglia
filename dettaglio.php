<?php
include 'includes/session_check.php';
include 'includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
  die("ID mancante");
}

// Dati del movimento
$stmt = $conn->prepare("
  SELECT m.*, g.descrizione AS gruppo_descrizione, GROUP_CONCAT(e.descrizione SEPARATOR ', ') AS etichette
  FROM movimenti_revolut m
  LEFT JOIN bilancio_gruppi_transazione g ON m.id_gruppo_transazione = g.id_gruppo_transazione
  LEFT JOIN bilancio_etichette2operazioni eo ON eo.id_tabella = m.id_movimento_revolut AND eo.tabella_operazione = 'movimenti_revolut'
  LEFT JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
  WHERE m.id_movimento_revolut = ?
  GROUP BY m.id_movimento_revolut
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$movimento = $result->fetch_assoc();
if (!$movimento) {
  die("Movimento non trovato");
}

// Etichette disponibili
$etichette = [];
$res = $conn->query("SELECT id_etichetta, descrizione, attivo FROM bilancio_etichette ORDER BY attivo DESC, descrizione ASC");
while ($row = $res->fetch_assoc()) {
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

// Gruppi transazione
$gruppi = [];
$res2 = $conn->query("SELECT id_gruppo_transazione, descrizione, categoria, attivo FROM bilancio_gruppi_transazione ORDER BY categoria, descrizione");
while ($row = $res2->fetch_assoc()) {
  $gruppi[] = $row;
}

foreach ($gruppi as &$et) {
  $et['descrizione'] = sanitize_string($et['descrizione']);
  $et['categoria'] = sanitize_string($et['categoria']);
}
unset($et);

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
        <i class="bi bi-pencil ms-2" onclick="openSelectModal('id_gruppo_transazione', <?= $movimento['id_gruppo_transazione'] ?>)"></i>
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

<!-- Etichette modal -->
<div class="modal fade" id="etichetteModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title">Seleziona etichette</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="text" class="form-control mb-2" placeholder="Cerca..." oninput="filterEtichette(this.value)">
        <div id="etichetteList" class="d-flex flex-wrap gap-2"></div>
        <div class="form-check mt-2">
          <input type="checkbox" class="form-check-input" id="toggleInactive" onchange="toggleInactiveEtichette()">
          <label class="form-check-label" for="toggleInactive">Mostra etichette vecchie</label>
        </div>
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
let currentGroupId;

function openModal(field, value) {
  document.getElementById('fieldName').value = field;
  document.getElementById('fieldValue').value = value;
  new bootstrap.Modal(document.getElementById('editModal')).show();
}

function saveField(event) {
  event.preventDefault();
  const field = document.getElementById('fieldName').value;
  const value = document.getElementById('fieldValue').value;

  fetch('ajax/update_field.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ id: idMovimento, field, value })
  }).then(() => location.reload());
}

function openSelectModal(field, selectedId) {
  currentGroupId = selectedId;
  const body = document.querySelector('#editModal .modal-body');
  body.innerHTML = `
    <div id="groupSelectContainer"></div>
    <div class="form-check mt-2">
      <input type="checkbox" class="form-check-input" id="toggleInactiveGroups" onchange="populateGroups(this.checked)">
      <label class="form-check-label" for="toggleInactiveGroups">Mostra gruppi non attivi</label>
    </div>
    <input type="hidden" id="fieldName" value="${field}">
  `;
  document.querySelector('#editModal form').onsubmit = saveField;
  populateGroups(false);
  new bootstrap.Modal(document.getElementById('editModal')).show();
}

function populateGroups(showInactive) {
  const container = document.getElementById('groupSelectContainer');
  const grouped = {};
  for (let g of gruppi) {
    if (!showInactive && !g.attivo) continue;
    if (!grouped[g.categoria]) grouped[g.categoria] = [];
    grouped[g.categoria].push(g);
  }
  let html = '<select id="fieldValue" class="form-select">';
  for (let cat in grouped) {
    html += `<optgroup label="${cat}">`;
    for (let g of grouped[cat]) {
      html += `<option value="${g.id_gruppo_transazione}" ${g.id_gruppo_transazione == currentGroupId ? 'selected' : ''}>${g.descrizione}</option>`;
    }
    html += '</optgroup>';
  }
  html += '</select>';
  container.innerHTML = html;
  container.querySelector('select').addEventListener('change', e => currentGroupId = e.target.value);
}

function openEtichetteModal() {
  const list = document.getElementById('etichetteList');
  list.innerHTML = '';
  for (let e of etichette) {
    if (!e.attivo) continue;
    const div = document.createElement('div');
    div.className = 'form-check';
    div.innerHTML = `
      <input class="form-check-input" type="checkbox" id="et_${e.id_etichetta}" value="${e.id_etichetta}">
      <label class="form-check-label" for="et_${e.id_etichetta}">${e.descrizione}</label>
    `;
    list.appendChild(div);
  }
  new bootstrap.Modal(document.getElementById('etichetteModal')).show();
}

function saveEtichette() {
  const selected = Array.from(document.querySelectorAll('#etichetteList input:checked')).map(e => e.value);
  fetch('ajax/update_etichette.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ id: idMovimento, etichette: selected })
  }).then(() => location.reload());
}
</script>

<?php include 'includes/footer.php'; ?>