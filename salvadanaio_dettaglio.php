<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:salvadanai.php', 'view')) { http_response_code(403); exit('Accesso negato'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$data = [
    'id_salvadanaio' => 0,
    'nome_salvadanaio' => '',
    'importo_attuale' => 0,
];
if ($id > 0) {
    $stmt = $conn->prepare('SELECT * FROM salvadanai WHERE id_salvadanaio = ?');
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
    $id_salvadanaio = isset($_POST['id_salvadanaio']) ? (int)$_POST['id_salvadanaio'] : 0;
    $nome = $_POST['nome_salvadanaio'] ?? '';
    $importo = isset($_POST['importo_attuale']) ? (float)$_POST['importo_attuale'] : 0;
    $now = date('Y-m-d H:i:s');
    if ($id_salvadanaio > 0) {
        $stmt = $conn->prepare('UPDATE salvadanai SET nome_salvadanaio=?, importo_attuale=?, data_aggiornamento_manuale=? WHERE id_salvadanaio=?');
        $stmt->bind_param('sdsi', $nome, $importo, $now, $id_salvadanaio);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare('INSERT INTO salvadanai (nome_salvadanaio, importo_attuale, data_aggiornamento_manuale) VALUES (?,?,?)');
        $stmt->bind_param('sds', $nome, $importo, $now);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: salvadanai.php');
    exit;
}
include 'includes/header.php';
?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-4"><?= $id > 0 ? 'Modifica Salvadanaio' : 'Nuovo Salvadanaio' ?></h4>
</div>
<form method="post" class="bg-dark text-white p-3 rounded">
  <div class="mb-3">
    <label class="form-label">Nome salvadanaio</label>
    <input type="text" name="nome_salvadanaio" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['nome_salvadanaio']) ?>" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Importo attuale</label>
    <input type="number" step="0.01" name="importo_attuale" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars(number_format((float)$data['importo_attuale'],2,'.','')) ?>">
  </div>
  <?php if ($id > 0): ?>
    <input type="hidden" name="id_salvadanaio" value="<?= (int)$data['id_salvadanaio'] ?>">
  <?php endif; ?>
  <button type="submit" class="btn btn-primary w-100">Salva</button>
</form>
<?php include 'includes/footer.php'; ?>
