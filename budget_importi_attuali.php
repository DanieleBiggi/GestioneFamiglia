<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
include 'includes/header.php';

$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$updateSuccess = false;

$salvStmt = $conn->prepare('SELECT DISTINCT s.id_salvadanaio, s.nome_salvadanaio, s.importo_attuale
    FROM salvadanai s
    JOIN budget b ON b.id_salvadanaio = s.id_salvadanaio
    WHERE b.id_famiglia = ?
      AND (b.data_scadenza IS NULL OR b.data_scadenza >= CURDATE())
    ORDER BY s.nome_salvadanaio');
$salvStmt->bind_param('i', $idFamiglia);
$salvStmt->execute();
$salvadanai = $salvStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$salvStmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['id_salvadanaio'] ?? [];
    $importi = $_POST['importo_attuale'] ?? [];
    $allowedIds = array_column($salvadanai, 'id_salvadanaio');

    if (is_array($ids) && is_array($importi) && !empty($ids) && !empty($allowedIds)) {
        $stmt = $conn->prepare('UPDATE salvadanai SET importo_attuale = ?, data_aggiornamento_manuale = ? WHERE id_salvadanaio = ?');
        $now = date('Y-m-d H:i:s');

        foreach ($ids as $index => $idSalvadanaio) {
            if (!isset($importi[$index])) {
                continue;
            }

            $id = (int)$idSalvadanaio;
            $importo = $importi[$index];

            if ($id <= 0 || $importo === '' || !in_array($id, $allowedIds, true)) {
                continue;
            }

            $amount = (float)$importo;
            $stmt->bind_param('dsi', $amount, $now, $id);
            $stmt->execute();
        }

        $stmt->close();
        $updateSuccess = true;

        $salvStmt = $conn->prepare('SELECT DISTINCT s.id_salvadanaio, s.nome_salvadanaio, s.importo_attuale
            FROM salvadanai s
            JOIN budget b ON b.id_salvadanaio = s.id_salvadanaio
            WHERE b.id_famiglia = ?
              AND (b.data_scadenza IS NULL OR b.data_scadenza >= CURDATE())
            ORDER BY s.nome_salvadanaio');
        $salvStmt->bind_param('i', $idFamiglia);
        $salvStmt->execute();
        $salvadanai = $salvStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $salvStmt->close();
    }
}
?>
<div class="d-flex mb-3 justify-content-between align-items-center">
  <h4 class="mb-0">Modifica importi attuali</h4>
  <a href="budget_dashboard.php" class="btn btn-outline-light btn-sm">Torna alla dashboard</a>
</div>
<?php if ($updateSuccess): ?>
<div class="alert alert-success">Importi aggiornati con successo.</div>
<?php endif; ?>
<form method="post">
  <div class="table-responsive">
    <table class="table table-dark table-striped table-sm align-middle">
      <thead>
        <tr>
          <th>Salvadanaio</th>
          <th class="text-end">Importo attuale</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($salvadanai)): ?>
          <tr>
            <td colspan="2" class="text-center">Nessun salvadanaio disponibile</td>
          </tr>
        <?php else: ?>
          <?php foreach ($salvadanai as $salvadanaio): ?>
          <tr>
            <td><?= htmlspecialchars($salvadanaio['nome_salvadanaio'] ?? '') ?></td>
            <td>
              <input type="hidden" name="id_salvadanaio[]" value="<?= (int)$salvadanaio['id_salvadanaio'] ?>">
              <input type="number" step="0.01" name="importo_attuale[]" value="<?= number_format((float)($salvadanaio['importo_attuale'] ?? 0), 2, '.', '') ?>" class="form-control bg-dark text-white border-secondary text-end">
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="d-flex justify-content-end">
    <button type="submit" class="btn btn-outline-light">Salva</button>
  </div>
</form>
<?php include 'includes/footer.php'; ?>
