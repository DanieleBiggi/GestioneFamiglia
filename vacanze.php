<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$stato = $_GET['stato'] ?? '';
$budget = $_GET['budget_max'] ?? '';
$query = "SELECT v.*, t.min_totale FROM viaggi v LEFT JOIN (SELECT id_viaggio, MIN(totale_viaggio) AS min_totale FROM v_totali_alternative GROUP BY id_viaggio) t ON v.id_viaggio=t.id_viaggio WHERE 1=1";
$params = [];
$types = '';
if ($stato !== '') { $query .= " AND v.stato = ?"; $types .= 's'; $params[] = $stato; }
if ($budget !== '') { $query .= " AND (t.min_totale IS NULL OR t.min_totale <= ?)"; $types .= 'd'; $params[] = $budget; }
$query .= " ORDER BY v.data_inizio DESC";
$stmt = $conn->prepare($query);
if ($types) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$res = $stmt->get_result();
?>
<div class="container text-white">
  <div class="d-flex mb-3 justify-content-between">
    <h4>Vacanze</h4>
    <a href="vacanze_modifica.php" class="btn btn-outline-light btn-sm">Nuovo</a>
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
      <div class="col-12">
        <button class="btn btn-primary w-100">Filtra</button>
      </div>
    </div>
    <!-- Filtri aggiuntivi: regione, durata, etichette -->
  </form>
  <div class="row row-cols-1 g-3">
    <?php while($row = $res->fetch_assoc()): ?>
    <div class="col">
      <div class="card bg-dark text-white">
        <div class="card-body">
          <h5 class="card-title"><a href="vacanze_view.php?id=<?= (int)$row['id_viaggio'] ?>" class="text-white text-decoration-none"><?= htmlspecialchars($row['titolo']) ?></a></h5>
          <p class="card-text small mb-1"><?= htmlspecialchars($row['data_inizio']) ?> - <?= htmlspecialchars($row['data_fine']) ?></p>
          <?php if(isset($row['min_totale'])): ?>
          <p class="card-text">Miglior totale: €<?= number_format($row['min_totale'],2,',','.') ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endwhile; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
