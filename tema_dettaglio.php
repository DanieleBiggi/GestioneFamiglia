<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:temi.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
include 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$data = [
    'id' => 0,
    'nome' => '',
    'background_color' => '#000000',
    'text_color' => '#ffffff',
    'primary_color' => '#000000',
    'secondary_color' => '#000000'
];

if ($id > 0) {
    $stmt = $conn->prepare('SELECT * FROM temi WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $data = $res->fetch_assoc();
    } else {
        echo '<p class="text-danger">Record non trovato.</p>';
        include 'includes/footer.php';
        exit;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nome = $_POST['nome'] ?? '';
    $background = $_POST['background_color'] ?? '';
    $text = $_POST['text_color'] ?? '';
    $primary = $_POST['primary_color'] ?? '';
    $secondary = $_POST['secondary_color'] ?? '';

    if (isset($_POST['delete']) && $id > 0) {
        $stmt = $conn->prepare('DELETE FROM temi WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        header('Location: temi.php');
        exit;
    }

    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE temi SET nome=?, background_color=?, text_color=?, primary_color=?, secondary_color=? WHERE id=?');
        $stmt->bind_param('sssssi', $nome, $background, $text, $primary, $secondary, $id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare('INSERT INTO temi (nome, background_color, text_color, primary_color, secondary_color) VALUES (?,?,?,?,?)');
        $stmt->bind_param('sssss', $nome, $background, $text, $primary, $secondary);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: temi.php');
    exit;
}
?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-4"><?= $id > 0 ? 'Modifica Tema' : 'Nuovo Tema' ?></h4>
</div>
<form method="post" class="bg-dark text-white p-3 rounded">
  <div class="mb-3">
    <label class="form-label">Nome</label>
    <input type="text" name="nome" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['nome']) ?>" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Colore sfondo</label>
    <input type="text" name="background_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['background_color']) ?>" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Colore testo</label>
    <input type="text" name="text_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['text_color']) ?>" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Colore primario</label>
    <input type="text" name="primary_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['primary_color']) ?>" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Colore secondario</label>
    <input type="text" name="secondary_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['secondary_color']) ?>" required>
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
