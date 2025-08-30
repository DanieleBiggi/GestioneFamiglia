<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:ocr_caricamenti_scontrini.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
$canUpdate = has_permission($conn, 'table:ocr_caricamenti', 'update');
$canDelete = has_permission($conn, 'table:ocr_caricamenti', 'delete');
include 'includes/header.php';

$idUtente = $_SESSION['utente_id'] ?? 0;
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$descrizione = trim($_GET['descrizione'] ?? '');
$order = $_GET['order'] ?? 'data_desc';

$sql = "SELECT id_caricamento, data_caricamento, data_scontrino, descrizione, nome_file, totale_scontrino FROM ocr_caricamenti WHERE id_utente=?";
$types = 'i';
$params = [$idUtente];
if ($from) {
    $sql .= ' AND data_caricamento >= ?';
    $types .= 's';
    $params[] = $from . ' 00:00:00';
}
if ($to) {
    $sql .= ' AND data_caricamento <= ?';
    $types .= 's';
    $params[] = $to . ' 23:59:59';
}
if ($descrizione !== '') {
    $sql .= ' AND descrizione LIKE ?';
    $types .= 's';
    $params[] = '%' . $descrizione . '%';
}

switch ($order) {
    case 'data_asc':
        $sql .= ' ORDER BY data_caricamento ASC';
        break;
    case 'descrizione_asc':
        $sql .= ' ORDER BY descrizione ASC';
        break;
    case 'descrizione_desc':
        $sql .= ' ORDER BY descrizione DESC';
        break;
    default:
        $sql .= ' ORDER BY data_caricamento DESC';
        $order = 'data_desc';
        break;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Scontrini caricati</h4>
</div>

<form id="caricamentoForm" action="ajax/save_caricamento.php" method="post" enctype="multipart/form-data" class="mb-4">
  <input type="hidden" name="id_caricamento" id="id_caricamento" value="">
  <input type="hidden" name="id_supermercato" value="0">
  <div class="row g-2">
    <div class="col-md-4">
      <label class="form-label">File scontrino</label>
      <input type="file" name="nome_file" class="form-control bg-secondary text-white" required>
    </div>
    <div class="col-md-2">
      <label class="form-label">Data scontrino</label>
      <input type="date" name="data_scontrino" class="form-control bg-secondary text-white">
    </div>
    <div class="col-md-2">
      <label class="form-label">Totale</label>
      <input type="number" step="0.01" name="totale_scontrino" class="form-control bg-secondary text-white">
    </div>
    <div class="col-md-3">
      <label class="form-label">Descrizione</label>
      <input type="text" name="descrizione" class="form-control bg-secondary text-white">
    </div>
    <div class="col-md-1 d-flex align-items-end">
      <button type="submit" id="submitBtn" class="btn btn-primary w-100">Carica</button>
    </div>
  </div>
</form>

<form method="get" class="row g-2 mb-3">
  <div class="col-md-3">
    <label class="form-label">Dal</label>
    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control bg-secondary text-white">
  </div>
  <div class="col-md-3">
    <label class="form-label">Al</label>
    <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control bg-secondary text-white">
  </div>
  <div class="col-md-3">
    <label class="form-label">Descrizione</label>
    <input type="text" name="descrizione" value="<?= htmlspecialchars($descrizione) ?>" class="form-control bg-secondary text-white">
  </div>
  <div class="col-md-3">
    <label class="form-label">Ordina per</label>
    <select name="order" class="form-select bg-secondary text-white">
      <option value="data_desc" <?= $order==='data_desc'?'selected':'' ?>>Data caricamento ↓</option>
      <option value="data_asc" <?= $order==='data_asc'?'selected':'' ?>>Data caricamento ↑</option>
      <option value="descrizione_asc" <?= $order==='descrizione_asc'?'selected':'' ?>>Descrizione A→Z</option>
      <option value="descrizione_desc" <?= $order==='descrizione_desc'?'selected':'' ?>>Descrizione Z→A</option>
    </select>
  </div>
  <div class="col-12 text-end">
    <button type="submit" class="btn btn-outline-light mt-2">Filtra</button>
  </div>
</form>

<table class="table table-dark table-striped">
  <thead>
    <tr>
      <th>Data caricamento</th>
      <th>Descrizione</th>
      <th>Totale</th>
      <th>File</th>
      <?php if ($canUpdate || $canDelete): ?>
      <th>Azioni</th>
      <?php endif; ?>
    </tr>
  </thead>
  <tbody>
    <?php while ($row = $res->fetch_assoc()): ?>
    <tr>
      <td><?= htmlspecialchars($row['data_caricamento']) ?></td>
      <td><?= htmlspecialchars($row['descrizione']) ?></td>
      <td><?= number_format($row['totale_scontrino'], 2, ',', '.') ?></td>
      <td>
        <?php if ($row['nome_file']): ?>
        <a href="files/scontrini/<?= urlencode($row['nome_file']) ?>" target="_blank">Apri</a>
        <?php endif; ?>
      </td>
      <?php if ($canUpdate || $canDelete): ?>
      <td>
        <?php if ($canUpdate): ?>
        <button type="button" class="btn btn-sm btn-outline-light me-1 edit-btn"
          data-id="<?= $row['id_caricamento'] ?>"
          data-scontrino="<?= htmlspecialchars($row['data_scontrino'] ? substr($row['data_scontrino'],0,10) : '') ?>"
          data-totale="<?= htmlspecialchars($row['totale_scontrino']) ?>"
          data-descrizione="<?= htmlspecialchars($row['descrizione']) ?>">
          Modifica
        </button>
        <?php endif; ?>
        <?php if ($canDelete): ?>
        <button type="button" class="btn btn-sm btn-outline-danger delete-btn" data-id="<?= $row['id_caricamento'] ?>">Elimina</button>
        <?php endif; ?>
      </td>
      <?php endif; ?>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>
<?php
$stmt->close();
?>
<script>
const form = document.getElementById('caricamentoForm');
const submitBtn = document.getElementById('submitBtn');
form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(form);
  const resp = await fetch('ajax/save_caricamento.php', { method: 'POST', body: fd });
  const data = await resp.json();
  if (data.success) {
    location.reload();
  } else {
    alert(data.error || 'Errore');
  }
});
document.querySelectorAll('.edit-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('id_caricamento').value = btn.dataset.id;
    form.querySelector('[name=data_scontrino]').value = btn.dataset.scontrino;
    form.querySelector('[name=totale_scontrino]').value = btn.dataset.totale;
    form.querySelector('[name=descrizione]').value = btn.dataset.descrizione;
    submitBtn.textContent = 'Salva';
  });
});
document.querySelectorAll('.delete-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    if (!confirm('Eliminare il caricamento?')) return;
    const resp = await fetch('ajax/delete_caricamento.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'id=' + encodeURIComponent(btn.dataset.id)
    });
    const data = await resp.json();
    if (data.success) {
      location.reload();
    } else {
      alert(data.error || 'Errore');
    }
  });
});
</script>
<?php include 'includes/footer.php'; ?>
