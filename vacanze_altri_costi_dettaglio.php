<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';

$id = (int)($_GET['id'] ?? 0);
$alt = (int)($_GET['alt'] ?? 0);
$id_costo = (int)($_GET['id_costo'] ?? 0);
$duplica = isset($_GET['duplica']);

// Recupera info viaggio per breadcrumb
$stmt = $conn->prepare('SELECT titolo FROM viaggi WHERE id_viaggio=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$viaggio = $stmt->get_result()->fetch_assoc();
if (!$viaggio) {
    include 'includes/header.php';
    echo '<p class="text-danger">Viaggio non trovato</p>';
    include 'includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_costo = (int)($_POST['id_costo'] ?? 0);
    $id_alt = (int)($_POST['id_viaggio_alternativa'] ?? $alt);
    $data = $_POST['data'] ?: null;
    $importo = $_POST['importo_eur'] !== '' ? (float)$_POST['importo_eur'] : null;
    $note = $_POST['note'] ?? null;

    if (isset($_POST['delete']) && $id_costo) {
        $del = $conn->prepare('DELETE FROM viaggi_altri_costi WHERE id_costo=? AND id_viaggio=?');
        $del->bind_param('ii', $id_costo, $id);
        $del->execute();
    } elseif ($id_costo) {
        $upd = $conn->prepare('UPDATE viaggi_altri_costi SET id_viaggio_alternativa=?, data=?, importo_eur=?, note=? WHERE id_costo=? AND id_viaggio=?');
        $upd->bind_param('isdsii', $id_alt, $data, $importo, $note, $id_costo, $id);
        $upd->execute();
    } else {
        $ins = $conn->prepare('INSERT INTO viaggi_altri_costi (id_viaggio, id_viaggio_alternativa, data, importo_eur, note) VALUES (?,?,?,?,?)');
        $ins->bind_param('iisds', $id, $id_alt, $data, $importo, $note);
        $ins->execute();
    }
    header('Location: vacanze_tratte.php?id=' . $id . '&alt=' . $id_alt);
    exit;
}

$costo = [
    'id_viaggio_alternativa' => $alt,
    'data' => '',
    'importo_eur' => '',
    'note' => '',
];

if ($id_costo) {
    $cStmt = $conn->prepare('SELECT * FROM viaggi_altri_costi WHERE id_costo=? AND id_viaggio=?');
    $cStmt->bind_param('ii', $id_costo, $id);
    $cStmt->execute();
    $costo = $cStmt->get_result()->fetch_assoc();
    if (!$costo) {
        echo '<p class="text-danger">Costo non trovato</p>';
        include 'includes/footer.php';
        exit;
    }
    $alt = (int)$costo['id_viaggio_alternativa'];
    if ($duplica) {
        $id_costo = 0;
    }
}

$altStmt = $conn->prepare('SELECT id_viaggio_alternativa, breve_descrizione FROM viaggi_alternative WHERE id_viaggio=? ORDER BY id_viaggio_alternativa');
$altStmt->bind_param('i', $id);
$altStmt->execute();
$altRes = $altStmt->get_result();
$alternative = [];
while ($row = $altRes->fetch_assoc()) { $alternative[$row['id_viaggio_alternativa']] = $row['breve_descrizione']; }
$alt_desc = $alternative[$alt] ?? '';
?>
<?php include 'includes/header.php'; ?>
<div class="container text-white">
  <a href="vacanze_tratte.php?id=<?= $id ?>&alt=<?= $alt ?>" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-3"><?= $duplica ? 'Duplica' : ($id_costo ? 'Modifica' : 'Nuovo') ?> costo</h4>
  <form method="post">
    <input type="hidden" name="id_costo" value="<?= (int)$id_costo ?>">
    <div class="mb-3">
      <label class="form-label">Alternativa</label>
      <select class="form-select" name="id_viaggio_alternativa">
        <?php foreach ($alternative as $aid => $descr): ?>
          <option value="<?= $aid ?>"<?= $costo['id_viaggio_alternativa']==$aid ? ' selected' : '' ?>><?= htmlspecialchars($descr) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Data</label>
      <input type="date" class="form-control" name="data" value="<?= htmlspecialchars($costo['data']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Importo €</label>
      <input type="number" step="0.01" class="form-control" name="importo_eur" value="<?= htmlspecialchars($costo['importo_eur']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Note</label>
      <textarea class="form-control" name="note"><?= htmlspecialchars($costo['note']) ?></textarea>
    </div>
    <div class="d-flex justify-content-between mt-3">
      <button type="submit" class="btn btn-primary">Salva</button>
      <?php if ($id_costo && !$duplica): ?>
        <button type="submit" name="delete" value="1" class="btn btn-danger">Elimina</button>
      <?php endif; ?>
    </div>
  </form>
</div>
<?php include 'includes/footer.php'; ?>
