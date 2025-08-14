<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:invitati_eventi.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
include 'includes/header.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare('SELECT id, nome, cognome FROM eventi_invitati WHERE id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();
if (!$inv) { echo '<p class="text-danger">Invitato non trovato.</p>'; include 'includes/footer.php'; exit; }
$famStmt = $conn->prepare('SELECT f.id_i2f, f.id_famiglia, fam.nome_famiglia, f.data_inizio, f.data_fine, f.attivo FROM eventi_invitati2famiglie f JOIN famiglie fam ON f.id_famiglia=fam.id_famiglia WHERE f.id_invitato=? ORDER BY f.data_inizio DESC');
$famStmt->bind_param('i', $id);
$famStmt->execute();
$famRes = $famStmt->get_result();
$families = $famRes->fetch_all(MYSQLI_ASSOC);
$allFamRes = $conn->query('SELECT id_famiglia, nome_famiglia FROM famiglie ORDER BY nome_famiglia');
$allFamilies = $allFamRes ? $allFamRes->fetch_all(MYSQLI_ASSOC) : [];
?>
<div class="d-flex mb-3 align-items-center">
  <h4 class="mb-0 me-2"><?= htmlspecialchars($inv['nome'] . ' ' . $inv['cognome']) ?></h4>
  <button class="btn btn-outline-light btn-sm" onclick="openInvitatoEditModal()"><i class="bi bi-pencil"></i></button>
</div>
<div class="d-flex mb-3 justify-content-between">
  <h5>Famiglie</h5>
  <button type="button" class="btn btn-outline-light btn-sm" onclick="openFamigliaModal()">Aggiungi</button>
</div>
<div id="famiglieList" class="list-group list-group-flush bg-dark">
<?php foreach ($families as $row): ?>
  <div class="list-group-item bg-dark text-white fam-row" data-id="<?= (int)$row['id_i2f'] ?>" data-famiglia="<?= (int)$row['id_famiglia'] ?>" data-inizio="<?= htmlspecialchars($row['data_inizio']) ?>" data-fine="<?= htmlspecialchars($row['data_fine']) ?>" data-attivo="<?= (int)$row['attivo'] ?>" onclick="openFamigliaEdit(this)">
    <div class="fw-semibold"><?= htmlspecialchars($row['nome_famiglia']) ?></div>
    <div class="small">Dal <?= htmlspecialchars($row['data_inizio']) ?> al <?= htmlspecialchars($row['data_fine']) ?></div>
    <?php if (!(int)$row['attivo']): ?><div class="small text-danger">Non attivo</div><?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<div class="modal fade" id="invitatoEditModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="invitatoEditForm">
      <div class="modal-header">
        <h5 class="modal-title">Modifica invitato</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nome</label>
          <input type="text" name="nome" class="form-control bg-secondary text-white" required value="<?= htmlspecialchars($inv['nome']) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Cognome</label>
          <input type="text" name="cognome" class="form-control bg-secondary text-white" required value="<?= htmlspecialchars($inv['cognome']) ?>">
        </div>
        <input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>
<div class="modal fade" id="famigliaModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="famigliaForm">
      <div class="modal-header">
        <h5 class="modal-title">Famiglia</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Famiglia</label>
          <select name="id_famiglia" class="form-select bg-secondary text-white">
            <?php foreach ($allFamilies as $f): ?>
              <option value="<?= (int)$f['id_famiglia'] ?>"><?= htmlspecialchars($f['nome_famiglia']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Data inizio</label>
          <input type="date" name="data_inizio" class="form-control bg-secondary text-white" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Data fine</label>
          <input type="date" name="data_fine" class="form-control bg-secondary text-white" value="9999-12-31">
        </div>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" name="attivo" id="attivo" checked>
          <label class="form-check-label" for="attivo">Attivo</label>
        </div>
        <input type="hidden" name="id_invitato" value="<?= (int)$inv['id'] ?>">
        <input type="hidden" name="id" id="id_i2f">
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-danger me-auto d-none" id="deleteFam">Elimina</button>
        <button type="submit" class="btn btn-primary">Salva</button>
      </div>
    </form>
  </div>
</div>
<script src="js/invitati_eventi_dettaglio.js"></script>
<?php include 'includes/footer.php'; ?>
