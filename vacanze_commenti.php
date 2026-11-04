<?php
include 'includes/session_check.php';
include 'includes/db.php';
require_once 'includes/permissions.php';

if (!has_permission($conn, 'table:viaggi', 'view')) {
    http_response_code(403);
    exit('Accesso negato');
}
$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT titolo FROM viaggi WHERE id_viaggio=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$viaggio = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$viaggio) {
    http_response_code(404);
    exit('Vacanza non trovata');
}
$canComment = has_permission($conn, 'table:viaggi_commenti', 'insert');
if (empty($_SESSION['vacanze_csrf'])) {
    $_SESSION['vacanze_csrf'] = bin2hex(random_bytes(32));
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canComment) {
        http_response_code(403);
        exit('Accesso negato');
    }
    if (!hash_equals($_SESSION['vacanze_csrf'], (string)($_POST['vacanze_csrf'] ?? ''))) {
        http_response_code(403);
        exit('Token non valido');
    }
    $testo = trim((string)($_POST['testo'] ?? ''));
    $visibilita = (string)($_POST['visibilita'] ?? 'interno');
    $alt = (int)($_POST['id_viaggio_alternativa'] ?? 0);
    if ($testo === '' || mb_strlen($testo, 'UTF-8') > 5000 || !in_array($visibilita, ['interno','pubblico'], true)) {
        $error = 'Inserisci un commento valido (massimo 5000 caratteri).';
    } else {
        if ($alt > 0) {
            $check = $conn->prepare('SELECT 1 FROM viaggi_alternative WHERE id_viaggio=? AND id_viaggio_alternativa=?');
            $check->bind_param('ii', $id, $alt);
            $check->execute();
            $validAlt = (bool)$check->get_result()->fetch_row();
            $check->close();
            if (!$validAlt) {
                $error = 'Lo scenario selezionato non appartiene alla vacanza.';
            }
        }
        if ($error === '') {
            $userId = (int)$_SESSION['utente_id'];
            $altOrNull = $alt > 0 ? $alt : null;
            $ins = $conn->prepare('INSERT INTO viaggi_commenti (id_viaggio,id_viaggio_alternativa,id_utente,visibilita,testo) VALUES (?,?,?,?,?)');
            $ins->bind_param('iiiss', $id, $altOrNull, $userId, $visibilita, $testo);
            $ins->execute();
            $ins->close();
            header('Location: vacanze_commenti.php?id=' . $id);
            exit;
        }
    }
}
$altStmt = $conn->prepare('SELECT id_viaggio_alternativa,breve_descrizione FROM viaggi_alternative WHERE id_viaggio=? ORDER BY id_viaggio_alternativa');
$altStmt->bind_param('i', $id);
$altStmt->execute();
$alternative = $altStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$altStmt->close();
$comments = $conn->prepare('SELECT c.testo,c.visibilita,c.creato_il,c.id_viaggio_alternativa,u.username,a.breve_descrizione FROM viaggi_commenti c JOIN utenti u ON u.id=c.id_utente LEFT JOIN viaggi_alternative a ON a.id_viaggio_alternativa=c.id_viaggio_alternativa AND a.id_viaggio=c.id_viaggio WHERE c.id_viaggio=? ORDER BY c.creato_il DESC,c.id_commento DESC');
$comments->bind_param('i', $id);
$comments->execute();
$rows = $comments->get_result()->fetch_all(MYSQLI_ASSOC);
$comments->close();
include 'includes/header.php';
?>
<div class="container text-white my-3">
  <a class="btn btn-outline-light mb-3" href="vacanze_workspace.php?id=<?= $id ?>">← Vacanza</a>
  <h4>Commenti: <?= htmlspecialchars($viaggio['titolo'], ENT_QUOTES, 'UTF-8') ?></h4>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <?php if ($canComment): ?>
  <form method="post" class="border rounded p-3 mb-4">
    <input type="hidden" name="vacanze_csrf" value="<?= htmlspecialchars($_SESSION['vacanze_csrf'], ENT_QUOTES, 'UTF-8') ?>">
    <label class="form-label" for="testo">Nuovo commento</label>
    <textarea id="testo" name="testo" class="form-control mb-3" maxlength="5000" required><?= htmlspecialchars((string)($_POST['testo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
    <label class="form-label" for="scenario">Scenario</label>
    <select id="scenario" name="id_viaggio_alternativa" class="form-select mb-3">
      <option value="0">Intera vacanza</option>
      <?php foreach ($alternative as $a): ?>
      <option value="<?= (int)$a['id_viaggio_alternativa'] ?>"><?= htmlspecialchars($a['breve_descrizione'], ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
    <label class="form-label" for="visibilita">Visibilità</label>
    <select id="visibilita" name="visibilita" class="form-select mb-3">
      <option value="interno">Solo utenti del portale</option>
      <option value="pubblico">Anche tramite link di condivisione autorizzato</option>
    </select>
    <button type="submit" class="btn btn-primary">Pubblica commento</button>
  </form>
  <?php endif; ?>
  <?php if (!$rows): ?><p class="text-muted">Nessun commento.</p><?php endif; ?>
  <?php foreach ($rows as $row): ?>
  <div class="border rounded p-3 mb-2">
    <div class="small text-secondary mb-2">
      <?= htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') ?>
      · <?= htmlspecialchars($row['creato_il'], ENT_QUOTES, 'UTF-8') ?>
      · <?= $row['visibilita'] === 'interno' ? 'Interno' : 'Condivisibile' ?>
      <?php if ($row['breve_descrizione'] !== null): ?> · <?= htmlspecialchars($row['breve_descrizione'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
    </div>
    <div><?= nl2br(htmlspecialchars($row['testo'], ENT_QUOTES, 'UTF-8')) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php include 'includes/footer.php'; ?>
