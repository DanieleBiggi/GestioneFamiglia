<?php
include 'includes/session_check.php';
require_once 'includes/db.php';

$loggedId = $_SESSION['utente_id'] ?? 0;

// verify admin
$stmt = $conn->prepare('SELECT admin FROM utenti WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $loggedId);
$stmt->execute();
$adminRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (($adminRow['admin'] ?? 0) != 1) {
    http_response_code(403);
    exit('Accesso negato');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetId = (int)($_POST['id_utente'] ?? 0);
    $stmt = $conn->prepare('SELECT id, nome, id_famiglia_gestione FROM utenti WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $targetId);
    $stmt->execute();
    if ($user = $stmt->get_result()->fetch_assoc()) {
        $_SESSION['impersonator_id'] = $loggedId;
        $_SESSION['utente_id'] = $user['id'];
        $_SESSION['utente_nome'] = $user['nome'];
        $_SESSION['id_famiglia_gestione'] = $user['id_famiglia_gestione'] ?? 0;
    }
    $stmt->close();
    header('Location: index.php');
    exit;
}

$stmt = $conn->query('SELECT id, nome, cognome FROM utenti WHERE attivo = 1 ORDER BY nome');
$users = $stmt ? $stmt->fetch_all(MYSQLI_ASSOC) : [];
?>
<?php include 'includes/header.php'; ?>
<div class="text-white">
  <h4 class="mb-3">Impersona utente</h4>
  <form method="post" class="mb-3">
    <select name="id_utente" class="form-select w-auto d-inline">
      <?php foreach ($users as $u): ?>
        <option value="<?= (int)$u['id'] ?>" <?= $u['id']==$loggedId ? 'disabled' : '' ?>><?= htmlspecialchars(trim(($u['nome'] ?? '') . ' ' . ($u['cognome'] ?? ''))) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary ms-2">Impersona</button>
  </form>
  <?php if (isset($_SESSION['impersonator_id'])): ?>
  <a href="stop_impersonate.php" class="btn btn-secondary">Torna al tuo account</a>
  <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
