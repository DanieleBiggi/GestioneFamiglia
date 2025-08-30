<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT titolo FROM viaggi WHERE id_viaggio=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$viaggio = $stmt->get_result()->fetch_assoc();
if (!$viaggio) {
    echo '<p class="text-danger">Viaggio non trovato</p>';
    include 'includes/footer.php';
    exit;
}

$chkStmt = $conn->prepare('SELECT vc.id_checklist, vc.voce, vc.completata, vc.id_utente, u.username FROM viaggi_checklist vc LEFT JOIN utenti u ON vc.id_utente=u.id WHERE vc.id_viaggio=? ORDER BY vc.id_checklist');
$chkStmt->bind_param('i', $id);
$chkStmt->execute();
$chkRes = $chkStmt->get_result();

$userRes = $conn->query('SELECT id, username FROM utenti ORDER BY username');
$users = $userRes ? $userRes->fetch_all(MYSQLI_ASSOC) : [];
?>
<div class="container text-white">
  <a href="vacanze_view.php?id=<?= $id ?>" class="btn btn-outline-light mb-3">← Indietro</a>
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="vacanze.php">Vacanze</a></li>
      <li class="breadcrumb-item"><a href="vacanze_view.php?id=<?= $id ?>"><?= htmlspecialchars($viaggio['titolo']) ?></a></li>
      <li class="breadcrumb-item active" aria-current="page">Checklist</li>
    </ol>
  </nav>
  <div class="d-flex justify-content-between mb-3">
    <h4 class="m-0">Checklist</h4>
    <button class="btn btn-sm btn-outline-light" id="addChecklistBtn">Aggiungi</button>
  </div>
  <?php if ($chkRes->num_rows === 0): ?>
    <p class="text-muted">Nessuna voce.</p>
  <?php else: ?>
    <ul class="list-group list-group-flush" id="checklistList">
      <?php while ($row = $chkRes->fetch_assoc()): ?>
        <li class="list-group-item bg-dark text-white">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <input type="checkbox" class="form-check-input me-2 checklist-checkbox" data-id="<?= $row['id_checklist'] ?>" <?= $row['completata'] ? 'checked' : '' ?>>
              <?= htmlspecialchars($row['voce']) ?>
              <select class="form-select form-select-sm d-inline-block w-auto ms-2 checklist-user" data-id="<?= $row['id_checklist'] ?>">
                <option value="">--</option>
                <?php foreach ($users as $u): ?>
                  <option value="<?= $u['id'] ?>" <?= ($row['id_utente'] == $u['id']) ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="btn btn-sm btn-outline-light checklist-chat-btn" data-id="<?= $row['id_checklist'] ?>">Chat</button>
          </div>
        </li>
      <?php endwhile; ?>
    </ul>
  <?php endif; ?>
</div>
<div class="modal fade" id="addChecklistModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="addChecklistForm">
      <div class="modal-header">
        <h5 class="modal-title">Nuova voce</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Voce</label>
          <input type="text" name="voce" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Assegna a</label>
          <select name="id_utente" class="form-select">
            <option value="">--</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="submit" class="btn btn-primary">Salva</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="chatModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title">Chat</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="chatMessages" class="mb-3" style="max-height:300px;overflow-y:auto;"></div>
        <div class="input-group">
          <input type="text" id="chatInput" class="form-control bg-dark text-white" placeholder="Messaggio">
          <button class="btn btn-outline-light" id="chatSend">Invia</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>const viaggioId = <?= $id ?>;</script>
<script src="js/vacanze_checklist.js"></script>
<?php include 'includes/footer.php'; ?>
