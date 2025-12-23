<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$data = [
    'descrizione' => '',
    'stringa_da_completare' => '',
    'parametri' => '',
    'archiviato' => 0,
    'id_dato_remoto' => 0
];
$parametriArray = [];

if ($id > 0) {
    $stmt = $conn->prepare('SELECT * FROM dati_remoti WHERE id_dato_remoto = ?');
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

$parametriArray = json_decode($data['parametri'] ?? '', true);
if (!is_array($parametriArray)) {
    $parametriArray = [];
}
$parametriPretty = json_encode($parametriArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_dato_remoto = isset($_POST['id_dato_remoto']) ? (int)$_POST['id_dato_remoto'] : 0;
    if (isset($_POST['delete']) && $id_dato_remoto > 0) {
        $stmt = $conn->prepare('DELETE FROM dati_remoti WHERE id_dato_remoto = ?');
        $stmt->bind_param('i', $id_dato_remoto);
        $stmt->execute();
        $stmt->close();
        header('Location: query.php');
        exit;
    }
    $descrizione = $_POST['descrizione'] ?? '';
    $stringa = $_POST['stringa_da_completare'] ?? '';
    $parametri = $_POST['parametri'] ?? '';
    $archiviato = isset($_POST['archiviato']) ? 1 : 0;
    if ($id_dato_remoto > 0) {
        $stmt = $conn->prepare('UPDATE dati_remoti SET descrizione=?, stringa_da_completare=?, parametri=?, archiviato=? WHERE id_dato_remoto=?');
        $stmt->bind_param('sssii', $descrizione, $stringa, $parametri, $archiviato, $id_dato_remoto);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare('INSERT INTO dati_remoti (descrizione, stringa_da_completare, parametri, archiviato) VALUES (?,?,?,?)');
        $stmt->bind_param('sssi', $descrizione, $stringa, $parametri, $archiviato);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: query.php');
    exit;
}

include 'includes/header.php';
?>
<div class="container text-white">
  <a href="javascript:history.back()" class="btn btn-outline-light mb-3">← Indietro</a>
  <h4 class="mb-4">Dettaglio Query</h4>
</div>
<form method="post" class="bg-dark text-white p-3 rounded">
  <div class="mb-3">
    <label class="form-label">Descrizione</label>
    <input type="text" name="descrizione" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($data['descrizione']) ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Stringa da completare</label>
    <textarea name="stringa_da_completare" class="form-control bg-dark text-white border-secondary" rows="3" aria-describedby="stringaHelp"><?= htmlspecialchars($data['stringa_da_completare']) ?></textarea>
    <div id="stringaHelp" class="form-text text-secondary">Usa la sintassi [[NOME_PARAMETRO]] per indicare i valori da completare.</div>
  </div>
  <div class="mb-3">
    <label class="form-label">Parametri necessari</label>
    <p class="small text-secondary" id="placeholdersInfo">Compila la stringa sopra per individuare automaticamente i campi tra parentesi quadre.</p>
    <div id="parametriContainer" class="p-3 border border-secondary rounded"></div>
  </div>
  <div class="mb-3">
    <label class="form-label">Parametri (JSON generato automaticamente)</label>
    <textarea name="parametri" id="parametri" class="form-control bg-dark text-white border-secondary" rows="3" readonly><?= htmlspecialchars($parametriPretty ?? '') ?></textarea>
    <div class="form-text text-secondary">Il JSON verrà aggiornato in base ai campi compilati sopra.</div>
  </div>
  <div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" id="archiviato" name="archiviato" <?= ($data['archiviato'] ?? 0) ? 'checked' : '' ?>>
    <label class="form-check-label" for="archiviato">Archiviato</label>
  </div>
  <?php if ($data['id_dato_remoto']): ?>
    <input type="hidden" name="id_dato_remoto" value="<?= (int)$data['id_dato_remoto'] ?>">
  <?php endif; ?>
  <button type="submit" class="btn btn-primary w-100">Salva</button>
  <?php if ($data['id_dato_remoto']): ?>
    <button type="submit" name="delete" value="1" class="btn btn-danger w-100 mt-3" onclick="return confirm('Eliminare definitivamente?');">Elimina</button>
  <?php endif; ?>
</form>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const stringaField = document.querySelector('textarea[name="stringa_da_completare"]');
  const container = document.getElementById('parametriContainer');
  const jsonField = document.getElementById('parametri');
  const existingParams = <?php echo json_encode($parametriArray, JSON_UNESCAPED_UNICODE); ?>;

  function extractPlaceholders(text) {
    const matches = Array.from(text.matchAll(/\[\[([^\[\]]+)\]\]/g)).map(m => m[1].trim()).filter(Boolean);
    const unique = new Set(matches);
    Object.keys(existingParams || {}).forEach(key => unique.add(key));
    return Array.from(unique);
  }

  function syncJson() {
    const params = {};
    container.querySelectorAll('input[data-name]').forEach(input => {
      if (input.value !== '') {
        params[input.dataset.name] = input.value;
      }
    });
    jsonField.value = JSON.stringify(params, null, 2);
  }

  function buildInputs() {
    const placeholders = extractPlaceholders(stringaField.value);
    container.innerHTML = '';

    if (placeholders.length === 0) {
      container.innerHTML = '<p class="m-0 text-muted">Nessun parametro richiesto.</p>';
      jsonField.value = JSON.stringify(existingParams || {}, null, 2) || '';
      return;
    }

    placeholders.forEach(name => {
      const value = (existingParams && Object.prototype.hasOwnProperty.call(existingParams, name)) ? existingParams[name] : '';
      const group = document.createElement('div');
      group.className = 'mb-2';

      const label = document.createElement('label');
      label.className = 'form-label small mb-1';
      label.setAttribute('for', `param-${name}`);
      label.textContent = name;

      const input = document.createElement('input');
      input.type = 'text';
      input.className = 'form-control form-control-sm bg-dark text-white border-secondary';
      input.id = `param-${name}`;
      input.dataset.name = name;
      input.value = value;

      group.appendChild(label);
      group.appendChild(input);
      container.appendChild(group);
    });

    syncJson();
  }

  container.addEventListener('input', event => {
    if (event.target.matches('input[data-name]')) {
      syncJson();
    }
  });

  stringaField.addEventListener('input', buildInputs);

  buildInputs();
});
</script>
<?php include 'includes/footer.php'; ?>
