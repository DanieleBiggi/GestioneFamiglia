<?php
include 'includes/session_check.php';
include 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'table:viaggi', 'view')) { http_response_code(403); exit('Accesso negato'); }
$id = (int)($_GET['id'] ?? 0);
$q = $conn->prepare('SELECT id_viaggio,titolo FROM viaggi WHERE id_viaggio=?');
$q->bind_param('i',$id);$q->execute();$viaggio=$q->get_result()->fetch_assoc();
if (!$viaggio) { http_response_code(404); exit('Vacanza non trovata'); }
$categories = [
 ['viaggi_tratte','viaggi_tratte_alternative','id_tratta','descrizione','Tratte','vacanze_tratte_dettaglio.php'],
 ['viaggi_alloggi','viaggi_alloggi_alternative','id_alloggio','nome_alloggio','Alloggi','vacanze_alloggi_dettaglio.php'],
 ['viaggi_pasti','viaggi_pasti_alternative','id_pasto','nome_locale','Pasti','vacanze_pasti_dettaglio.php'],
 ['viaggi_altri_costi','viaggi_altri_costi_alternative','id_costo','descrizione','Altri costi','vacanze_altri_costi_dettaglio.php']
];
$groups=[];
foreach ($categories as [$table,$link,$pk,$name,$title,$page]) {
 if (!has_permission($conn, 'table:' . $table, 'view')) { continue; }
 $stmt=$conn->prepare("SELECT e.`$pk` AS id_spesa, e.`$name` AS nome FROM `$table` e WHERE e.id_viaggio=? AND NOT EXISTS (SELECT 1 FROM `$link` l WHERE l.`$pk`=e.`$pk`) ORDER BY e.`$pk`");
 $stmt->bind_param('i',$id);$stmt->execute();
 $groups[]=['title'=>$title,'page'=>$page,'key'=>$pk,'items'=>$stmt->get_result()->fetch_all(MYSQLI_ASSOC)];
}
include 'includes/header.php';
?>
<div class="container text-white my-3">
 <a class="btn btn-outline-light mb-3" href="vacanze_workspace.php?id=<?= $id ?>">← Vacanza</a>
 <h4>Spese non assegnate: <?= htmlspecialchars($viaggio['titolo'],ENT_QUOTES,'UTF-8') ?></h4>
 <p class="text-muted">Le spese qui presenti non contribuiscono ai totali degli scenari. Apri una spesa per assegnarla a uno o più scenari.</p>
 <?php foreach ($groups as $group): ?>
 <h5 class="mt-4"><?= htmlspecialchars($group['title']) ?> (<?= count($group['items']) ?>)</h5>
 <?php if (!$group['items']): ?><p class="text-muted">Nessuna spesa non assegnata.</p><?php endif; ?>
 <div class="list-group">
 <?php foreach ($group['items'] as $item): ?>
 <a class="list-group-item list-group-item-action bg-dark text-white" href="<?= htmlspecialchars($group['page']) ?>?id=<?= $id ?>&<?= urlencode($group['key']) ?>=<?= (int)$item['id_spesa'] ?>"><?= htmlspecialchars($item['nome'] ?: 'Spesa #'.$item['id_spesa'],ENT_QUOTES,'UTF-8') ?></a>
 <?php endforeach; ?>
 </div>
 <?php endforeach; ?>
</div>
<?php include 'includes/footer.php'; ?>
