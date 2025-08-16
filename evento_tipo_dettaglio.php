<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:eventi_tipi.php', 'view')) { http_response_code(403); exit('Accesso negato'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$data = [
    'id' => 0,
    'tipo_evento' => '',
    'colore' => '#71843f',
    'colore_testo' => '#ffffff',
    'attivo' => 1
];
if ($id > 0) {
    $stmt = $conn->prepare('SELECT * FROM eventi_tipi_eventi WHERE id=?');
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
        $stmt = $conn->prepare('DELETE FROM eventi_tipi_eventi WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        header('Location: eventi_tipi.php');
        exit;
    }
    $tipo = $_POST['tipo_evento'] ?? '';
    $colore = $_POST['colore'] ?? '';
    $coloreTesto = $_POST['colore_testo'] ?? '';
    $attivo = isset($_POST['attivo']) ? 1 : 0;
    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE eventi_tipi_eventi SET tipo_evento=?, colore=?, colore_testo=?, attivo=? WHERE id=?');
        $stmt->bind_param('sssii', $tipo, $colore, $coloreTesto, $attivo, $id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare('INSERT INTO eventi_tipi_eventi (tipo_evento, colore, colore_testo, attivo) VALUES (?,?,?,?)');
        $stmt->bind_param('sssi', $tipo, $colore, $coloreTesto, $attivo);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: eventi_tipi.php');
    exit;
}

include 'includes/header.php';
?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">&larr; Indietro</a>
  <h4 class="mb-4"><?= $id > 0 ? 'Modifica tipo evento' : 'Nuovo tipo evento' ?></h4>
</div>
<form id="eventoTipoForm" method="post" class="bg-dark text-white p-3 rounded">
  <div class="mb-3">
    <label class="form-label">Tipo evento</label>
    <input type="text" name="tipo_evento" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['tipo_evento']) ?>" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Colore sfondo</label>
    <input type="color" name="colore" class="form-control form-control-color" value="<?= htmlspecialchars($data['colore']) ?>" title="Scegli colore">
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
      <button type="button" class="btn btn-danger me-auto" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">Elimina</button>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary ms-auto">Salva</button>
    </div>
  </form>

  <!-- Modal conferma eliminazione -->
  <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content bg-dark text-white">
        <div class="modal-header border-secondary">
          <h5 class="modal-title" id="confirmDeleteLabel">Conferma eliminazione</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
        </div>
        <div class="modal-body">
          Sei sicuro di voler eliminare questo tipo di evento?
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" form="eventoTipoForm" name="delete" value="1" class="btn btn-danger">Elimina</button>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/footer.php'; ?>
