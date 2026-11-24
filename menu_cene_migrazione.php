<?php
include __DIR__ . '/includes/session_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/menu_cene_migrazione.php';

$familyId = (int)($_SESSION['id_famiglia_gestione'] ?? 0);
if ($familyId <= 0 || (int)($_SESSION['userlevelid'] ?? 0) !== -1
    || !has_permission($conn, 'page:menu_cene_config.php', 'update')) {
    http_response_code(403);
    exit('Accesso negato');
}
header('Cache-Control: no-store');
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
if (empty($_SESSION['menu_migration_csrf'])) {
    $_SESSION['menu_migration_csrf'] = bin2hex(random_bytes(32));
}
$plan = null;
$result = null;
$error = null;
$alreadyMigrated = false;
$activationPlan = null;
$activated = false;
try {
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, ['GET','POST'], true)) {
        http_response_code(405);
        exit('Metodo non consentito');
    }
    foreach (['menu_cene_master','piatti','ricette'] as $table) {
        $stmt = menu_migration_query($conn, "SELECT COUNT(*) AS n FROM $table WHERE id_famiglia=?", 'i', [$familyId]);
        $count = (int)$stmt->get_result()->fetch_assoc()['n'];
        $stmt->close();
        if ($count > 0) $alreadyMigrated = true;
    }
    if ($method === 'POST') {
        $token = $_POST['csrf_token'] ?? null;
        $hash = $_POST['expected_hash'] ?? null;
        if (!is_string($token) || !hash_equals($_SESSION['menu_migration_csrf'], $token)
            || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)
            || ($_POST['confirm'] ?? '') !== '1'
            || ($_POST['family_id'] ?? '') !== (string)$familyId) {
            http_response_code(403);
            exit('Conferma non valida. Ricaricare la pagina.');
        }
        if (($_POST['operation'] ?? 'migrate') === 'activate') {
            menu_migration_activate($conn, $familyId, $hash);
            $activated = true;
        } else {
            if ($alreadyMigrated) throw new RuntimeException('Dati relazionali già presenti.');
            $result = menu_migration_apply($conn, $familyId, $hash);
        }
        unset($_SESSION['menu_migration_csrf']);
        $alreadyMigrated = true;
    } elseif (!$alreadyMigrated) {
        $plan = menu_migration_plan($familyId);
    } else {
        $stmt = menu_migration_query($conn, 'SELECT attivo FROM menu_cene_master WHERE id_famiglia=?','i',[$familyId]);
        $masters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (count($masters) === 1 && (int)$masters[0]['attivo'] === 0) {
            $activationPlan = menu_migration_plan($familyId);
        }
    }
} catch (Throwable $e) {
    error_log('Migrazione menu: ' . $e->getMessage());
    $error = 'Migrazione non eseguita. Verificare i log, la configurazione e la presenza delle nuove tabelle; ricaricare la pagina per una nuova anteprima.';
    $plan = null;
    $activationPlan = null;
}
include __DIR__ . '/includes/header.php';
?>
<h4>Migrazione iniziale del menù</h4>
<p>Famiglia: <?= $escape($familyId) ?>. Operazione riservata agli amministratori.</p>
<p>I file JSON originali e i menù settimanali non saranno modificati. Il master importato resterà inattivo.</p>
<?php if ($error): ?>
<div class="alert alert-danger" role="alert"><?= $escape($error) ?></div>
<?php elseif ($activated): ?>
<div class="alert alert-success">Master attivato. Ora puoi modificare il master dalla configurazione ordinaria.</div>
<?php elseif ($result): ?>
<div class="alert alert-success" role="status">
    Migrazione completata: <?= $escape($result['piatti']) ?> piatti,
    <?= $escape($result['ricette']) ?> ricette,
    <?= $escape($result['occorrenze']) ?> occorrenze.
    Master ID <?= $escape($result['id_master']) ?>, inattivo.
</div>
<a class="btn btn-outline-light" href="menu_cene_migrazione.php">Verifica e attiva il master</a>
<?php elseif ($activationPlan): ?>
<div class="alert alert-info">Master trasferito ma non ancora attivo. Il server confronterà tutti i riferimenti del master con la sorgente prima di attivarlo.</div>
<p>Piatti distinti: <?= count($activationPlan['dishes']) ?>; ricette: <?= count($activationPlan['recipes']) ?>; occorrenze: <?= count($activationPlan['slots']) ?>.</p>
<form method="post">
<input type="hidden" name="operation" value="activate">
<input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['menu_migration_csrf']) ?>">
<input type="hidden" name="expected_hash" value="<?= $escape(menu_migration_hash($activationPlan)) ?>">
<input type="hidden" name="family_id" value="<?= $escape($familyId) ?>">
<div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="confirm" value="1" id="confirmActivation" required>
<label class="form-check-label" for="confirmActivation">Confermo il passaggio al master nel database.</label></div>
<button type="submit" class="btn btn-primary">Attiva master</button>
</form>
<?php elseif ($alreadyMigrated): ?>
<div class="alert alert-warning">Dati relazionali già presenti: nuova migrazione bloccata.</div>
<?php elseif ($plan): ?>
<div class="alert alert-info">Anteprima in sola lettura. Nessun dato è stato trasferito.</div>
<dl>
    <dt>Anno scolastico</dt><dd><?= $escape($plan['master']['anno_scolastico'] ?? '') ?></dd>
    <dt>Data di riferimento</dt><dd><?= $escape($plan['master']['ciclo']['data_riferimento']) ?></dd>
    <dt>Settimana di riferimento</dt><dd><?= $escape($plan['master']['ciclo']['settimana_riferimento']) ?></dd>
    <dt>Piatti distinti</dt><dd><?= count($plan['dishes']) ?></dd>
    <dt>Ricette</dt><dd><?= count($plan['recipes']) ?></dd>
    <dt>Occorrenze nel master</dt><dd><?= count($plan['slots']) ?></dd>
</dl>
<div class="table-responsive">
<table class="table table-dark table-sm">
<thead><tr><th>Stagione</th><th>Settimana</th><th>Giorno</th><th>Tipo</th><th>Piatto</th><th>Ricetta</th></tr></thead>
<tbody>
<?php foreach ($plan['slots'] as $slot): ?>
<tr>
<td><?= $escape($slot['season']) ?></td><td><?= $escape($slot['week']) ?></td>
<td><?= $escape($slot['day']) ?></td><td><?= $escape($slot['type']) ?></td>
<td><?= $escape($plan['dishes'][$slot['key']]) ?></td>
<td><?= isset($plan['recipeMap'][$slot['key']]) ? 'Sì' : 'No' ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['menu_migration_csrf']) ?>">
<input type="hidden" name="expected_hash" value="<?= $escape(menu_migration_hash($plan)) ?>">
<input type="hidden" name="family_id" value="<?= $escape($familyId) ?>">
<div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="confirm" value="1" id="confirmMigration" required>
<label class="form-check-label" for="confirmMigration">Ho verificato l'anteprima e confermo il trasferimento.</label></div>
<button type="submit" class="btn btn-primary">Conferma migrazione</button>
</form>
<?php endif; ?>
<a class="btn btn-outline-light mt-3" href="menu_cene_config.php">Torna alla configurazione</a>
<?php include __DIR__ . '/includes/footer.php'; ?>
