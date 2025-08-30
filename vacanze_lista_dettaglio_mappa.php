<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$alt = (int)($_GET['alt'] ?? 0);

$luogoStmt = $conn->prepare('SELECT v.id_luogo, l.nome, l.lat, l.lng FROM viaggi v LEFT JOIN viaggi_luoghi l ON v.id_luogo=l.id_luogo WHERE v.id_viaggio=?');
$luogoStmt->bind_param('i', $id);
$luogoStmt->execute();
$luogo = $luogoStmt->get_result()->fetch_assoc();

$altStmt = $conn->prepare('SELECT id_viaggio_alternativa, breve_descrizione FROM viaggi_alternative WHERE id_viaggio=? ORDER BY id_viaggio_alternativa');
$altStmt->bind_param('i', $id);
$altStmt->execute();
$alternatives = $altStmt->get_result()->fetch_all(MYSQLI_ASSOC);
if(!$alt && !empty($alternatives)){
  $alt = $alternatives[0]['id_viaggio_alternativa'];
}

$allStmt = $conn->prepare('SELECT nome_alloggio, lat, lng FROM viaggi_alloggi WHERE id_viaggio=? AND id_viaggio_alternativa=? AND lat IS NOT NULL AND lng IS NOT NULL');
$allStmt->bind_param('ii', $id, $alt);
$allStmt->execute();
$alloggi = $allStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$trStmt = $conn->prepare('SELECT origine_testo, origine_lat, origine_lng, destinazione_testo, destinazione_lat, destinazione_lng FROM viaggi_tratte WHERE id_viaggio=? AND id_viaggio_alternativa=?');
$trStmt->bind_param('ii', $id, $alt);
$trStmt->execute();
$tratte = $trStmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<div class="container my-3">
  <a href="vacanze_lista_dettaglio.php?id=<?= $id ?>" class="btn btn-outline-secondary mb-3">&larr; Indietro</a>
  <h4 class="mb-3">Mappa</h4>
  <div id="map" style="height:500px"></div>
  <?php if(!empty($alternatives)): ?>
  <div class="mt-3 d-flex flex-wrap gap-2">
    <?php foreach($alternatives as $row): ?>
      <a href="vacanze_lista_dettaglio_mappa.php?id=<?= $id ?>&alt=<?= (int)$row['id_viaggio_alternativa'] ?>" class="btn btn-sm <?= $row['id_viaggio_alternativa']==$alt ? 'btn-primary' : 'btn-outline-primary' ?>">
        <?= htmlspecialchars($row['breve_descrizione']) ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<script>
const luogo = <?= json_encode($luogo) ?>;
const alloggi = <?= json_encode($alloggi) ?>;
const tratte = <?= json_encode($tratte) ?>;
function initMap(){
  const center = luogo && luogo.lat && luogo.lng ? {lat:parseFloat(luogo.lat), lng:parseFloat(luogo.lng)} : {lat:0,lng:0};
  const map = new google.maps.Map(document.getElementById('map'), {zoom:8, center});
  if(luogo && luogo.lat && luogo.lng){
    new google.maps.Marker({position:center, map, title:luogo.nome});
  }
  alloggi.forEach(a=>{
    const pos = {lat:parseFloat(a.lat), lng:parseFloat(a.lng)};
    new google.maps.Marker({position:pos, map, icon:'https://maps.google.com/mapfiles/kml/shapes/lodging.png', title:a.nome_alloggio});
  });
  tratte.forEach(t=>{
    if(t.origine_lat && t.origine_lng){
      const posO = {lat:parseFloat(t.origine_lat), lng:parseFloat(t.origine_lng)};
      new google.maps.Marker({position:posO, map, icon:'http://maps.google.com/mapfiles/ms/icons/green-dot.png', title:t.origine_testo});
    }
    if(t.destinazione_lat && t.destinazione_lng){
      const posD = {lat:parseFloat(t.destinazione_lat), lng:parseFloat(t.destinazione_lng)};
      new google.maps.Marker({position:posD, map, icon:'http://maps.google.com/mapfiles/ms/icons/red-dot.png', title:t.destinazione_testo});
    }
  });
}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= $config['GOOGLE_MAPS_API'] ?? '' ?>&callback=initMap&loading=async" async defer></script>
<?php include 'includes/footer.php'; ?>

