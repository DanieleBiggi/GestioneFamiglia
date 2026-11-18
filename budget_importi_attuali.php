<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
include 'includes/header.php';

$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$updateSuccess = false;

$salvStmt = $conn->prepare('SELECT DISTINCT s.id_salvadanaio, s.nome_salvadanaio, s.importo_attuale, s.data_aggiornamento_manuale
    FROM salvadanai s
    JOIN budget b ON b.id_salvadanaio = s.id_salvadanaio
    WHERE b.id_famiglia = ?
      AND (b.data_scadenza IS NULL OR b.data_scadenza >= CURDATE())
    ORDER BY s.nome_salvadanaio');
$salvStmt->bind_param('i', $idFamiglia);
$salvStmt->execute();
$salvadanai = $salvStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$salvStmt->close();

$updatedCount = 0;
$updateSuccess = false;
$saveError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['id_salvadanaio'] ?? [];
    $importi = $_POST['importo_attuale'] ?? [];
    $saved = [];
    foreach ($salvadanai as $r) {
        $saved[(int)$r['id_salvadanaio']] = round((float)$r['importo_attuale'], 2);
    }
    if (!is_array($ids) || !is_array($importi) || count($ids) !== count($importi)) {
        $saveError = 'Dati del modulo non validi.';
    } else {
        // Check all values before updating any record. Unchanged values are not written.
        $changes = [];
        foreach ($ids as $index => $idValue) {
            $id = filter_var($idValue, FILTER_VALIDATE_INT);
            $raw = $importi[$index] ?? null;
            if (!$id || !array_key_exists($id, $saved) || !is_scalar($raw) ||
                !is_numeric($raw) || !is_finite((float)$raw) || (float)$raw < 0 || (float)$raw > 99999999) {
                $saveError = 'Controlla gli importi inseriti.';
                break;
            }
            $amount = round((float)$raw, 2);
            if (abs($amount - $saved[$id]) >= 0.005) $changes[$id] = $amount;
        }
        if (!$saveError && $changes) {
            try {
                $conn->begin_transaction();
                $stmt = $conn->prepare('UPDATE salvadanai SET importo_attuale=?, data_aggiornamento_manuale=? WHERE id_salvadanaio=?');
                $now = date('Y-m-d H:i:s');
                foreach ($changes as $id => $amount) {
                    $stmt->bind_param('dsi', $amount, $now, $id);
                    if (!$stmt->execute()) throw new RuntimeException('Salvataggio non riuscito.');
                    $updatedCount++;
                }
                $stmt->close();
                $conn->commit();
                $updateSuccess = true;
                foreach ($salvadanai as &$item) {
                    $id = (int)$item['id_salvadanaio'];
                    if (isset($changes[$id])) {
                        $item['importo_attuale'] = $changes[$id];
                        $item['data_aggiornamento_manuale'] = $now;
                    }
                }
                unset($item);
            } catch (Throwable $e) {
                $conn->rollback();
                $updatedCount = 0;
                $saveError = 'Impossibile aggiornare i saldi.';
            }
        } elseif (!$saveError) {
            $updateSuccess = true;
        }
    }
}

?>
<style>
.pocket-balance-grid {display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:.6rem}
.pocket-balance-item {border:1px solid var(--bs-secondary);border-radius:.5rem;padding:.65rem;background:rgba(255,255,255,.025)}
.pocket-balance-item .form-control {min-width:0}
@media (min-width:992px) {
  .pocket-balance-grid {grid-template-columns:repeat(4,minmax(0,1fr));gap:.4rem}
  .pocket-balance-item {padding:.4rem .55rem}
  .pocket-balance-item .form-control {padding:.22rem .4rem;min-height:32px}
  .pocket-balance-toolbar {margin-bottom:.5rem!important}
}
@media (min-width:1500px) {.pocket-balance-grid {grid-template-columns:repeat(5,minmax(0,1fr))}}
</style>
<div class="d-flex flex-wrap gap-2 mb-2 justify-content-between align-items-center pocket-balance-toolbar">
  <h4 class="mb-0">Saldi Pocket Revolut</h4>
  <a href="budget_dashboard.php" class="btn btn-outline-light btn-sm">Torna alla dashboard</a>
</div>
<?php if ($updateSuccess): ?>
  <div class="alert alert-success py-2" role="status"><?= $updatedCount ? (int)$updatedCount . ' saldi aggiornati.' : 'Nessuna variazione: i saldi erano già aggiornati.' ?></div>
<?php endif; ?>
<?php if ($saveError): ?>
  <div class="alert alert-danger py-2" role="alert"><?= htmlspecialchars($saveError) ?></div>
<?php endif; ?>
<p class="small text-white-50 mb-2">Importi precompilati dai saldi attuali in app. Modifica solo quelli diversi da Revolut: gli altri e le loro date di verifica rimangono invariati.</p>
<form method="post" autocomplete="off">
  <div class="pocket-balance-grid">
  <?php if (empty($salvadanai)): ?>
    <p class="text-white-50">Nessun salvadanaio disponibile.</p>
  <?php else: ?>
    <?php foreach ($salvadanai as $salvadanaio): ?>
    <div class="pocket-balance-item text-white">
      <label class="form-label fw-semibold small mb-1 d-block text-truncate" title="<?= htmlspecialchars($salvadanaio['nome_salvadanaio'] ?? '',ENT_QUOTES) ?>" for="saldo-<?= (int)$salvadanaio['id_salvadanaio'] ?>"><?= htmlspecialchars($salvadanaio['nome_salvadanaio'] ?? '') ?></label>
      <input type="hidden" name="id_salvadanaio[]" value="<?= (int)$salvadanaio['id_salvadanaio'] ?>">
      <div class="input-group input-group-sm">
        <input id="saldo-<?= (int)$salvadanaio['id_salvadanaio'] ?>" type="number" step="0.01" min="0" inputmode="decimal" name="importo_attuale[]" value="<?= number_format((float)$salvadanaio['importo_attuale'],2,'.','') ?>" required class="form-control bg-dark text-white border-secondary" aria-label="Saldo Pocket <?= htmlspecialchars($salvadanaio['nome_salvadanaio'] ?? '',ENT_QUOTES) ?>">
        <span class="input-group-text bg-secondary text-white">€</span>
      </div>
      <div class="small text-white-50 mt-1">Verificato: <?= !empty($salvadanaio['data_aggiornamento_manuale']) ? date('d/m/Y',strtotime($salvadanaio['data_aggiornamento_manuale'])) : 'mai' ?></div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
  </div>
  <div class="d-flex justify-content-end mt-2">
    <button type="submit" class="btn btn-primary px-4">Salva modifiche</button>
  </div>
</form>
<?php include 'includes/footer.php'; ?>
