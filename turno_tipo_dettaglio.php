<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:turni_tipi.php', 'view')) { http_response_code(403); exit('Accesso negato'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$allowedColors = ['#a4bdfc', '#7ae7bf', '#dbadff', '#ff887c', '#fbd75b', '#ffb878', '#46d6db', '#e1e1e1', '#5484ed', '#51b749'];
$data = [
    'id' => 0,
    'descrizione' => '',
    'ora_inizio' => '',
    'ora_fine' => '',
    'colore_bg' => $allowedColors[0],
    'colore_testo' => '#000000',
    'attivo' => 1
];
if ($id > 0) {
    $stmt = $conn->prepare('SELECT * FROM turni_tipi WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $data = $res->fetch_assoc();
    } else {
        include 'includes/header.php';
        echo '<p class="text-danger">Record non trovato.</p>';
        include 'includes/footer.php';
        exit;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (isset($_POST['delete']) && $id > 0) {
        $stmt = $conn->prepare('DELETE FROM turni_tipi WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        header('Location: turni_tipi.php');
        exit;
    }
    $descrizione = $_POST['descrizione'] ?? '';
    $oraInizio = $_POST['ora_inizio'] ?? '';
    $oraFine = $_POST['ora_fine'] ?? '';
    $coloreBg = $_POST['colore_bg'] ?? '';
    if (!in_array($coloreBg, $allowedColors, true)) {
        $coloreBg = $allowedColors[0];
    }
    $coloreTesto = $_POST['colore_testo'] ?? '';
    $attivo = isset($_POST['attivo']) ? 1 : 0;
    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE turni_tipi SET descrizione=?, ora_inizio=?, ora_fine=?, colore_bg=?, colore_testo=?, attivo=? WHERE id=?');
        $stmt->bind_param('ssssssi', $descrizione, $oraInizio, $oraFine, $coloreBg, $coloreTesto, $attivo, $id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare('INSERT INTO turni_tipi (descrizione, ora_inizio, ora_fine, colore_bg, colore_testo, attivo) VALUES (?,?,?,?,?,?)');
        $stmt->bind_param('sssssi', $descrizione, $oraInizio, $oraFine, $coloreBg, $coloreTesto, $attivo);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: turni_tipi.php');
    exit;
}

include 'includes/header.php';
?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">&larr; Indietro</a>
  <h4 class="mb-4"><?= $id > 0 ? 'Modifica tipo turno' : 'Nuovo tipo turno' ?></h4>
</div>
<form method="post" class="bg-dark text-white p-3 rounded">
  <div class="mb-3">
    <label class="form-label">Descrizione</label>
    <input type="text" name="descrizione" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['descrizione']) ?>" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Ora inizio</label>
    <input type="time" name="ora_inizio" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['ora_inizio']) ?>" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Ora fine</label>
    <input type="time" name="ora_fine" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['ora_fine']) ?>" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Colore sfondo</label>
    <select name="colore_bg" class="form-select">
      <?php foreach ($allowedColors as $color): ?>
        <option value="<?= $color ?>" style="background-color: <?= $color ?>; color: #000;" <?= $data['colore_bg'] === $color ? 'selected' : '' ?>><?= $color ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label">Colore testo</label>
    <input type="color" name="colore_testo" class="form-control form-control-color" value="<?= htmlspecialchars($data['colore_testo']) ?>" title="Scegli colore">
  </div>
  <div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" id="attivo" name="attivo" <?= ($data['attivo'] ?? 1) ? 'checked' : '' ?>>
    <label class="form-check-label" for="attivo">Attivo</label>
  </div>
  <?php if ($id > 0): ?>
    <input type="hidden" name="id" value="<?= (int)$data['id'] ?>">
  <?php endif; ?>
  <div class="d-flex">
    <?php if ($id > 0): ?>
    <button type="submit" name="delete" value="1" class="btn btn-danger me-auto">Elimina</button>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary ms-auto">Salva</button>
  </div>
</form>
<?php include 'includes/footer.php'; ?>
