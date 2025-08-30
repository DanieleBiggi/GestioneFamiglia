<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
include 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);

$statStmt = $conn->prepare('SELECT AVG(voto) AS media, COUNT(*) AS num FROM viaggi_feedback WHERE id_viaggio=?');
$statStmt->bind_param('i', $id);
$statStmt->execute();
$stats = $statStmt->get_result()->fetch_assoc();

$fbStmt = $conn->prepare('SELECT vf.voto, vf.commento, vf.creato_il, u.username FROM viaggi_feedback vf LEFT JOIN utenti u ON vf.id_utente=u.id WHERE vf.id_viaggio=? ORDER BY vf.creato_il DESC');
$fbStmt->bind_param('i', $id);
$fbStmt->execute();
$fbRes = $fbStmt->get_result();
?>
<div class="container my-3">
  <a href="vacanze_lista_dettaglio.php?id=<?= $id ?>" class="btn btn-outline-secondary mb-3">&larr; Indietro</a>
  <h4 class="mb-3">Recensioni</h4>
  <?php if ($stats['num'] > 0): ?>
  <p>Media recensioni: <?= number_format($stats['media'],1,',','.') ?> (<?= (int)$stats['num'] ?>)</p>
  <?php else: ?>
  <p>Nessuna recensione disponibile.</p>
  <?php endif; ?>

  <?php if ($fbRes->num_rows > 0): ?>
    <ul class="list-group">
      <?php while($row = $fbRes->fetch_assoc()): ?>
      <li class="list-group-item d-flex justify-content-between">
        <div>
          <strong><?= htmlspecialchars($row['username'] ?? 'Anonimo') ?></strong><br>
          <small class="text-muted"><?= htmlspecialchars(date('d/m/Y', strtotime($row['creato_il']))) ?></small>
          <?php if ($row['commento']): ?><div><?= htmlspecialchars($row['commento']) ?></div><?php endif; ?>
        </div>
        <div class="ms-3 fw-bold align-self-center"><?= (int)$row['voto'] ?></div>
      </li>
      <?php endwhile; ?>
    </ul>
  <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>

