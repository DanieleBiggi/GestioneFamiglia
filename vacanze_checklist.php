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
    <a class="btn btn-sm btn-outline-light" href="table_manager.php?table=viaggi_checklist&id_viaggio=<?= $id ?>">Aggiungi</a>
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
            <button class="btn btn-sm btn-outline-light" data-bs-toggle="collapse" data-bs-target="#chat<?= $row['id_checklist'] ?>">Chat</button>
          </div>
          <div class="collapse mt-2" id="chat<?= $row['id_checklist'] ?>">
            <div class="checklist-chat-messages mb-2" data-id="<?= $row['id_checklist'] ?>"></div>
            <div class="input-group input-group-sm">
              <input type="text" class="form-control bg-dark text-white checklist-chat-input" data-id="<?= $row['id_checklist'] ?>" placeholder="Messaggio">
              <button class="btn btn-outline-light checklist-chat-send" data-id="<?= $row['id_checklist'] ?>">Invia</button>
            </div>
          </div>
        </li>
      <?php endwhile; ?>
    </ul>
  <?php endif; ?>
</div>
<script src="js/vacanze_checklist.js"></script>
<?php include 'includes/footer.php'; ?>
