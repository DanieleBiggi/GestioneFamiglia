<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:eventi_google_rules.php', 'view')) { http_response_code(403); exit('Accesso negato'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rule = [
    'id' => 0,
    'creator_email' => '',
    'description_keyword' => '',
    'id_tipo_evento' => null,
    'attiva' => 1
];
if ($id > 0) {
    $stmt = $conn->prepare('SELECT * FROM eventi_google_rules WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $rule = $res->fetch_assoc();
    } else {
        include 'includes/header.php';
        echo '<p class="text-danger">Record non trovato.</p>';
        include 'includes/footer.php';
        exit;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_rule') {
        $creator = $_POST['creator_email'] ?? '';
        $keyword = $_POST['description_keyword'] ?? '';
        $idTipo = isset($_POST['id_tipo_evento']) && $_POST['id_tipo_evento'] !== '' ? (int)$_POST['id_tipo_evento'] : null;
        $attiva = isset($_POST['attiva']) ? 1 : 0;
        if ($id > 0) {
            $stmt = $conn->prepare('UPDATE eventi_google_rules SET creator_email=?, description_keyword=?, id_tipo_evento=?, attiva=? WHERE id=?');
            $stmt->bind_param('ssiii', $creator, $keyword, $idTipo, $attiva, $id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare('INSERT INTO eventi_google_rules (creator_email, description_keyword, id_tipo_evento, attiva) VALUES (?,?,?,?)');
            $stmt->bind_param('ssii', $creator, $keyword, $idTipo, $attiva);
            $stmt->execute();
            $id = $stmt->insert_id;
            $stmt->close();
        }
        header('Location: eventi_google_rule_dettaglio.php?id=' . $id);
        exit;
    } elseif ($action === 'add_inv') {
        $idInv = isset($_POST['id_invitato']) ? (int)$_POST['id_invitato'] : 0;
        if ($id > 0 && $idInv > 0) {
            $stmt = $conn->prepare('INSERT INTO eventi_google_rules_invitati (id_rule, id_invitato) VALUES (?,?)');
            $stmt->bind_param('ii', $id, $idInv);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: eventi_google_rule_dettaglio.php?id=' . $id);
        exit;
    } elseif ($action === 'update_inv') {
        $old = isset($_POST['old_id_invitato']) ? (int)$_POST['old_id_invitato'] : 0;
        $new = isset($_POST['id_invitato']) ? (int)$_POST['id_invitato'] : 0;
        if ($id > 0 && $old > 0) {
            $stmt = $conn->prepare('UPDATE eventi_google_rules_invitati SET id_invitato=? WHERE id_rule=? AND id_invitato=?');
            $stmt->bind_param('iii', $new, $id, $old);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: eventi_google_rule_dettaglio.php?id=' . $id);
        exit;
    } elseif ($action === 'delete_inv') {
        $del = isset($_POST['id_invitato']) ? (int)$_POST['id_invitato'] : 0;
        if ($id > 0 && $del > 0) {
            $stmt = $conn->prepare('DELETE FROM eventi_google_rules_invitati WHERE id_rule=? AND id_invitato=?');
            $stmt->bind_param('ii', $id, $del);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: eventi_google_rule_dettaglio.php?id=' . $id);
        exit;
    }
}

// Fetch invitati and tipi evento
$tipi = [];
$tipiMap = [];
$res = $conn->query('SELECT id, tipo_evento FROM eventi_tipi_eventi ORDER BY tipo_evento');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $tipi[] = $row;
        $tipiMap[$row['id']] = $row['tipo_evento'];
    }
    $res->close();
}
$allInv = [];
$res = $conn->query('SELECT id, nome, cognome FROM eventi_invitati ORDER BY nome');
if ($res) {
    while ($row = $res->fetch_assoc()) { $allInv[] = $row; }
    $res->close();
}
$invList = [];
if ($id > 0) {
    $stmt = $conn->prepare('SELECT r.id_invitato, i.nome, i.cognome FROM eventi_google_rules_invitati r JOIN eventi_invitati i ON r.id_invitato=i.id WHERE r.id_rule=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $invRes = $stmt->get_result();
    $invList = $invRes ? $invRes->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}
include 'includes/header.php';
?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <div class="d-flex align-items-center mb-3">
    <h4 class="flex-grow-1 mb-0">Regola Google Evento</h4>
    <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#editRuleModal">✏️</button>
  </div>
  <div class="mb-4">
    <div><strong>Email creatore:</strong> <?= htmlspecialchars($rule['creator_email']) ?></div>
    <div><strong>Keyword descrizione:</strong> <?= htmlspecialchars($rule['description_keyword']) ?></div>
    <div><strong>Tipo evento:</strong> <?= htmlspecialchars($tipiMap[$rule['id_tipo_evento']] ?? '') ?></div>
    <div><strong>Attiva:</strong> <?= $rule['attiva'] ? 'Si' : 'No' ?></div>
  </div>
  <h5>Invitati</h5>
  <?php if ($id > 0 && has_permission($conn, 'table:eventi_google_rules_invitati', 'insert')): ?>
  <button class="btn btn-outline-light btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#addInvModal">Aggiungi invitato</button>
  <?php endif; ?>
  <ul class="list-group bg-dark">
  <?php foreach ($invList as $inv): ?>
    <li class="list-group-item bg-dark text-white d-flex justify-content-between align-items-center">
      <span><?= htmlspecialchars($inv['nome'] . ' ' . $inv['cognome']) ?></span>
      <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#editInvModal<?= $inv['id_invitato'] ?>">Modifica</button>
    </li>
    <div class="modal fade" id="editInvModal<?= $inv['id_invitato'] ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content bg-dark text-white">
          <form method="post">
            <input type="hidden" name="action" value="update_inv">
            <input type="hidden" name="old_id_invitato" value="<?= (int)$inv['id_invitato'] ?>">
            <div class="modal-header">
              <h5 class="modal-title">Modifica invitato</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <select name="id_invitato" class="form-select bg-dark text-white border-secondary">
                <?php foreach ($allInv as $opt): ?>
                <option value="<?= $opt['id'] ?>" <?= $opt['id']==$inv['id_invitato']?'selected':'' ?>><?= htmlspecialchars($opt['nome'].' '.$opt['cognome']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-danger me-auto" onclick="this.closest('form').action.value='delete_inv'; this.closest('form').submit();">Elimina</button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
              <button type="submit" class="btn btn-primary">Salva</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  </ul>
</div>

<div class="modal fade" id="editRuleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white">
      <form method="post">
        <input type="hidden" name="action" value="save_rule">
        <div class="modal-header">
          <h5 class="modal-title"><?= $id > 0 ? 'Modifica regola' : 'Nuova regola' ?></h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Email creatore</label>
            <input type="email" name="creator_email" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($rule['creator_email']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Keyword descrizione</label>
            <input type="text" name="description_keyword" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($rule['description_keyword']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Tipo evento</label>
            <select name="id_tipo_evento" class="form-select bg-dark text-white border-secondary">
              <option value="">--</option>
              <?php foreach ($tipi as $t): ?>
              <option value="<?= $t['id'] ?>" <?= $rule['id_tipo_evento']==$t['id']?'selected':'' ?>><?= htmlspecialchars($t['tipo_evento']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="attiva" id="attiva" <?= $rule['attiva']?'checked':'' ?>>
            <label class="form-check-label" for="attiva">Attiva</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary">Salva</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="addInvModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white">
      <form method="post">
        <input type="hidden" name="action" value="add_inv">
        <div class="modal-header">
          <h5 class="modal-title">Aggiungi invitato</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <select name="id_invitato" class="form-select bg-dark text-white border-secondary">
            <?php foreach ($allInv as $opt): ?>
            <option value="<?= $opt['id'] ?>"><?= htmlspecialchars($opt['nome'].' '.$opt['cognome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary">Aggiungi</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php if ($id === 0): ?>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    var modal = new bootstrap.Modal(document.getElementById('editRuleModal'));
    modal.show();
  });
</script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
