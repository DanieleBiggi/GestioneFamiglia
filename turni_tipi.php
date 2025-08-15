<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$stmt = $conn->prepare("SELECT id, descrizione, ora_inizio, ora_fine, colore_bg, colore_testo, attivo FROM turni_tipi ORDER BY descrizione");
$stmt->execute();
$res = $stmt->get_result();
?>
<div class="d-flex mb-3 justify-content-between">
  <h4>Tipi turni</h4>
  <button class="btn btn-outline-light btn-sm" id="addType">Aggiungi nuovo</button>
</div>
<div class="d-flex mb-3 align-items-center">
  <input type="text" id="search" class="form-control bg-dark text-white border-secondary me-2" placeholder="Cerca">
  <div class="form-check form-switch text-nowrap">
    <input class="form-check-input" type="checkbox" id="showInactive">
    <label class="form-check-label" for="showInactive">Mostra non attivi</label>
  </div>
</div>
<table class="table table-dark table-hover" id="typesTable">
  <tbody>
    <?php while ($row = $res->fetch_assoc()): ?>
    <?php
      $search = strtolower($row['descrizione']);
      $searchAttr = htmlspecialchars($search, ENT_QUOTES);
      $descrizione = htmlspecialchars($row['descrizione']);
      $coloreBg = htmlspecialchars($row['colore_bg']);
      $coloreTesto = htmlspecialchars($row['colore_testo']);
      $attivo = (int)$row['attivo'] === 1;
      $class = $attivo ? '' : 'inactive';
    ?>
    <tr class="type-row <?php echo $class; ?>"
        data-id="<?php echo (int)$row['id']; ?>"
        data-descrizione="<?php echo htmlspecialchars($row['descrizione'], ENT_QUOTES); ?>"
        data-ora_inizio="<?php echo htmlspecialchars($row['ora_inizio']); ?>"
        data-ora_fine="<?php echo htmlspecialchars($row['ora_fine']); ?>"
        data-colore_bg="<?php echo $coloreBg; ?>"
        data-colore_testo="<?php echo $coloreTesto; ?>"
        data-attivo="<?php echo $attivo ? 1 : 0; ?>"
        data-search="<?php echo $searchAttr; ?>">
      <td><?php echo $descrizione; ?></td>
      <td><span class="badge" style="background:<?php echo $coloreBg; ?>;color:<?php echo $coloreTesto; ?>">Aa</span></td>
      <td class="text-end">
        <?php if ($attivo): ?>
          <i class="bi bi-check-circle-fill text-success"></i>
        <?php else: ?>
          <i class="bi bi-x-circle-fill text-danger"></i>
        <?php endif; ?>
      </td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<div class="modal fade" id="typeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title">Tipo turno</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="typeForm">
          <input type="hidden" name="id" id="typeId">
          <div class="mb-3">
            <label for="descrizione" class="form-label">Descrizione</label>
            <input type="text" class="form-control bg-dark text-white border-secondary" name="descrizione" id="descrizione" required>
          </div>
          <div class="mb-3">
            <label for="ora_inizio" class="form-label">Ora inizio</label>
            <input type="time" class="form-control bg-dark text-white border-secondary" name="ora_inizio" id="ora_inizio" required>
          </div>
          <div class="mb-3">
            <label for="ora_fine" class="form-label">Ora fine</label>
            <input type="time" class="form-control bg-dark text-white border-secondary" name="ora_fine" id="ora_fine" required>
          </div>
          <div class="mb-3">
            <label for="colore_bg" class="form-label">Colore sfondo</label>
            <input type="color" class="form-control form-control-color" id="colore_bg" name="colore_bg" value="#ffffff" title="Scegli colore">
          </div>
          <div class="mb-3">
            <label for="colore_testo" class="form-label">Colore testo</label>
            <input type="color" class="form-control form-control-color" id="colore_testo" name="colore_testo" value="#000000" title="Scegli colore">
          </div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="attivo" name="attivo">
            <label class="form-check-label" for="attivo">Attivo</label>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
        <button type="button" class="btn btn-primary" id="saveType">Salva</button>
      </div>
    </div>
  </div>
</div>

<script src="js/turni_tipi.js"></script>
<?php include 'includes/footer.php'; ?>
