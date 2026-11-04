<?php
// Vacanze V2: dashboard ispirata al prototipo approvato, sul tema selezionato.
include 'includes/session_check.php';
include 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'table:viaggi', 'view')) { http_response_code(403); exit('Accesso negato'); }
$conn->set_charset('utf8mb4');
$id=(int)($_GET['id'] ?? 0);
$tab=(string)($_GET['tab'] ?? 'overview');
$tabs=['overview'=>'Riepilogo','compare'=>'Confronta scenari','catalog'=>'Catalogo spese','notes'=>'Note e documenti','photos'=>'Foto','checklist'=>'Checklist','sharing'=>'Condivisione'];
if (!isset($tabs[$tab])) $tab='overview';
$stmt=$conn->prepare('SELECT v.*,l.nome AS luogo_nome FROM viaggi v LEFT JOIN viaggi_luoghi l ON l.id_luogo=v.id_luogo WHERE v.id_viaggio=?');
$stmt->bind_param('i',$id);$stmt->execute();$trip=$stmt->get_result()->fetch_assoc();$stmt->close();
if (!$trip) { http_response_code(404); exit('Vacanza non trovata'); }
function vwEsc($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function vwEuro($v) { return '€ '.number_format((float)$v,2,',','.'); }
function vwUrl($id,$tab) { return 'vacanze_workspace.php?id='.$id.'&tab='.rawurlencode($tab); }
$canEdit=has_permission($conn,'table:viaggi','update');
$totals=$conn->prepare('SELECT * FROM v_totali_alternative WHERE id_viaggio=? ORDER BY id_viaggio_alternativa');
$totals->bind_param('i',$id);$totals->execute();$scenarios=$totals->get_result()->fetch_all(MYSQLI_ASSOC);$totals->close();
$expenseTypes=[
 ['label'=>'Trasporti','table'=>'viaggi_tratte','link'=>'viaggi_tratte_alternative','key'=>'id_tratta','name'=>'descrizione','formula'=>'COALESCE(e.distanza_km,0)*COALESCE(e.consumo_litri_100km,0)/100*COALESCE(e.prezzo_carburante_eur_litro,0)+COALESCE(e.pedaggi_eur,0)+COALESCE(e.costo_traghetto_eur,0)+COALESCE(e.costo_volo_eur,0)+COALESCE(e.costo_noleggio_eur,0)+COALESCE(e.altri_costi_eur,0)','page'=>'vacanze_tratte_dettaglio.php'],
 ['label'=>'Alloggi','table'=>'viaggi_alloggi','link'=>'viaggi_alloggi_alternative','key'=>'id_alloggio','name'=>'nome_alloggio','formula'=>'DATEDIFF(e.data_checkout,e.data_checkin)*COALESCE(e.costo_notte_eur,0)','page'=>'vacanze_alloggi_dettaglio.php'],
 ['label'=>'Pasti','table'=>'viaggi_pasti','link'=>'viaggi_pasti_alternative','key'=>'id_pasto','name'=>'nome_locale','formula'=>'COALESCE(e.costo_medio_eur,0)','page'=>'vacanze_pasti_dettaglio.php'],
 ['label'=>'Altri costi','table'=>'viaggi_altri_costi','link'=>'viaggi_altri_costi_alternative','key'=>'id_costo','name'=>'descrizione','formula'=>'COALESCE(e.importo_eur,0)','page'=>'vacanze_altri_costi_dettaglio.php']
];
$expenses=[];
foreach ($expenseTypes as $type) {
 if (!has_permission($conn,'table:'.$type['table'],'view')) continue;
 $table=$type['table'];$link=$type['link'];$key=$type['key'];$name=$type['name'];$formula=$type['formula'];
 $sql="SELECT e.`$key` AS id_spesa,e.`$name` AS voce,e.note,e.pagato,($formula) AS totale,
 GROUP_CONCAT(a.breve_descrizione ORDER BY a.id_viaggio_alternativa SEPARATOR ', ') AS scenari,
 COUNT(DISTINCT a.id_viaggio_alternativa) AS num_scenari
 FROM `$table` e LEFT JOIN `$link` x ON x.`$key`=e.`$key`
 LEFT JOIN viaggi_alternative a ON a.id_viaggio_alternativa=x.id_viaggio_alternativa AND a.id_viaggio=e.id_viaggio
 WHERE e.id_viaggio=? GROUP BY e.`$key`,e.`$name`,e.note,e.pagato ORDER BY e.`$key`";
 $q=$conn->prepare($sql);$q->bind_param('i',$id);$q->execute();
 foreach($q->get_result()->fetch_all(MYSQLI_ASSOC) as $item) {
  $item['categoria']=$type['label'];$item['page']=$type['page'];$item['key']=$key;
  $expenses[]=$item;
 }
 $q->close();
}
$uniquePaid=0;$unassigned=[];foreach($expenses as $e){if ((int)$e['pagato']===1) $uniquePaid+=(float)$e['totale'];if((int)$e['num_scenari']===0)$unassigned[]=$e;}
$best=null;foreach($scenarios as $s){$val=(float)$s['totale_viaggio'];if($best===null||$val<$best)$best=$val;}
$checklist=[];
if ($tab==='checklist' || $tab==='overview') {
 $q=$conn->prepare('SELECT c.id_checklist,c.voce,c.completata,u.username FROM viaggi_checklist c LEFT JOIN utenti u ON u.id=c.id_utente WHERE c.id_viaggio=? ORDER BY c.id_checklist');
 $q->bind_param('i',$id);$q->execute();$checklist=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
}
$shared=[];
if ($tab==='sharing') {
 $q=$conn->prepare('SELECT c.id_condivisione,c.creato_il,c.scade_il,c.revocato_il,GROUP_CONCAT(a.breve_descrizione SEPARATOR ", ") AS scenari FROM viaggi_condivisioni c LEFT JOIN viaggi_condivisioni_alternative ca ON ca.id_condivisione=c.id_condivisione LEFT JOIN viaggi_alternative a ON a.id_viaggio_alternativa=ca.id_viaggio_alternativa AND a.id_viaggio=c.id_viaggio WHERE c.id_viaggio=? GROUP BY c.id_condivisione ORDER BY c.id_condivisione DESC');
 $q->bind_param('i',$id);$q->execute();$shared=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
}
$docs=[];
if($tab==='notes'){
 $q=$conn->prepare('SELECT oc.nome_file,oc.descrizione FROM viaggi2caricamenti vc JOIN ocr_caricamenti oc ON oc.id_caricamento=vc.id_caricamento WHERE vc.id_viaggio=? ORDER BY vc.id_caricamento DESC');
 $q->bind_param('i',$id);$q->execute();$docs=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
}
include 'includes/header.php';
?>
<link rel="stylesheet" href="assets/vacanze_workspace.css?v=2">
<main class="vw" id="vacanzeWorkspace">
 <div class="vw-crumb"><a href="vacanze_lista.php">Vacanze</a> / <strong><?= vwEsc($trip['titolo']) ?></strong></div>
 <div class="vw-hero"><div><h1><?= vwEsc($trip['titolo']) ?></h1><div class="vw-muted"><?= vwEsc($trip['data_inizio'] ?: 'Date da definire') ?> – <?= vwEsc($trip['data_fine'] ?: 'Da definire') ?> · <?= (int)$trip['notti'] ?> notti · <?= (int)$trip['persone'] ?> persone · <?= vwEsc(ucfirst((string)$trip['stato'])) ?></div></div>
 <div class="d-flex flex-wrap gap-2"><?php if($canEdit): ?><a class="vw-btn" href="vacanze_modifica.php?id=<?= $id ?>">Modifica vacanza</a><?php endif; ?><a class="vw-btn vw-btn-primary" href="<?= vwUrl($id,'catalog') ?>">+ Nuova spesa</a></div></div>
 <nav class="vw-tabs" aria-label="Sezioni della vacanza"><?php foreach($tabs as $key=>$label): ?><a class="vw-tab <?= $tab===$key?'active':'' ?>" href="<?= vwUrl($id,$key) ?>"><?= vwEsc($label) ?></a><?php endforeach; ?></nav>
 <?php if($tab==='overview'): ?>
 <div class="vw-note">Le spese condivise si aggiornano in tutti gli scenari collegati. Le spese non assegnate rimangono nel catalogo e non entrano nei totali.</div>
 <div class="vw-grid">
  <div class="vw-panel"><div class="vw-stat-label">Scenari a confronto</div><div class="vw-stat-num"><?= count($scenarios) ?></div><div class="vw-muted">Combinazioni disponibili</div></div>
  <div class="vw-panel"><div class="vw-stat-label">Budget più economico</div><div class="vw-stat-num"><?= $best===null?'—':vwEuro($best) ?></div><div class="vw-muted">Tra le alternative attuali</div></div>
  <div class="vw-panel"><div class="vw-stat-label">Spese segnate come pagate</div><div class="vw-stat-num"><?= vwEuro($uniquePaid) ?></div><div class="vw-muted">Voci uniche, senza duplicazioni</div></div>
 </div>
 <div class="vw-section-head"><h2>Alternative di viaggio</h2><a class="vw-btn" href="vacanze_view.php?id=<?= $id ?>">+ Nuovo scenario</a></div>
 <div class="vw-grid"><?php foreach($scenarios as $s): ?>
 <article class="vw-scenario"><h3><?= vwEsc($s['breve_descrizione']) ?></h3><div class="vw-price"><?= vwEuro($s['totale_viaggio']) ?></div><div class="vw-split"><span>Trasporti</span><b><?= vwEuro($s['totale_trasporti']) ?></b><span>Alloggi</span><b><?= vwEuro($s['totale_alloggi']) ?></b><span>Pasti</span><b><?= vwEuro($s['totale_pasti']) ?></b><span>Altri costi</span><b><?= vwEuro($s['totale_altri_costi']) ?></b></div><a class="vw-btn" href="vacanze_tratte.php?id=<?= $id ?>&alt=<?= (int)$s['id_viaggio_alternativa'] ?>">Apri scenario e spese</a></article>
 <?php endforeach; ?><?php if(!$scenarios): ?><p class="vw-empty">Nessuno scenario.</p><?php endif; ?></div>
 <div class="vw-panel"><div class="vw-section-head"><h2>Spese ancora da assegnare</h2><a class="vw-btn" href="<?= vwUrl($id,'catalog') ?>">Apri catalogo</a></div>
 <?php if(!$unassigned): ?><p class="vw-muted">Tutte le spese sono assegnate.</p><?php endif; ?>
 <?php foreach(array_slice($unassigned,0,5) as $e): ?><p><a href="<?= vwEsc($e['page']) ?>?id=<?= $id ?>&<?= vwEsc($e['key']) ?>=<?= (int)$e['id_spesa'] ?>"><?= vwEsc($e['voce'] ?: $e['categoria']) ?></a> · <?= vwEuro($e['totale']) ?></p><?php endforeach; ?></div>
 <?php elseif($tab==='compare'): ?>
 <div class="vw-section-head"><h2>Confronto dei budget</h2><a class="vw-btn" href="<?= vwUrl($id,'catalog') ?>">Gestisci le spese</a></div>
 <div class="vw-panel"><div class="vw-table-wrap"><table class="vw-table"><thead><tr><th>Scenario</th><th>Trasporti</th><th>Alloggi</th><th>Pasti</th><th>Altri costi</th><th class="vw-money">Totale</th></tr></thead><tbody>
 <?php foreach($scenarios as $s): ?><tr><td><?= vwEsc($s['breve_descrizione']) ?></td><td><?= vwEuro($s['totale_trasporti']) ?></td><td><?= vwEuro($s['totale_alloggi']) ?></td><td><?= vwEuro($s['totale_pasti']) ?></td><td><?= vwEuro($s['totale_altri_costi']) ?></td><td class="vw-money"><b><?= vwEuro($s['totale_viaggio']) ?></b></td></tr><?php endforeach; ?></tbody></table></div>
 <div class="vw-card-mobile"><?php foreach($scenarios as $s): ?><article><h3><?= vwEsc($s['breve_descrizione']) ?></h3><div class="vw-price"><?= vwEuro($s['totale_viaggio']) ?></div><div class="vw-split"><span>Trasporti</span><b><?= vwEuro($s['totale_trasporti']) ?></b><span>Alloggi</span><b><?= vwEuro($s['totale_alloggi']) ?></b><span>Pasti</span><b><?= vwEuro($s['totale_pasti']) ?></b><span>Altri costi</span><b><?= vwEuro($s['totale_altri_costi']) ?></b></div></article><?php endforeach; ?></div></div>
 <div class="vw-panel"><h2>Come funzionano le spese condivise</h2><p>Una spesa può appartenere a più scenari senza essere duplicata. Se cambi il costo, la modifica si riflette in tutte le alternative collegate.</p></div>
 <?php elseif($tab==='catalog'): ?>
 <div class="vw-section-head"><h2>Catalogo delle spese</h2><a class="vw-btn vw-btn-primary" href="vacanze_spese_non_assegnate.php?id=<?= $id ?>">Spese non assegnate</a></div>
 <div class="vw-panel"><div class="vw-filter"><input class="vw-input" id="vwSearch" placeholder="Cerca una voce o una nota"><select class="vw-input" id="vwCategory"><option value="">Tutte le categorie</option><?php foreach($expenseTypes as $type): ?><option><?= vwEsc($type['label']) ?></option><?php endforeach; ?></select><select class="vw-input" id="vwAssigned"><option value="">Tutte le associazioni</option><option value="assigned">Assegnate</option><option value="unassigned">Non assegnate</option></select></div>
 <div class="vw-table-wrap"><table class="vw-table"><thead><tr><th>Voce</th><th>Categoria</th><th>Scenari collegati</th><th>Stato</th><th class="vw-money">Importo</th><th></th></tr></thead><tbody>
 <?php foreach($expenses as $e): ?><tr class="vw-expense" data-category="<?= vwEsc($e['categoria']) ?>" data-assigned="<?= (int)$e['num_scenari']?'assigned':'unassigned' ?>" data-search="<?= vwEsc(mb_strtolower((string)$e['voce'].' '.(string)$e['note'])) ?>"><td><?= vwEsc($e['voce'] ?: $e['categoria']) ?></td><td><?= vwEsc($e['categoria']) ?></td><td><?= vwEsc($e['scenari'] ?: 'Non assegnata') ?></td><td><?= (int)$e['pagato']?'Pagata':'Da pagare' ?></td><td class="vw-money"><?= vwEuro($e['totale']) ?></td><td><a class="vw-btn" href="<?= vwEsc($e['page']) ?>?id=<?= $id ?>&<?= vwEsc($e['key']) ?>=<?= (int)$e['id_spesa'] ?>">Apri</a></td></tr><?php endforeach; ?>
 </tbody></table></div><div class="vw-card-mobile"><?php foreach($expenses as $e): ?><article class="vw-expense" data-category="<?= vwEsc($e['categoria']) ?>" data-assigned="<?= (int)$e['num_scenari']?'assigned':'unassigned' ?>" data-search="<?= vwEsc(mb_strtolower((string)$e['voce'].' '.(string)$e['note'])) ?>"><h3><?= vwEsc($e['voce'] ?: $e['categoria']) ?></h3><p class="vw-muted"><?= vwEsc($e['categoria']) ?> · <?= vwEsc($e['scenari'] ?: 'Non assegnata') ?></p><div class="vw-price"><?= vwEuro($e['totale']) ?></div><a class="vw-btn" href="<?= vwEsc($e['page']) ?>?id=<?= $id ?>&<?= vwEsc($e['key']) ?>=<?= (int)$e['id_spesa'] ?>">Apri spesa</a></article><?php endforeach; ?></div></div>
 <div class="vw-panel"><h3>Aggiungi una spesa</h3><div class="d-flex flex-wrap gap-2"><?php foreach($expenseTypes as $type): ?><?php if(has_permission($conn,'table:'.$type['table'],'insert')): ?><a class="vw-btn" href="<?= vwEsc($type['page']) ?>?id=<?= $id ?>"><?= vwEsc($type['label']) ?></a><?php endif; ?><?php endforeach; ?></div></div>
 <?php elseif($tab==='notes'): ?>
 <div class="vw-panel"><h2>Note generali della vacanza</h2><div><?= nl2br(vwEsc($trip['note'] ?? '')) ?></div><?php if($canEdit): ?><p><a class="vw-btn" href="vacanze_view.php?id=<?= $id ?>">Modifica note</a></p><?php endif; ?></div>
 <div class="vw-panel"><div class="vw-section-head"><h2>Documenti della vacanza</h2><a class="vw-btn" href="vacanze_view.php?id=<?= $id ?>">Gestisci documenti</a></div><?php foreach($docs as $d): ?><p><?= vwEsc($d['descrizione'] ?: $d['nome_file']) ?></p><?php endforeach; ?><?php if(!$docs): ?><p class="vw-muted">Nessun documento.</p><?php endif; ?></div>
 <div class="vw-panel"><h2>Note e preventivi delle singole spese</h2><p class="vw-muted">Apri la spesa dal catalogo: note e preventivi appartengono alla voce, non al singolo scenario.</p><a class="vw-btn" href="<?= vwUrl($id,'catalog') ?>">Apri catalogo</a></div>
 <?php elseif($tab==='photos'): ?>
 <div class="vw-panel"><h2>Foto della vacanza</h2><p class="vw-muted">La copertina e le fotografie disponibili si gestiscono attualmente dalla modifica della vacanza.</p><a class="vw-btn" href="vacanze_modifica.php?id=<?= $id ?>">Gestisci foto e copertina</a></div>
 <?php elseif($tab==='checklist'): ?>
 <div class="vw-section-head"><h2>Checklist della vacanza</h2><a class="vw-btn vw-btn-primary" href="vacanze_checklist.php?id=<?= $id ?>">+ Nuova voce</a></div>
 <?php $complete=count(array_filter($checklist,fn($v)=>(int)$v['completata']===1)); ?>
 <div class="vw-grid"><div class="vw-panel"><div class="vw-stat-label">Completate</div><div class="vw-stat-num"><?= $complete ?> / <?= count($checklist) ?></div></div><div class="vw-panel"><div class="vw-stat-label">Ancora da preparare</div><div class="vw-stat-num"><?= count($checklist)-$complete ?></div></div></div>
 <div class="vw-panel"><div class="vw-section-head"><h2>Voci della checklist</h2><a class="vw-btn" href="vacanze_checklist.php?id=<?= $id ?>">Apri checklist completa</a></div><?php foreach($checklist as $task): ?><p><?= (int)$task['completata']?'☑':'☐' ?> <?= vwEsc($task['voce']) ?> <span class="vw-muted">· <?= vwEsc($task['username'] ?: 'Da assegnare') ?></span></p><?php endforeach; ?><?php if(!$checklist): ?><p class="vw-muted">Nessuna voce.</p><?php endif; ?></div>
 <?php elseif($tab==='sharing'): ?>
 <div class="vw-section-head"><h2>Condividi scenari</h2><a class="vw-btn vw-btn-primary" href="vacanze_condivisioni.php?id=<?= $id ?>">+ Nuovo link</a></div>
 <div class="vw-panel"><h2>Link temporanei, in sola lettura, validi 7 giorni</h2><p class="vw-muted">Puoi selezionare uno o più scenari. Documenti e note interne non vengono pubblicati.</p><a class="vw-btn" href="vacanze_condivisioni.php?id=<?= $id ?>">Scegli scenari e genera link</a></div>
 <div class="vw-panel"><h2>Link condivisi</h2><?php foreach($shared as $share): ?><p><b><?= vwEsc($share['scenari'] ?: 'Nessuno scenario') ?></b><span class="vw-muted"> · <?= vwEsc($share['revocato_il']?'Revocato':(strtotime($share['scade_il'].' UTC')<=time()?'Scaduto':'Attivo')) ?> · scade <?= vwEsc($share['scade_il']) ?> UTC</span></p><?php endforeach; ?><?php if(!$shared): ?><p class="vw-muted">Nessun link creato.</p><?php endif; ?></div>
 <?php endif; ?>
 <nav class="vw-mobile-nav" aria-label="Navigazione mobile">
 <?php foreach(['overview'=>['bi-house','Riepilogo'],'compare'=>['bi-bar-chart','Confronta'],'catalog'=>['bi-receipt','Spese'],'checklist'=>['bi-check2-square','Checklist']] as $key=>$spec): ?><a href="<?= vwUrl($id,$key) ?>" class="<?= $tab===$key?'active':'' ?>"><i class="bi <?= $spec[0] ?>"></i><?= $spec[1] ?></a><?php endforeach; ?>
 <button type="button" id="vwMoreButton" aria-expanded="false"><i class="bi bi-three-dots"></i>Altro</button></nav>
 <div class="vw-mobile-more" id="vwMobileMore"><?php foreach(['notes'=>'Note e documenti','photos'=>'Foto','sharing'=>'Condivisione'] as $key=>$label): ?><a href="<?= vwUrl($id,$key) ?>"><?= vwEsc($label) ?></a><?php endforeach; ?></div>
</main>
<script>
(function(){
 const search=document.getElementById('vwSearch'),category=document.getElementById('vwCategory'),assigned=document.getElementById('vwAssigned');
 function filter(){ document.querySelectorAll('.vw-expense').forEach(row=>{const ok=(!search||row.dataset.search.includes(search.value.toLocaleLowerCase('it-IT')))&&(!category||!category.value||row.dataset.category===category.value)&&(!assigned||!assigned.value||row.dataset.assigned===assigned.value);row.hidden=!ok;row.style.display=ok?'':'none';});}
 [search,category,assigned].forEach(el=>{if(el)el.addEventListener(el===search?'input':'change',filter)});
 const more=document.getElementById('vwMoreButton'),menu=document.getElementById('vwMobileMore');
 if(more&&menu)more.addEventListener('click',()=>{const open=menu.classList.toggle('open');more.setAttribute('aria-expanded',String(open));});
})();
</script>
<?php include 'includes/footer.php'; ?>
