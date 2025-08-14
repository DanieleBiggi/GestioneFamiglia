<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:invitati_eventi.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
require_once 'includes/render_invitato_evento.php';
include 'includes/header.php';
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;
$stmt = $conn->prepare('SELECT i.id, IFNULL(u.nome,i.nome) AS nome, IFNULL(u.cognome,i.cognome) AS cognome FROM eventi_invitati i JOIN eventi_invitati2famiglie f ON i.id=f.id_invitato LEFT JOIN utenti u ON i.id_utente=u.id AND u.attivo=1 WHERE f.id_famiglia=? AND f.attivo=1 ORDER BY cognome,nome');
$stmt->bind_param('i', $idFamiglia);
$stmt->execute();
$res = $stmt->get_result();
$canInsert = has_permission($conn, 'table:eventi_invitati', 'insert');
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Invitati</h4>
  <?php if ($canInsert): ?>
  <button type="button" class="btn btn-outline-light btn-sm" onclick="openInvitatoModal()">Aggiungi nuovo</button>
  <?php endif; ?>
</div>
<div class="d-flex mb-3 align-items-center">
  <input type="text" id="search" class="form-control bg-dark text-white border-secondary" placeholder="Cerca">
</div>
<div id="invitatiList">
<?php while ($row = $res->fetch_assoc()): ?>
  <?php render_invitato_evento($row); ?>
<?php endwhile; ?>
</div>
<?php if ($canInsert): ?>
<div class="modal fade" id="invitatoModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" id="invitatoForm">
      <div class="modal-header">
        <h5 class="modal-title">Nuovo invitato</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nome</label>
          <input type="text" name="nome" class="form-control bg-secondary text-white" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Cognome</label>
          <input type="text" name="cognome" class="form-control bg-secondary text-white" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">Salva</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
<script src="js/invitati_eventi.js"></script>
<?php include 'includes/footer.php'; ?>
