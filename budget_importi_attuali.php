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

            if (!is_numeric($importo) || (float)$importo < 0) { continue; }
            $amount = round((float)$importo,2);
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
<p class="small text-white-50">Confronta i saldi con i Pocket Revolut e aggiorna solo quelli che hai verificato. Gli importi lasciati vuoti non vengono modificati.</p>
<form method="post">
  <div class="row g-3">
  <?php if (empty($salvadanai)): ?>
    <p class="text-white-50">Nessun salvadanaio disponibile.</p>
  <?php else: ?>
    <?php foreach ($salvadanai as $salvadanaio): ?>
    <div class="col-12 col-md-6 col-xl-4">
      <div class="card bg-dark border-secondary text-white p-3 h-100">
        <label class="form-label fw-semibold" for="saldo-<?= (int)$salvadanaio['id_salvadanaio'] ?>"><?= htmlspecialchars($salvadanaio['nome_salvadanaio'] ?? '') ?></label>
        <div class="small text-white-50 mb-2">Saldo attuale in app: <?= number_format((float)$salvadanaio['importo_attuale'],2,',','.') ?> €</div>
        <input type="hidden" name="id_salvadanaio[]" value="<?= (int)$salvadanaio['id_salvadanaio'] ?>">
        <div class="input-group">
          <input id="saldo-<?= (int)$salvadanaio['id_salvadanaio'] ?>" type="number" step="0.01" min="0" inputmode="decimal" name="importo_attuale[]" value="" placeholder="Nuovo saldo Revolut" class="form-control bg-dark text-white border-secondary" aria-label="Nuovo saldo Pocket <?= htmlspecialchars($salvadanaio['nome_salvadanaio'] ?? '',ENT_QUOTES) ?>">
          <span class="input-group-text bg-secondary text-white">€</span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
  </div>
  <div class="sticky-bottom bg-dark border-top border-secondary py-3 mt-3">
    <button type="submit" class="btn btn-primary w-100 py-3">Salva i saldi verificati</button>
  </div>
</form>
<?php include 'includes/footer.php'; ?>
