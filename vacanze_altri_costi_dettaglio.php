<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/vacanze_spese_assoc.php';
require_once 'includes/permissions.php';

$id = (int)($_GET['id'] ?? 0);
$alt = (int)($_GET['alt'] ?? 0);
$id_costo = (int)($_GET['id_costo'] ?? 0);
$duplica = isset($_GET['duplica']);

if (!has_permission($conn, 'table:viaggi', 'view')) { http_response_code(403); exit('Accesso negato'); }
// I permessi vanno verificati anche lato server, non solo nascondendo i pulsanti.
$canViewSpesa = has_permission($conn, 'table:viaggi_altri_costi', 'view');
$canInsertSpesa = has_permission($conn, 'table:viaggi_altri_costi', 'insert');
$canUpdateSpesa = has_permission($conn, 'table:viaggi_altri_costi', 'update');
$canDeleteSpesa = has_permission($conn, 'table:viaggi_altri_costi', 'delete');
if (!$canViewSpesa || ($duplica && !$canInsertSpesa) || ($id_costo && !$duplica && !$canUpdateSpesa) || (!$id_costo && !$canInsertSpesa)) {
    http_response_code(403); exit('Accesso negato');
}

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
    if (isset($_POST['delete']) && !$canDeleteSpesa) { http_response_code(403); exit('Accesso negato'); }
    if (!isset($_POST['delete']) && !($id_costo && !$duplica ? $canUpdateSpesa : $canInsertSpesa)) { http_response_code(403); exit('Accesso negato'); }
    if (!hash_equals($_SESSION['vacanze_csrf'] ?? '', (string)($_POST['vacanze_csrf'] ?? '')) || empty($_SESSION['vacanze_csrf'])) { http_response_code(403); exit('Token non valido'); }
    if (isset($_POST['delete']) && $duplica) { http_response_code(400); exit('Operazione non valida'); }
    $postedSpesaId = (int)($_POST['id_costo'] ?? 0);
    if ($postedSpesaId !== ($duplica ? 0 : $id_costo)) { http_response_code(400); exit('Identificativo spesa non valido'); }
    if (isset($_POST['separa_scenario'])) {
        if (!$id_costo || $duplica || !$canInsertSpesa || !$canUpdateSpesa) {
            http_response_code(403); exit('Operazione non consentita');
        }
        $targetAlt = filter_var($_POST['separa_scenario'], FILTER_VALIDATE_INT);
        if (!$targetAlt || $targetAlt <= 0) { http_response_code(422); exit('Scenario non valido'); }
        try {
            $newId = vacanze_separa_spesa_scenario($conn, 'viaggi_altri_costi', $id_costo, $id, (int)$targetAlt);
        } catch (Throwable $e) {
            http_response_code(422);
            exit('Impossibile separare la spesa: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
        header('Location: vacanze_altri_costi_dettaglio.php?id=' . $id . '&alt=' . (int)$targetAlt . '&id_costo=' . $newId);
        exit;
    }
    $selectedAltIds = array_map('intval', (array)($_POST['id_alternative'] ?? []));
    $id_alt = $selectedAltIds[0] ?? null;
    $data = ($_POST['data'] ?? '') ?: null;
    $importo = (($_POST['importo_eur'] ?? '') !== '') ? (float)$_POST['importo_eur'] : null;
    $descrizione = $_POST['descrizione'] ?? null;
    $note = $_POST['note'] ?? null;
    $pagato = isset($_POST['pagato']) ? 1 : 0;

    $conn->begin_transaction();
    if ($id_costo && !$duplica) {
        $sharedCheck = $conn->prepare('SELECT COUNT(*) AS totale FROM viaggi_altri_costi_alternative WHERE id_costo=?');
        $sharedCheck->bind_param('i', $id_costo);
        $sharedCheck->execute();
        $sharedCount = (int)$sharedCheck->get_result()->fetch_assoc()['totale'];
        $sharedCheck->close();
        if ($sharedCount > 1 && ($_POST['conferma_spesa_condivisa'] ?? '') !== '1') {
            $conn->rollback();
            http_response_code(422);
            exit('Questa spesa appartiene a più scenari. Conferma esplicitamente la modifica o eliminazione su tutti gli scenari collegati.');
        }
    }
    try {
    if (isset($_POST['delete']) && $id_costo && !$duplica) {
        $del = $conn->prepare('DELETE FROM viaggi_altri_costi WHERE id_costo=? AND id_viaggio=?');
        $del->bind_param('ii', $id_costo, $id);
        if (!$del->execute()) { throw new RuntimeException('Eliminazione fallita'); }
    } elseif ($id_costo && !$duplica) {
        $upd = $conn->prepare('UPDATE viaggi_altri_costi SET id_viaggio_alternativa=?, data=?, importo_eur=?, descrizione=?, note=?, pagato=? WHERE id_costo=? AND id_viaggio=?');
        $upd->bind_param('isdssiii', $id_alt, $data, $importo, $descrizione, $note, $pagato, $id_costo, $id);
        if (!$upd->execute()) { throw new RuntimeException('Aggiornamento fallito'); }
        vacanze_sync_spesa_alternative($conn, 'viaggi_altri_costi', $id_costo, $id, $selectedAltIds);
    } else {
        $ins = $conn->prepare('INSERT INTO viaggi_altri_costi (id_viaggio, id_viaggio_alternativa, data, importo_eur, descrizione, note, pagato) VALUES (?,?,?,?,?,?,?)');
        $ins->bind_param('iisdssi', $id, $id_alt, $data, $importo, $descrizione, $note, $pagato);
        if (!$ins->execute()) { throw new RuntimeException('Inserimento fallito'); }
        vacanze_sync_spesa_alternative($conn, 'viaggi_altri_costi', (int)$conn->insert_id, $id, $selectedAltIds);
    }
    $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        http_response_code(400);
        exit('Impossibile salvare la spesa: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
    header('Location: ' . (($alt ?: (int)$id_alt) > 0 ? 'vacanze_tratte.php?id=' . $id . '&alt=' . ($alt ?: (int)$id_alt) : 'vacanze_spese_non_assegnate.php?id=' . $id));
    exit;
}

$costo = [
    'id_viaggio_alternativa' => $alt,
    'data' => '',
    'importo_eur' => '',
    'descrizione' => '',
    'note' => '',
    'pagato' => 0,
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
    if (!$alt) { $alt = (int)$costo['id_viaggio_alternativa']; }
    if ($duplica) {
        $id_costo = 0;
        $costo['pagato'] = 0;
    }
}

$altStmt = $conn->prepare('SELECT id_viaggio_alternativa, breve_descrizione FROM viaggi_alternative WHERE id_viaggio=? ORDER BY id_viaggio_alternativa');
$altStmt->bind_param('i', $id);
$altStmt->execute();
$altRes = $altStmt->get_result();
$alternative = [];
while ($row = $altRes->fetch_assoc()) { $alternative[$row['id_viaggio_alternativa']] = $row['breve_descrizione']; }
$selectedAlternativeIds = $id_costo && !$duplica ? vacanze_spesa_alternative($conn, 'viaggi_altri_costi_alternative', 'id_costo', $id_costo) : [($alt ?: 0)];
$alt_desc = $alternative[$alt] ?? '';
?>
<?php if (empty($_SESSION['vacanze_csrf'])) { $_SESSION['vacanze_csrf'] = bin2hex(random_bytes(32)); } ?>
<?php include 'includes/header.php'; ?>
<div class="container text-white vw">
  <a href="vacanze_tratte.php?id=<?= $id ?>&alt=<?= $alt ?>" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-3"><?= $duplica ? 'Duplica' : ($id_costo ? 'Modifica' : 'Nuovo') ?> costo</h4>
  <form method="post">
    <?php if ($id_costo && !$duplica && count($selectedAlternativeIds) > 1): ?>
      <div class="alert alert-warning">
        Questa spesa è condivisa da <?= count($selectedAlternativeIds) ?> scenari. Le modifiche ai dati o l'eliminazione avranno effetto su tutti gli scenari collegati.
        <label class="d-block mt-2"><input type="checkbox" name="conferma_spesa_condivisa" value="1" required> Confermo di voler modificare o eliminare questa spesa condivisa.</label>
      </div>
    <?php endif; ?>
    <?php if ($id_costo && !$duplica && count($selectedAlternativeIds) > 1 && $canInsertSpesa && $canUpdateSpesa): ?>
      <div class="mb-3">
        <label for="separa_scenario_id_costo" class="form-label">Modifica soltanto uno scenario</label>
        <div class="d-flex gap-2 flex-wrap">
          <select id="separa_scenario_id_costo" name="separa_scenario" class="form-select" style="max-width: 320px">
            <?php foreach ($selectedAlternativeIds as $scenarioId): ?>
              <option value="<?= (int)$scenarioId ?>"><?= htmlspecialchars($alternative[$scenarioId] ?? (string)$scenarioId, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-outline-primary" formnovalidate onclick="return confirm('Creare una copia indipendente per questo scenario? La spesa originale resterà negli altri scenari.')">Separa e modifica</button>
        </div>
        <small class="text-muted">Crea una copia per lo scenario scelto, senza modificare gli altri. Le modifiche saranno salvate nella pagina successiva.</small>
      </div>
    <?php endif; ?>
    <input type="hidden" name="vacanze_csrf" value="<?= htmlspecialchars($_SESSION['vacanze_csrf'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id_costo" value="<?= (int)$id_costo ?>">
    <div class="mb-3">
      <label class="form-label">Alternativa</label>
      <div class="border border-secondary rounded p-2"><?php foreach ($alternative as $aid => $descr): ?><label class="d-block mb-1"><input type="checkbox" name="id_alternative[]" value="<?= (int)$aid ?>" <?= in_array((int)$aid, $selectedAlternativeIds, true) ? 'checked' : '' ?>> <?= htmlspecialchars($descr) ?></label><?php endforeach; ?><small class="text-muted">Seleziona più scenari oppure nessuno (spesa non assegnata).</small></div>
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
      <label class="form-label">Descrizione</label>
      <input type="text" class="form-control" name="descrizione" value="<?= htmlspecialchars($costo['descrizione']) ?>">
    </div>
    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" name="pagato" id="pagato_costo" <?= !empty($costo['pagato']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="pagato_costo">Già pagato</label>
    </div>
    <div class="mb-3">
      <label class="form-label">Note</label>
      <textarea class="form-control" name="note"><?= htmlspecialchars($costo['note']) ?></textarea>
    </div>
    <div class="d-flex justify-content-between mt-3">
      <button type="submit" class="btn btn-primary">Salva</button>
      <?php if ($id_costo && !$duplica && $canDeleteSpesa): ?>
        <button type="submit" name="delete" value="1" class="btn btn-danger">Elimina</button>
      <?php endif; ?>
    </div>
  </form>
</div>
<?php include 'includes/footer.php'; ?>
