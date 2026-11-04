<?php
include 'includes/session_check.php';
include 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn,'table:viaggi','view')) { http_response_code(403); exit('Accesso negato'); }
$id=(int)($_GET['id'] ?? 0);
$canManage=has_permission($conn,'table:viaggi','update');
$q=$conn->prepare('SELECT titolo FROM viaggi WHERE id_viaggio=?');
$q->bind_param('i',$id); $q->execute(); $trip=$q->get_result()->fetch_assoc(); $q->close();
if (!$trip) { http_response_code(404); exit('Vacanza non trovata'); }
if (empty($_SESSION['vacanze_csrf'])) $_SESSION['vacanze_csrf']=bin2hex(random_bytes(32));
$error=''; $createdUrl='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 if (!$canManage) { http_response_code(403); exit('Accesso negato'); }
 if (!hash_equals($_SESSION['vacanze_csrf'],(string)($_POST['vacanze_csrf'] ?? ''))) { http_response_code(403); exit('Token non valido'); }
 $action=(string)($_POST['action'] ?? '');
 if ($action==='revoke') {
  $shareId=(int)($_POST['id_condivisione'] ?? 0);
  $upd=$conn->prepare('UPDATE viaggi_condivisioni SET revocato_il=UTC_TIMESTAMP() WHERE id_condivisione=? AND id_viaggio=? AND revocato_il IS NULL');
  $upd->bind_param('ii',$shareId,$id); $upd->execute(); $upd->close();
  header('Location: vacanze_condivisioni.php?id='.$id); exit;
 } elseif ($action==='create') {
  $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['alternative'] ?? [])),fn($x)=>$x>0)));
  if (!$ids) $error='Seleziona almeno uno scenario.';
  else {
   $placeholders=implode(',',array_fill(0,count($ids),'?'));
   $sql="SELECT COUNT(*) AS n FROM viaggi_alternative WHERE id_viaggio=? AND id_viaggio_alternativa IN ($placeholders)";
   $check=$conn->prepare($sql);
   $types=str_repeat('i',count($ids)+1); $params=array_merge([$id],$ids);
   $check->bind_param($types,...$params); $check->execute(); $count=(int)$check->get_result()->fetch_assoc()['n']; $check->close();
   if ($count!==count($ids)) $error='Uno o più scenari non appartengono alla vacanza.';
   else {
    $raw=bin2hex(random_bytes(32)); $hash=hash('sha256',$raw);
    $uid=(int)$_SESSION['utente_id'];
    $conn->begin_transaction();
    try {
     $ins=$conn->prepare('INSERT INTO viaggi_condivisioni (id_viaggio,token_sha256,creato_da,scade_il) VALUES (?,?,?,UTC_TIMESTAMP() + INTERVAL 7 DAY)');
     $ins->bind_param('isi',$id,$hash,$uid); $ins->execute(); $shareId=$conn->insert_id; $ins->close();
     $link=$conn->prepare('INSERT INTO viaggi_condivisioni_alternative (id_condivisione,id_viaggio_alternativa) VALUES (?,?)');
     foreach ($ids as $altId) { $link->bind_param('ii',$shareId,$altId); $link->execute(); }
     $link->close(); $conn->commit();
     $base=dirname($_SERVER['SCRIPT_NAME']); $base=$base==='/'?'':rtrim($base,'/');
     $createdUrl=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'?'https':'http').'://'.$_SERVER['HTTP_HOST'].$base.'/vacanze_condivisa.php?token='.$raw;
    } catch (Throwable $e) { $conn->rollback(); $error='Impossibile creare la condivisione.'; }
   }
  }
 } else $error='Operazione non valida.';
}
$altStmt=$conn->prepare('SELECT id_viaggio_alternativa,breve_descrizione FROM viaggi_alternative WHERE id_viaggio=? ORDER BY id_viaggio_alternativa');
$altStmt->bind_param('i',$id); $altStmt->execute(); $alts=$altStmt->get_result()->fetch_all(MYSQLI_ASSOC); $altStmt->close();
$shares=$conn->prepare('SELECT c.id_condivisione,c.creato_il,c.scade_il,c.revocato_il, GROUP_CONCAT(a.breve_descrizione ORDER BY a.id_viaggio_alternativa SEPARATOR ", ") AS scenari FROM viaggi_condivisioni c LEFT JOIN viaggi_condivisioni_alternative ca ON ca.id_condivisione=c.id_condivisione LEFT JOIN viaggi_alternative a ON a.id_viaggio_alternativa=ca.id_viaggio_alternativa WHERE c.id_viaggio=? GROUP BY c.id_condivisione ORDER BY c.creato_il DESC');
$shares->bind_param('i',$id); $shares->execute(); $rows=$shares->get_result()->fetch_all(MYSQLI_ASSOC); $shares->close();
function esc($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
include 'includes/header.php';
?>
<div class="container text-white my-3">
<a class="btn btn-outline-light mb-3" href="vacanze_view.php?id=<?= $id ?>">← Vacanza</a>
<h4>Condivisioni: <?= esc($trip['titolo']) ?></h4>
<p class="text-secondary">I link sono validi per 7 giorni. Il link completo viene mostrato solo alla creazione.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= esc($error) ?></div><?php endif; ?>
<?php if ($createdUrl): ?><div class="alert alert-success"><p>Link creato. Copialo ora: non sarà possibile recuperarlo in seguito.</p><input class="form-control" readonly value="<?= esc($createdUrl) ?>" onclick="this.select()"></div><?php endif; ?>
<?php if ($canManage): ?>
<form method="post" class="border rounded p-3 mb-4">
<input type="hidden" name="vacanze_csrf" value="<?= esc($_SESSION['vacanze_csrf']) ?>">
<input type="hidden" name="action" value="create">
<h5 class="h6">Nuova condivisione</h5>
<?php foreach ($alts as $a): ?>
<label class="d-block mb-2"><input type="checkbox" name="alternative[]" value="<?= (int)$a['id_viaggio_alternativa'] ?>"> <?= esc($a['breve_descrizione']) ?></label>
<?php endforeach; ?>
<button type="submit" class="btn btn-primary" <?= !$alts?'disabled':'' ?>>Genera link</button>
</form>
<?php endif; ?>
<h5>Link creati</h5>
<?php if (!$rows): ?><p>Nessun link creato.</p><?php endif; ?>
<?php foreach ($rows as $row): ?>
<div class="border rounded p-3 mb-2"><div><?= esc($row['scenari']) ?></div><div class="small text-secondary">Creato: <?= esc($row['creato_il']) ?> · Scadenza UTC: <?= esc($row['scade_il']) ?></div>
<?php if ($row['revocato_il']): ?><span class="text-secondary">Revocato</span>
<?php elseif (strtotime($row['scade_il'].' UTC')<=time()): ?><span class="text-secondary">Scaduto</span>
<?php elseif ($canManage): ?><form method="post" class="mt-2"><input type="hidden" name="vacanze_csrf" value="<?= esc($_SESSION['vacanze_csrf']) ?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="id_condivisione" value="<?= (int)$row['id_condivisione'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Revoca link</button></form><?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php include 'includes/footer.php'; ?>
