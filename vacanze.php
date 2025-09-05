<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/permissions.php';

if (!has_permission($conn, 'page:vacanze.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
$canInsert = has_permission($conn, 'table:viaggi', 'insert');

$stato = $_GET['stato'] ?? '';
$budget = $_GET['budget_max'] ?? '';
$query = "SELECT v.*, "
  . "NULLIF(v.data_inizio,'0000-00-00') AS data_inizio, "
  . "NULLIF(v.data_fine,'0000-00-00') AS data_fine, "
  . "t.min_totale, a.num_alternative, f.media_voto, f.num_feedback FROM viaggi v "
  . "LEFT JOIN (SELECT id_viaggio, MIN(totale_viaggio) AS min_totale FROM v_totali_alternative GROUP BY id_viaggio) t ON v.id_viaggio=t.id_viaggio "
  . "LEFT JOIN (SELECT id_viaggio, COUNT(*) AS num_alternative FROM viaggi_alternative GROUP BY id_viaggio) a ON v.id_viaggio=a.id_viaggio "
  . "LEFT JOIN (SELECT id_viaggio, AVG(voto) AS media_voto, COUNT(voto) AS num_feedback FROM viaggi_feedback GROUP BY id_viaggio) f ON v.id_viaggio=f.id_viaggio WHERE 1=1";
$params = [];
$types = '';
if ($stato !== '') { $query .= " AND v.stato = ?"; $types .= 's'; $params[] = $stato; }
if ($budget !== '') { $query .= " AND (t.min_totale IS NULL OR t.min_totale <= ?)"; $types .= 'd'; $params[] = $budget; }
$query .= " ORDER BY v.data_inizio DESC";
$stmt = $conn->prepare($query);
if ($types) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$res = $stmt->get_result();
include 'includes/header.php';
?>
<div class="container text-white">
  <div class="d-flex mb-3 justify-content-between">
    <h4>Vacanze</h4>
    <?php if ($canInsert): ?>
    <a href="vacanze_modifica.php" class="btn btn-outline-light btn-sm">Nuovo</a>
    <?php endif; ?>
  </div>
  <form class="mb-3" method="get">
    <div class="row g-2">
      <div class="col">
        <select name="stato" class="form-select bg-dark text-white border-secondary">
          <option value="">Stato</option>
          <?php foreach(['idea','shortlist','pianificato','prenotato','fatto','scartato'] as $s): ?>
            <option value="<?= $s ?>" <?= $stato===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col">
        <input type="number" step="0.01" name="budget_max" value="<?= htmlspecialchars($budget) ?>" class="form-control bg-dark text-white border-secondary" placeholder="Budget max €">
      </div>
      <div class="col-6">
        <button class="btn btn-primary w-100">Filtra</button>
      </div>
      <div class="col-6">
        <a href="vacanze_lista.php" class="btn btn-outline-light w-100">Lista</a>
      </div>
    </div>
    <!-- Filtri aggiuntivi: regione, durata, etichette -->
  </form>
  <div class="row row-cols-1 g-3">
    <?php while($row = $res->fetch_assoc()): ?>
    <div class="col">
      <a href="vacanze_view.php?id=<?= (int)$row['id_viaggio'] ?>" class="card bg-dark text-white text-decoration-none">
        <div class="card-body">
          <h5 class="card-title mb-1 d-flex justify-content-between">
            <span><?= htmlspecialchars($row['titolo']) ?></span>
            <span class="small"><?= number_format($row['media_voto'] ?? 0,1,',','.') ?> (<?= (int)($row['num_feedback'] ?? 0) ?>)</span>
          </h5>
          <?php
          $di = $row['data_inizio'] ?? '';
          $df = $row['data_fine'] ?? '';
          if ($di || $df): ?>
          <p class="card-text small mb-1">
            <?= htmlspecialchars($di) ?><?= ($di && $df) ? ' - ' : '' ?><?= htmlspecialchars($df) ?>
          </p>
          <?php endif; ?>
          <p class="card-text small mb-1">Alternative: <?= (int)($row['num_alternative'] ?? 0) ?></p>
          <?php if(isset($row['min_totale'])): ?>
          <p class="card-text">A partire da: €<?= number_format($row['min_totale'],2,',','.') ?></p>
          <?php endif; ?>
        </div>
      </a>
    </div>
    <?php endwhile; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
