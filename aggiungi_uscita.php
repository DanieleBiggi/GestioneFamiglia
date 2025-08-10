<?php
include 'includes/session_check.php';
include 'includes/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descrizione = trim($_POST['descrizione'] ?? '');
    $descrizione_extra = trim($_POST['descrizione_extra'] ?? '');
    $importo = $_POST['importo'] ?? 0;
    $note = trim($_POST['note'] ?? '');
    $data_operazione = trim($_POST['data_operazione'] ?? '');

    if ($descrizione === '' || $data_operazione === '') {
        $error = "Descrizione e data operazione sono obbligatorie.";
    } else {
        $data_operazione = date('Y-m-d H:i:s', strtotime($data_operazione));
        $note = $note !== '' ? $note : null;
        $descrizione_extra = $descrizione_extra !== '' ? $descrizione_extra : null;

        $stmt = $conn->prepare("INSERT INTO bilancio_uscite (id_utente, mezzo, descrizione_operazione, descrizione_extra, importo, note, data_operazione) VALUES (?, 'contanti', ?, ?, ?, ?, ?)");
        $stmt->bind_param('issdss', $_SESSION['utente_id'], $descrizione, $descrizione_extra, $importo, $note, $data_operazione);
        $stmt->execute();
        $id = $conn->insert_id;
        $stmt->close();

        header('Location: dettaglio.php?id=' . $id . '&src=bilancio_uscite');
        exit;
    }
}
include 'includes/header.php';
?>

<div class="container text-white">
  <h4 class="mb-4">Nuova uscita</h4>
  <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
  <form method="post">
    <div class="mb-3">
      <label class="form-label">Descrizione</label>
      <input type="text" name="descrizione" class="form-control bg-dark text-white" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Descrizione Extra</label>
      <input type="text" name="descrizione_extra" class="form-control bg-dark text-white">
    </div>
    <div class="mb-3">
      <label class="form-label">Importo</label>
      <input type="number" step="0.01" name="importo" class="form-control bg-dark text-white" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Data Operazione</label>
      <input type="datetime-local" name="data_operazione" class="form-control bg-dark text-white" value="<?php echo date('Y-m-d\\TH:i'); ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Note</label>
      <textarea name="note" class="form-control bg-dark text-white"></textarea>
    </div>
    <button type="submit" class="btn btn-primary w-100">Salva</button>
  </form>
</div>

<?php include 'includes/footer.php'; ?>
