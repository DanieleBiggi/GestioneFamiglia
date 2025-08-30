<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';

$search = $_GET['q'] ?? '';
$notti = $_GET['notti'] ?? '';
$prezzoMin = $_GET['prezzo_min'] ?? '';
$prezzoMax = $_GET['prezzo_max'] ?? '';

$query = "SELECT v.id_viaggio, v.titolo, v.breve_descrizione, lf.photo_reference as foto_url, t.min_totale FROM viaggi v LEFT JOIN (SELECT id_viaggio, MIN(totale_viaggio) AS min_totale FROM v_totali_alternative GROUP BY id_viaggio) t ON v.id_viaggio=t.id_viaggio LEFT JOIN viaggi_luoghi l ON v.id_luogo=l.id_luogo LEFT JOIN viaggi_luogo_foto lf ON v.id_foto=lf.id_foto WHERE 1=1";
$params = [];
$types = '';
if ($search !== '') {
    $query .= " AND (l.nome LIKE ? OR l.citta LIKE ? OR l.regione LIKE ? OR l.paese LIKE ?)";
    $searchLike = "%$search%";
    $types .= 'ssss';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}
if ($notti !== '') {
    $query .= " AND v.notti = ?";
    $types .= 'i';
    $params[] = $notti;
}
if ($prezzoMin !== '') {
    $query .= " AND (t.min_totale IS NULL OR t.min_totale >= ?)";
    $types .= 'd';
    $params[] = $prezzoMin;
}
if ($prezzoMax !== '') {
    $query .= " AND (t.min_totale IS NULL OR t.min_totale <= ?)";
    $types .= 'd';
    $params[] = $prezzoMax;
}
$query .= " ORDER BY v.data_inizio DESC";
$stmt = $conn->prepare($query);
if ($types) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$res = $stmt->get_result();
$count = $res->num_rows;

if (isset($_GET['count'])) {
    header('Content-Type: application/json');
    echo json_encode(['count' => $count]);
    exit;
}

// Distinct notti
$nottiRes = $conn->query("SELECT DISTINCT notti FROM viaggi WHERE notti IS NOT NULL ORDER BY notti");
?>
<?php include 'includes/header.php'; ?>
<div class="container my-3 text-white">
  <form method="get" class="mb-3" id="searchForm">
    <div class="input-group">
      <input type="text" class="form-control" name="q" placeholder="cerca una destinazione" value="<?= htmlspecialchars($search) ?>">
      <button class="btn btn-primary">Cerca</button>
    </div>
    <input type="hidden" name="notti" value="<?= htmlspecialchars($notti) ?>">
    <input type="hidden" name="prezzo_min" value="<?= htmlspecialchars($prezzoMin) ?>">
    <input type="hidden" name="prezzo_max" value="<?= htmlspecialchars($prezzoMax) ?>">
  </form>
  <div class="d-flex justify-content-end mb-3 gap-2">
    <?php if (has_permission($conn, 'page:vacanze.php', 'view')): ?>
    <a href="vacanze.php" class="btn btn-outline-light">Gestisci vacanze</a>
    <?php endif; ?>
    <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#filtersModal"><i class="bi bi-sliders"></i> Filtri</button>
  </div>
  <div class="row g-3">
    <?php while($row = $res->fetch_assoc()): ?>
    <div class="col-12">
      <a href="vacanze_lista_dettaglio.php?id=<?= (int)$row['id_viaggio'] ?>" class="text-decoration-none text-dark">
        <div class="card">
          <?php if ($row['foto_url']): ?>
          <?php $url = 'https://maps.googleapis.com/maps/api/place/photo?maxwidth=200&photo_reference=' . urlencode($row['foto_url']) . '&key=' . $config['GOOGLE_PLACES_FOTO_API']; ?>
        
          <img src="<?= htmlspecialchars($url) ?>" class="card-img-top" alt="">
          <?php endif; ?>
          <div class="card-body d-flex justify-content-between">
            <div>
              <h5 class="card-title mb-1"><?= htmlspecialchars($row['titolo']) ?></h5>
              <p class="card-text small mb-0 text-muted"><?= htmlspecialchars($row['breve_descrizione']) ?></p>
            </div>
            <?php if(isset($row['min_totale'])): ?>
            <div class="text-end">
              <small class="text-muted">a partire da</small><br>
              <span class="fw-bold">€<?= number_format($row['min_totale'],2,',','.') ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </a>
    </div>
    <?php endwhile; ?>
  </div>
</div>

<div class="modal fade" id="filtersModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content bg-dark text-white" method="get" id="filtersForm">
      <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
      <div class="modal-header">
        <h5 class="modal-title">Filtri</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Notti</label>
          <?php while($n = $nottiRes->fetch_assoc()): ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="notti" id="notti<?= $n['notti'] ?>" value="<?= $n['notti'] ?>" <?= $notti===$n['notti']?'checked':'' ?>>
              <label class="form-check-label" for="notti<?= $n['notti'] ?>"><?= $n['notti'] ?></label>
            </div>
          <?php endwhile; ?>
        </div>
        <div class="mb-3">
          <label class="form-label">Prezzo</label>
          <div class="d-flex">
            <input type="number" class="form-control me-2" name="prezzo_min" placeholder="Min" value="<?= htmlspecialchars($prezzoMin) ?>">
            <input type="number" class="form-control" name="prezzo_max" placeholder="Max" value="<?= htmlspecialchars($prezzoMax) ?>">
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-light" id="resetFilters">Cancella tutto</button>
        <button type="submit" class="btn btn-primary" id="applyBtn">Vedi <?= $count ?> vacanze</button>
      </div>
    </form>
  </div>
</div>

<script>
function updateCount(){
  const form=document.getElementById('filtersForm');
  const params=new URLSearchParams(new FormData(form));
  params.set('count','1');
  fetch('vacanze_lista.php?'+params.toString())
    .then(r=>r.json())
    .then(d=>{document.getElementById('applyBtn').innerText='Vedi '+d.count+' vacanze';});
}
document.getElementById('filtersModal').addEventListener('shown.bs.modal',updateCount);
Array.from(document.querySelectorAll('#filtersForm input')).forEach(el=>{
  el.addEventListener('input',updateCount);
});
document.getElementById('resetFilters').addEventListener('click',()=>{
  const form=document.getElementById('filtersForm');
  form.reset();
  updateCount();
});
</script>
<?php include 'includes/footer.php'; ?>
