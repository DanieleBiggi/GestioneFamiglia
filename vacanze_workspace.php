<?php
// Vacanze V2: dashboard ispirata al prototipo approvato, sul tema selezionato.
include 'includes/session_check.php';
include 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'table:viaggi', 'view')) { http_response_code(403); exit('Accesso negato'); }
$conn->set_charset('utf8mb4');
$id=(int)($_GET['id'] ?? 0);
$tab=(string)($_GET['tab'] ?? 'overview');
$tabs=['overview'=>'Riepilogo','compare'=>'Confronta scenari','scenario'=>'Dettaglio scenario','catalog'=>'Catalogo spese','notes'=>'Note e documenti','photos'=>'Foto','checklist'=>'Checklist','sharing'=>'Condivisione','map'=>'Mappa'];
if ($tab==='reviews') { header('Location: vacanze_workspace.php?id='.(int)($_GET['id'] ?? 0).'&tab=sharing#recensioni',true,302); exit; }
if (!isset($tabs[$tab])) $tab='overview';
$stmt=$conn->prepare('SELECT v.*,l.nome AS luogo_nome,l.lat AS luogo_lat,l.lng AS luogo_lng FROM viaggi v LEFT JOIN viaggi_luoghi l ON l.id_luogo=v.id_luogo WHERE v.id_viaggio=?');
$stmt->bind_param('i',$id);$stmt->execute();$trip=$stmt->get_result()->fetch_assoc();$stmt->close();
if (!$trip) { http_response_code(404); exit('Vacanza non trovata'); }
function vwEsc($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function vwEuro($v) { return '€ '.number_format((float)$v,2,',','.'); }
function vwUrl($id,$tab) { return 'vacanze_workspace.php?id='.$id.'&tab='.rawurlencode($tab); }
$canEdit=has_permission($conn,'table:viaggi','update');
$canAddScenario=has_permission($conn,'ajax:add_viaggi_alternativa','insert');
$canEditScenario=has_permission($conn,'ajax:update_viaggi_alternativa','update');
$canAddPublicNote=has_permission($conn,'table:viaggi_commenti','insert');
if(empty($_SESSION['vacanze_csrf'])) $_SESSION['vacanze_csrf']=bin2hex(random_bytes(32));
$workspaceError='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($_SESSION['vacanze_csrf'],(string)($_POST['vacanze_csrf'] ?? ''))) { http_response_code(403); exit('Token non valido'); }
 $action=(string)($_POST['vw_action'] ?? '');
 if($action==='notes' && $canEdit){
   $note=(string)($_POST['note'] ?? '');
   $q=$conn->prepare('UPDATE viaggi SET note=? WHERE id_viaggio=?');
   $q->bind_param('si',$note,$id);$q->execute();$q->close();
   header('Location: '.vwUrl($id,'notes'));exit;
 } elseif($action==='public_note' && $canAddPublicNote){
   $publicNote=trim((string)($_POST['testo'] ?? ''));
   $noteAlt=(int)($_POST['id_viaggio_alternativa'] ?? 0);
   if($publicNote==='' || mb_strlen($publicNote,'UTF-8')>5000){
     $workspaceError='La nota pubblica deve contenere da 1 a 5000 caratteri.';
   } else {
     if($noteAlt>0){
       $valid=$conn->prepare('SELECT 1 FROM viaggi_alternative WHERE id_viaggio=? AND id_viaggio_alternativa=?');
       $valid->bind_param('ii',$id,$noteAlt);$valid->execute();
       $validNoteAlt=(bool)$valid->get_result()->fetch_row();$valid->close();
       if(!$validNoteAlt) $workspaceError='Scenario non valido per questa vacanza.';
     }
     if($workspaceError===''){
       $userId=(int)($_SESSION['utente_id'] ?? 0);
       if(!$userId){http_response_code(403);exit('Utente non valido');}
       $nullableAlt=$noteAlt>0?$noteAlt:null;
       $visibility='pubblico';
       $q=$conn->prepare('INSERT INTO viaggi_commenti (id_viaggio,id_viaggio_alternativa,id_utente,visibilita,testo) VALUES (?,?,?,?,?)');
       $q->bind_param('iiiss',$id,$nullableAlt,$userId,$visibility,$publicNote);
       $q->execute();$q->close();
       header('Location: '.vwUrl($id,'sharing').'#note-condivise');exit;
     }
   }
 } elseif($action==='add_scenario' && $canAddScenario){
   $desc=trim((string)($_POST['breve_descrizione'] ?? ''));
   if($desc==='' || mb_strlen($desc,'UTF-8')>100) $workspaceError='Inserisci un nome scenario (massimo 100 caratteri).';
   else {
     $q=$conn->prepare('INSERT INTO viaggi_alternative (id_viaggio,breve_descrizione) VALUES (?,?)');
     $q->bind_param('is',$id,$desc);$q->execute();$q->close();
     header('Location: '.vwUrl($id,'overview'));exit;
   }
 } elseif($action==='add_review' && has_permission($conn,'ajax:add_viaggi_feedback','insert')){
   $ratingRaw=(string)($_POST['voto'] ?? '');
   $rating=$ratingRaw===''?null:(int)$ratingRaw;
   $comment=trim((string)($_POST['commento'] ?? ''));
   if(($rating===null&&$comment==='')||($rating!==null&&($rating<0||$rating>10))||mb_strlen($comment,'UTF-8')>5000) $workspaceError='Recensione non valida.';
   else{
    $uid=(int)$_SESSION['utente_id'];
    $q=$conn->prepare('INSERT INTO viaggi_feedback (id_viaggio,id_utente,voto,commento) VALUES (?,?,?,?)');
    $q->bind_param('iiis',$id,$uid,$rating,$comment);$q->execute();$q->close();
    header('Location: '.vwUrl($id,'sharing').'#recensioni');exit;
   }
 } elseif($action==='edit_scenario' && $canEditScenario){
   $altId=(int)($_POST['id_viaggio_alternativa'] ?? 0);
   $desc=trim((string)($_POST['breve_descrizione'] ?? ''));
   if($altId<=0||$desc===''||mb_strlen($desc,'UTF-8')>100) $workspaceError='Scenario non valido.';
   else{
     $q=$conn->prepare('UPDATE viaggi_alternative SET breve_descrizione=? WHERE id_viaggio_alternativa=? AND id_viaggio=?');
     $q->bind_param('sii',$desc,$altId,$id);$q->execute();$q->close();
     header('Location: '.vwUrl($id,'overview'));exit;
   }
 } else {http_response_code(403);exit('Operazione non autorizzata');}
}

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
$publicNotes=[];
if($tab==='sharing'){
  $q=$conn->prepare("SELECT c.testo,c.creato_il,c.id_viaggio_alternativa,u.username,a.breve_descrizione FROM viaggi_commenti c LEFT JOIN utenti u ON u.id=c.id_utente LEFT JOIN viaggi_alternative a ON a.id_viaggio_alternativa=c.id_viaggio_alternativa AND a.id_viaggio=c.id_viaggio WHERE c.id_viaggio=? AND c.visibilita='pubblico' ORDER BY c.creato_il DESC,c.id_commento DESC");
  $q->bind_param('i',$id);$q->execute();$publicNotes=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
}
$shared=[];
if ($tab==='sharing') {
 $q=$conn->prepare('SELECT c.id_condivisione,c.creato_il,c.scade_il,c.revocato_il,GROUP_CONCAT(a.breve_descrizione SEPARATOR ", ") AS scenari FROM viaggi_condivisioni c LEFT JOIN viaggi_condivisioni_alternative ca ON ca.id_condivisione=c.id_condivisione LEFT JOIN viaggi_alternative a ON a.id_viaggio_alternativa=ca.id_viaggio_alternativa AND a.id_viaggio=c.id_viaggio WHERE c.id_viaggio=? GROUP BY c.id_condivisione ORDER BY c.id_condivisione DESC');
 $q->bind_param('i',$id);$q->execute();$shared=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
}
$weatherDays=[];
if($tab==='overview' && !empty($trip['meteo_previsto_json'])){
 $rawWeather=json_decode((string)$trip['meteo_previsto_json'],true);
 if(is_array($rawWeather) && !empty($rawWeather['daily']['time']) && is_array($rawWeather['daily']['time'])){
  foreach(array_slice($rawWeather['daily']['time'],0,10) as $ix=>$day){
   $weatherDays[]=['date'=>$day,'min'=>$rawWeather['daily']['temperature_2m_min'][$ix] ?? null,'max'=>$rawWeather['daily']['temperature_2m_max'][$ix] ?? null,'code'=>$rawWeather['daily']['weathercode'][$ix] ?? null];
  }
 }
}
$reviews=[];
$reviewStats=['media'=>null,'num'=>0];
if($tab==='sharing'){
 $q=$conn->prepare('SELECT vf.voto,vf.commento,vf.creato_il,u.username FROM viaggi_feedback vf LEFT JOIN utenti u ON u.id=vf.id_utente WHERE vf.id_viaggio=? ORDER BY vf.creato_il DESC');
 $q->bind_param('i',$id);$q->execute();$reviews=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
 $q=$conn->prepare('SELECT AVG(voto) AS media,COUNT(voto) AS num FROM viaggi_feedback WHERE id_viaggio=?');
 $q->bind_param('i',$id);$q->execute();$reviewStats=$q->get_result()->fetch_assoc();$q->close();
}
$tripPhotos=[];
if($tab==='photos'){
 $q=$conn->prepare('SELECT id_foto,photo_reference FROM viaggi_luogo_foto WHERE id_luogo=? ORDER BY id_foto');
 $placeId=(int)($trip['id_luogo'] ?? 0);
 $q->bind_param('i',$placeId);$q->execute();$tripPhotos=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
}
$docs=[];
if($tab==='notes'){
 $q=$conn->prepare('SELECT oc.nome_file,oc.descrizione FROM viaggi2caricamenti vc JOIN ocr_caricamenti oc ON oc.id_caricamento=vc.id_caricamento WHERE vc.id_viaggio=? ORDER BY vc.id_caricamento DESC');
 $q->bind_param('i',$id);$q->execute();$docs=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
}

// La mappa usa le associazioni multi-scenario, non il vecchio campo legacy.
$scenarioId=(int)($_GET['alt'] ?? 0);
if($tab==='scenario' && $scenarioId<=0 && $scenarios) $scenarioId=(int)$scenarios[0]['id_viaggio_alternativa'];
$scenarioData=null;
$scenarioExpenses=[];
if ($tab==='scenario' && $scenarioId>0) {
 $q=$conn->prepare('SELECT id_viaggio_alternativa,breve_descrizione FROM viaggi_alternative WHERE id_viaggio=? AND id_viaggio_alternativa=?');
 $q->bind_param('ii',$id,$scenarioId);$q->execute();$scenarioData=$q->get_result()->fetch_assoc();$q->close();
 if(!$scenarioData){http_response_code(404);exit('Scenario non trovato');}
 foreach($expenseTypes as $type){
  if(!has_permission($conn,'table:'.$type['table'],'view'))continue;
  $table=$type['table'];$link=$type['link'];$key=$type['key'];$name=$type['name'];$formula=$type['formula'];
  $q=$conn->prepare("SELECT e.`$key` AS id_spesa,e.`$name` AS voce,e.pagato,($formula) AS totale FROM `$table` e JOIN `$link` a ON a.`$key`=e.`$key` WHERE e.id_viaggio=? AND a.id_viaggio_alternativa=? ORDER BY e.`$key`");
  $q->bind_param('ii',$id,$scenarioId);$q->execute();
  $items=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
  foreach($items as &$item){$item['categoria']=$type['label'];$item['table']=$table;$item['key']=$key;$item['page']=$type['page'];$item['canEdit']=has_permission($conn,'table:'.$table,'update');$item['canCopy']=has_permission($conn,'table:'.$table,'insert');}
  unset($item);
  $scenarioExpenses=array_merge($scenarioExpenses,$items);
 }
}
$mapAlternative=(int)($_GET['alt'] ?? 0);
$mapPoints=[];
$mapScenarios=[];
if ($tab==='map') {
  $q=$conn->prepare('SELECT id_viaggio_alternativa,breve_descrizione FROM viaggi_alternative WHERE id_viaggio=? ORDER BY id_viaggio_alternativa');
  $q->bind_param('i',$id);$q->execute();$mapScenarios=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
  $validIds=array_map('intval',array_column($mapScenarios,'id_viaggio_alternativa'));
  if (!in_array($mapAlternative,$validIds,true)) $mapAlternative=$validIds[0] ?? 0;
  $addMapPoint=function($lat,$lng,$name,$kind) use (&$mapPoints) {
    if ($lat===null||$lng===null||!is_numeric($lat)||!is_numeric($lng)) return;
    $lat=(float)$lat;$lng=(float)$lng;
    if ($lat < -90||$lat > 90||$lng < -180||$lng > 180) return;
    $mapPoints[]=['lat'=>$lat,'lng'=>$lng,'title'=>(string)$name,'kind'=>$kind];
  };
  $addMapPoint($trip['luogo_lat'],$trip['luogo_lng'],$trip['luogo_nome'] ?: $trip['titolo'],'destination');
  if ($mapAlternative>0) {
    if (has_permission($conn,'table:viaggi_alloggi','view')) {
      $q=$conn->prepare('SELECT e.nome_alloggio,e.lat,e.lng FROM viaggi_alloggi e JOIN viaggi_alloggi_alternative a ON a.id_alloggio=e.id_alloggio WHERE e.id_viaggio=? AND a.id_viaggio_alternativa=?');
      $q->bind_param('ii',$id,$mapAlternative);$q->execute();
      foreach($q->get_result()->fetch_all(MYSQLI_ASSOC) as $v) $addMapPoint($v['lat'],$v['lng'],$v['nome_alloggio'],'lodging');
      $q->close();
    }
    if (has_permission($conn,'table:viaggi_tratte','view')) {
      $q=$conn->prepare('SELECT e.origine_testo,e.origine_lat,e.origine_lng,e.destinazione_testo,e.destinazione_lat,e.destinazione_lng FROM viaggi_tratte e JOIN viaggi_tratte_alternative a ON a.id_tratta=e.id_tratta WHERE e.id_viaggio=? AND a.id_viaggio_alternativa=?');
      $q->bind_param('ii',$id,$mapAlternative);$q->execute();
      foreach($q->get_result()->fetch_all(MYSQLI_ASSOC) as $v){
        $addMapPoint($v['origine_lat'],$v['origine_lng'],$v['origine_testo'],'origin');
        $addMapPoint($v['destinazione_lat'],$v['destinazione_lng'],$v['destinazione_testo'],'arrival');
      }
      $q->close();
    }
    if (has_permission($conn,'table:viaggi_pasti','view')) {
      $q=$conn->prepare('SELECT e.nome_locale,e.lat,e.lng FROM viaggi_pasti e JOIN viaggi_pasti_alternative a ON a.id_pasto=e.id_pasto WHERE e.id_viaggio=? AND a.id_viaggio_alternativa=?');
      $q->bind_param('ii',$id,$mapAlternative);$q->execute();
      foreach($q->get_result()->fetch_all(MYSQLI_ASSOC) as $v) $addMapPoint($v['lat'],$v['lng'],$v['nome_locale'],'meal');
      $q->close();
    }
  }
}

include 'includes/header.php';
?>
<link rel="stylesheet" href="assets/vacanze_workspace.css?v=5">
<main class="vw" id="vacanzeWorkspace" data-viaggio-id="<?= $id ?>" data-scenario-id="<?= $scenarioId ?>" data-csrf="<?= vwEsc($_SESSION['vacanze_csrf']) ?>">
 <?php if($workspaceError): ?><div class="alert alert-danger"><?= vwEsc($workspaceError) ?></div><?php endif; ?>
 <div class="vw-crumb"><a href="vacanze_lista.php">Vacanze</a> / <strong><?= vwEsc($trip['titolo']) ?></strong></div>
 <div class="vw-hero"><div><h1><?= vwEsc($trip['titolo']) ?></h1><div class="vw-muted"><?= vwEsc($trip['data_inizio'] ?: 'Date da definire') ?> – <?= vwEsc($trip['data_fine'] ?: 'Da definire') ?> · <?= (int)$trip['notti'] ?> notti · <?= (int)$trip['persone'] ?> persone · <span class="vw-status vw-status-<?= vwEsc($trip['stato']) ?>"><?= vwEsc(ucfirst((string)$trip['stato'])) ?></span></div></div>
 <div class="d-flex flex-wrap gap-2"><?php if($canEdit): ?><a class="vw-btn" href="vacanze_modifica.php?id=<?= $id ?>">Modifica vacanza</a><?php endif; ?><a class="vw-btn vw-btn-primary" href="<?= vwUrl($id,'catalog') ?>">+ Nuova spesa</a></div></div>
 <nav class="vw-tabs" aria-label="Sezioni della vacanza"><?php foreach($tabs as $key=>$label): ?><a class="vw-tab <?= $tab===$key?'active':'' ?>" href="<?= vwUrl($id,$key) ?>"><?= vwEsc($label) ?></a><?php endforeach; ?></nav>
 <?php if($tab==='overview'): ?>
 <div class="d-flex flex-wrap gap-2 mb-3"><a class="vw-btn" href="<?= vwUrl($id,'map') ?>"><i class="bi bi-geo-alt"></i> Apri mappa</a><a class="vw-btn" href="<?= vwUrl($id,'notes') ?>">Note e documenti</a><a class="vw-btn" href="<?= vwUrl($id,'sharing') ?>#recensioni">Recensioni e domande</a></div>
 <div class="vw-note">Le spese condivise si aggiornano in tutti gli scenari collegati. Le spese non assegnate rimangono nel catalogo e non entrano nei totali.</div>
 <div class="vw-grid">
  <div class="vw-panel"><div class="vw-stat-label">Scenari a confronto</div><div class="vw-stat-num"><?= count($scenarios) ?></div><div class="vw-muted">Combinazioni disponibili</div></div>
  <div class="vw-panel"><div class="vw-stat-label">Budget più economico</div><div class="vw-stat-num"><?= $best===null?'—':vwEuro($best) ?></div><div class="vw-muted">Tra le alternative attuali</div></div>
  <div class="vw-panel"><div class="vw-stat-label">Spese segnate come pagate</div><div class="vw-stat-num"><?= vwEuro($uniquePaid) ?></div><div class="vw-muted">Voci uniche, senza duplicazioni</div></div>
 </div>
 <div class="vw-section-head"><h2>Alternative di viaggio</h2></div>
 <?php if($canAddScenario): ?><form method="post" class="vw-panel d-flex flex-wrap gap-2 align-items-end">
 <input type="hidden" name="vacanze_csrf" value="<?= vwEsc($_SESSION['vacanze_csrf']) ?>">
 <input type="hidden" name="vw_action" value="add_scenario">
 <div class="flex-grow-1"><label class="form-label" for="newScenario">Nuovo scenario</label><input class="vw-input w-100" id="newScenario" name="breve_descrizione" maxlength="100" placeholder="Es. Auto + appartamento" required></div>
 <button class="vw-btn vw-btn-primary" type="submit">+ Aggiungi scenario</button></form><?php endif; ?>
 <div class="vw-grid"><?php foreach($scenarios as $s): ?>
 <article class="vw-scenario"><h3><?= vwEsc($s['breve_descrizione']) ?></h3><div class="vw-price"><?= vwEuro($s['totale_viaggio']) ?></div><div class="vw-split"><span>Trasporti</span><b><?= vwEuro($s['totale_trasporti']) ?></b><span>Alloggi</span><b><?= vwEuro($s['totale_alloggi']) ?></b><span>Pasti</span><b><?= vwEuro($s['totale_pasti']) ?></b><span>Altri costi</span><b><?= vwEuro($s['totale_altri_costi']) ?></b></div><a class="vw-btn" href="<?= vwUrl($id,'scenario') ?>&alt=<?= (int)$s['id_viaggio_alternativa'] ?>">Gestisci spese</a>
 <?php if($canEditScenario): ?><form method="post" class="d-flex flex-wrap gap-2 mt-3">
 <input type="hidden" name="vacanze_csrf" value="<?= vwEsc($_SESSION['vacanze_csrf']) ?>">
 <input type="hidden" name="vw_action" value="edit_scenario">
 <input type="hidden" name="id_viaggio_alternativa" value="<?= (int)$s['id_viaggio_alternativa'] ?>">
 <input class="vw-input flex-grow-1" name="breve_descrizione" maxlength="100" value="<?= vwEsc($s['breve_descrizione']) ?>" aria-label="Nome dello scenario" required>
 <button class="vw-btn" type="submit">Rinomina</button>
 </form><?php endif; ?></article>
 <?php endforeach; ?><?php if(!$scenarios): ?><p class="vw-empty">Nessuno scenario.</p><?php endif; ?></div>
 <div class="vw-panel"><div class="vw-section-head"><h2>Spese ancora da assegnare</h2><a class="vw-btn" href="<?= vwUrl($id,'catalog') ?>&filter=unassigned">Apri catalogo</a></div>
 <?php if(!$unassigned): ?><p class="vw-muted">Tutte le spese sono assegnate.</p><?php endif; ?>
 <?php foreach(array_slice($unassigned,0,5) as $e): ?><p><a href="<?= vwEsc($e['page']) ?>?id=<?= $id ?>&<?= vwEsc($e['key']) ?>=<?= (int)$e['id_spesa'] ?>"><?= vwEsc($e['voce'] ?: $e['categoria']) ?></a> · <?= vwEuro($e['totale']) ?></p><?php endforeach; ?></div>
 <?php if($weatherDays): ?><section class="vw-panel">
 <h2>Meteo previsto</h2><div class="vw-grid">
 <?php foreach($weatherDays as $day): ?><div class="vw-note">
 <b><?= vwEsc($day['date']) ?></b><div class="vw-muted">Codice meteo: <?= vwEsc($day['code']===null?'n.d.':$day['code']) ?></div>
 <div><?= $day['min']===null?'—':number_format((float)$day['min'],1,',','.') ?>° / <?= $day['max']===null?'—':number_format((float)$day['max'],1,',','.') ?>°</div>
 </div><?php endforeach; ?>
 </div></section><?php endif; ?>
 <?php elseif($tab==='compare'): ?>
 <div class="vw-section-head"><h2>Confronto dei budget</h2><a class="vw-btn" href="<?= vwUrl($id,'catalog') ?>">Gestisci le spese</a></div>
 <div class="vw-panel"><div class="vw-table-wrap"><table class="vw-table"><thead><tr><th>Scenario</th><th>Trasporti</th><th>Alloggi</th><th>Pasti</th><th>Altri costi</th><th class="vw-money">Totale</th></tr></thead><tbody>
 <?php foreach($scenarios as $s): ?><tr><td><?= vwEsc($s['breve_descrizione']) ?></td><td><?= vwEuro($s['totale_trasporti']) ?></td><td><?= vwEuro($s['totale_alloggi']) ?></td><td><?= vwEuro($s['totale_pasti']) ?></td><td><?= vwEuro($s['totale_altri_costi']) ?></td><td class="vw-money"><b><?= vwEuro($s['totale_viaggio']) ?></b></td></tr><?php endforeach; ?></tbody></table></div>
 <div class="vw-card-mobile"><?php foreach($scenarios as $s): ?><article><h3><?= vwEsc($s['breve_descrizione']) ?></h3><div class="vw-price"><?= vwEuro($s['totale_viaggio']) ?></div><div class="vw-split"><span>Trasporti</span><b><?= vwEuro($s['totale_trasporti']) ?></b><span>Alloggi</span><b><?= vwEuro($s['totale_alloggi']) ?></b><span>Pasti</span><b><?= vwEuro($s['totale_pasti']) ?></b><span>Altri costi</span><b><?= vwEuro($s['totale_altri_costi']) ?></b></div></article><?php endforeach; ?></div></div>
 <div class="vw-panel"><h2>Come funzionano le spese condivise</h2><p>Una spesa può appartenere a più scenari senza essere duplicata. Se cambi il costo, la modifica si riflette in tutte le alternative collegate.</p></div>
 <?php elseif($tab==='scenario'): ?>
 <div class="vw-section-head"><h2>Scenario: <?= vwEsc($scenarioData['breve_descrizione'] ?? 'Seleziona uno scenario') ?></h2><div class="d-flex gap-2 flex-wrap"><a class="vw-btn" href="<?= vwUrl($id,'overview') ?>">Tutti gli scenari</a><a class="vw-btn" href="<?= vwUrl($id,'map') ?>&alt=<?= $scenarioId ?>">Mostra sulla mappa</a></div></div>
 <?php if($scenarioData): ?>
 <?php if(has_permission($conn,'ajax:delete_viaggi_alternativa','delete')): ?>
 <button type="button" class="vw-btn vw-delete-scenario mb-3" data-alt="<?= $scenarioId ?>" style="background:#925a5a!important;border-color:#bd8b8b!important">Elimina scenario senza eliminare le spese</button>
 <?php endif; ?>
 <div class="vw-panel"><h3>Spese associate</h3>
 <?php if(!$scenarioExpenses): ?><p class="vw-empty">Questo scenario non ha ancora spese assegnate.</p><?php endif; ?>
 <div class="vw-table-wrap"><table class="vw-table"><thead><tr><th>Voce</th><th>Categoria</th><th>Pagamento</th><th class="vw-money">Importo</th><th>Azioni</th></tr></thead><tbody>
 <?php foreach($scenarioExpenses as $e): ?><tr><td><?= vwEsc($e['voce'] ?: $e['categoria']) ?></td><td><?= vwEsc($e['categoria']) ?></td><td><?php if($e['canEdit']): ?><label><input type="checkbox" class="vw-payment" data-table="<?= vwEsc($e['table']) ?>" data-id="<?= (int)$e['id_spesa'] ?>" <?= (int)$e['pagato']?'checked':'' ?>> Pagata</label><?php else: ?><?= (int)$e['pagato']?'Pagata':'Da pagare' ?><?php endif; ?></td><td class="vw-money"><?= vwEuro($e['totale']) ?></td><td class="d-flex gap-2 flex-wrap"><?php if($e['canEdit']): ?><a class="vw-btn" href="<?= vwEsc($e['page']) ?>?id=<?= $id ?>&alt=<?= $scenarioId ?>&<?= vwEsc($e['key']) ?>=<?= (int)$e['id_spesa'] ?>">Modifica</a><?php endif; ?><?php if($e['canCopy']): ?><a class="vw-btn" href="<?= vwEsc($e['page']) ?>?id=<?= $id ?>&alt=<?= $scenarioId ?>&<?= vwEsc($e['key']) ?>=<?= (int)$e['id_spesa'] ?>&duplica=1">Duplica</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
 <div class="vw-card-mobile"><?php foreach($scenarioExpenses as $e): ?><article><h3><?= vwEsc($e['voce'] ?: $e['categoria']) ?></h3><p class="vw-muted"><?= vwEsc($e['categoria']) ?> · <?= (int)$e['pagato']?'Pagata':'Da pagare' ?></p><div class="vw-price"><?= vwEuro($e['totale']) ?></div><div class="d-flex gap-2"><?php if($e['canEdit']): ?><a class="vw-btn" href="<?= vwEsc($e['page']) ?>?id=<?= $id ?>&alt=<?= $scenarioId ?>&<?= vwEsc($e['key']) ?>=<?= (int)$e['id_spesa'] ?>">Modifica</a><?php endif; ?><?php if($e['canCopy']): ?><a class="vw-btn" href="<?= vwEsc($e['page']) ?>?id=<?= $id ?>&alt=<?= $scenarioId ?>&<?= vwEsc($e['key']) ?>=<?= (int)$e['id_spesa'] ?>&duplica=1">Duplica</a><?php endif; ?></div></article><?php endforeach; ?></div>
 </div>
 <div class="vw-panel"><h3>Aggiungi una spesa allo scenario</h3><div class="d-flex flex-wrap gap-2"><?php foreach($expenseTypes as $type): ?><?php if(has_permission($conn,'table:'.$type['table'],'insert')): ?><a class="vw-btn" href="<?= vwEsc($type['page']) ?>?id=<?= $id ?>&alt=<?= $scenarioId ?>"><?= vwEsc($type['label']) ?></a><?php endif; ?><?php endforeach; ?></div></div>
 <?php endif; ?>
 <?php elseif($tab==='catalog'): ?>
 <div class="vw-section-head"><h2>Catalogo delle spese</h2><a class="vw-btn vw-btn-primary" href="<?= vwUrl($id,'catalog') ?>&filter=unassigned">Spese non assegnate</a></div>
 <div class="vw-panel"><div class="vw-filter"><input class="vw-input" id="vwSearch" placeholder="Cerca una voce o una nota"><select class="vw-input" id="vwCategory"><option value="">Tutte le categorie</option><?php foreach($expenseTypes as $type): ?><option><?= vwEsc($type['label']) ?></option><?php endforeach; ?></select><select class="vw-input" id="vwAssigned"><option value="">Tutte le associazioni</option><option value="assigned">Assegnate</option><option value="unassigned">Non assegnate</option></select></div>
 <div class="vw-table-wrap"><table class="vw-table"><thead><tr><th>Voce</th><th>Categoria</th><th>Scenari collegati</th><th>Stato</th><th class="vw-money">Importo</th><th></th></tr></thead><tbody>
 <?php foreach($expenses as $e): ?><tr class="vw-expense" data-category="<?= vwEsc($e['categoria']) ?>" data-assigned="<?= (int)$e['num_scenari']?'assigned':'unassigned' ?>" data-search="<?= vwEsc(mb_strtolower((string)$e['voce'].' '.(string)$e['note'])) ?>"><td><?= vwEsc($e['voce'] ?: $e['categoria']) ?></td><td><?= vwEsc($e['categoria']) ?></td><td><?= vwEsc($e['scenari'] ?: 'Non assegnata') ?></td><td><?= (int)$e['pagato']?'Pagata':'Da pagare' ?></td><td class="vw-money"><?= vwEuro($e['totale']) ?></td><td><a class="vw-btn" href="<?= vwEsc($e['page']) ?>?id=<?= $id ?>&<?= vwEsc($e['key']) ?>=<?= (int)$e['id_spesa'] ?>">Apri</a></td></tr><?php endforeach; ?>
 </tbody></table></div><div class="vw-card-mobile"><?php foreach($expenses as $e): ?><article class="vw-expense" data-category="<?= vwEsc($e['categoria']) ?>" data-assigned="<?= (int)$e['num_scenari']?'assigned':'unassigned' ?>" data-search="<?= vwEsc(mb_strtolower((string)$e['voce'].' '.(string)$e['note'])) ?>"><h3><?= vwEsc($e['voce'] ?: $e['categoria']) ?></h3><p class="vw-muted"><?= vwEsc($e['categoria']) ?> · <?= vwEsc($e['scenari'] ?: 'Non assegnata') ?></p><div class="vw-price"><?= vwEuro($e['totale']) ?></div><a class="vw-btn" href="<?= vwEsc($e['page']) ?>?id=<?= $id ?>&<?= vwEsc($e['key']) ?>=<?= (int)$e['id_spesa'] ?>">Apri spesa</a></article><?php endforeach; ?></div></div>
 <div class="vw-panel"><h3>Aggiungi una spesa</h3><div class="d-flex flex-wrap gap-2"><?php foreach($expenseTypes as $type): ?><?php if(has_permission($conn,'table:'.$type['table'],'insert')): ?><a class="vw-btn" href="<?= vwEsc($type['page']) ?>?id=<?= $id ?>"><?= vwEsc($type['label']) ?></a><?php endif; ?><?php endforeach; ?></div></div>
 <?php elseif($tab==='notes'): ?>
 <div class="vw-panel"><h2>Note generali della vacanza</h2>
 <?php if($canEdit): ?><form method="post">
 <input type="hidden" name="vacanze_csrf" value="<?= vwEsc($_SESSION['vacanze_csrf']) ?>">
 <input type="hidden" name="vw_action" value="notes">
 <textarea class="vw-input w-100 mb-3" name="note" rows="6"><?= vwEsc($trip['note'] ?? '') ?></textarea>
 <button class="vw-btn vw-btn-primary" type="submit">Salva note</button>
 </form><?php else: ?><div><?= nl2br(vwEsc($trip['note'] ?? '')) ?></div><?php endif; ?>
 </div>
 <div class="vw-panel"><h2>Documenti della vacanza</h2>
 <?php foreach($docs as $d): ?>
 <?php $docIsUrl=filter_var($d['nome_file'],FILTER_VALIDATE_URL);$docHref=$docIsUrl?$d['nome_file']:'files/vacanze/'.rawurlencode((string)$d['nome_file']); ?>
 <p><a href="<?= vwEsc($docHref) ?>" target="_blank" rel="noopener noreferrer"><?= vwEsc($d['descrizione'] ?: $d['nome_file']) ?></a></p>
 <?php endforeach; ?>
 <?php if(!$docs): ?><p class="vw-muted">Nessun documento.</p><?php endif; ?>
 <?php if(has_permission($conn,'ajax:add_viaggi_documento','insert')): ?>
 <form id="vwDocForm" class="d-flex flex-column gap-2 mt-3" enctype="multipart/form-data">
 <label class="form-label">Aggiungi documento o link</label>
 <input type="file" name="file" class="vw-input">
 <input type="url" name="link" class="vw-input" placeholder="In alternativa, URL del documento">
 <input type="text" name="descrizione" class="vw-input" maxlength="255" placeholder="Descrizione">
 <button type="submit" class="vw-btn vw-btn-primary align-self-start">Aggiungi documento</button>
 <p id="vwDocResult" role="status" class="vw-muted"></p>
 </form><?php endif; ?>
 </div>
 <div class="vw-panel"><h2>Note e preventivi delle singole spese</h2><p class="vw-muted">Apri la spesa dal catalogo: note e preventivi appartengono alla voce, non al singolo scenario.</p><a class="vw-btn" href="<?= vwUrl($id,'catalog') ?>">Apri catalogo</a></div>
 <?php elseif($tab==='photos'): ?>
 <div class="vw-panel"><div class="vw-section-head"><h2>Foto della vacanza</h2><?php if($canEdit): ?><a class="vw-btn" href="vacanze_modifica.php?id=<?= $id ?>">Modifica copertina</a><?php endif; ?></div>
 <?php if(!$tripPhotos): ?><p class="vw-muted">Nessuna foto disponibile per la destinazione selezionata.</p><?php endif; ?>
 <div class="vw-grid">
 <?php foreach($tripPhotos as $photo): ?>
 <?php $photoRef=(string)$photo['photo_reference'];$apiKey=(string)($config['GOOGLE_PLACES_FOTO_API'] ?? ''); ?>
 <?php if($apiKey!==''): ?><figure class="vw-scenario" style="padding:6px;margin:0"><img loading="lazy" style="width:100%;height:210px;object-fit:cover;border-radius:8px" alt="Foto della destinazione" src="https://maps.googleapis.com/maps/api/place/photo?maxwidth=700&amp;photo_reference=<?= rawurlencode($photoRef) ?>&amp;key=<?= rawurlencode($apiKey) ?>"></figure><?php endif; ?>
 <?php endforeach; ?>
 </div>
 </div>
 <?php elseif($tab==='checklist'): ?>
 <div class="vw-section-head"><h2>Checklist della vacanza</h2><a class="vw-btn vw-btn-primary" href="vacanze_checklist.php?id=<?= $id ?>">+ Nuova voce</a></div>
 <?php $complete=count(array_filter($checklist,fn($v)=>(int)$v['completata']===1)); ?>
 <div class="vw-grid"><div class="vw-panel"><div class="vw-stat-label">Completate</div><div class="vw-stat-num"><?= $complete ?> / <?= count($checklist) ?></div></div><div class="vw-panel"><div class="vw-stat-label">Ancora da preparare</div><div class="vw-stat-num"><?= count($checklist)-$complete ?></div></div></div>
 <div class="vw-panel"><div class="vw-section-head"><h2>Voci della checklist</h2><a class="vw-btn" href="vacanze_checklist.php?id=<?= $id ?>">Apri checklist completa</a></div><?php foreach($checklist as $task): ?><p><?= (int)$task['completata']?'☑':'☐' ?> <?= vwEsc($task['voce']) ?> <span class="vw-muted">· <?= vwEsc($task['username'] ?: 'Da assegnare') ?></span></p><?php endforeach; ?><?php if(!$checklist): ?><p class="vw-muted">Nessuna voce.</p><?php endif; ?></div>
 <?php elseif($tab==='sharing'): ?>
 <div class="vw-section-head"><h2>Condividi scenari</h2><a class="vw-btn vw-btn-primary" href="vacanze_condivisioni.php?id=<?= $id ?>">+ Nuovo link</a></div>
 <div class="vw-panel"><h2>Link temporanei, in sola lettura, validi 7 giorni</h2><p class="vw-muted">Puoi selezionare uno o più scenari. Documenti e note interne non vengono pubblicati.</p><a class="vw-btn" href="vacanze_condivisioni.php?id=<?= $id ?>">Scegli scenari e genera link</a></div>
 <section class="vw-panel" id="note-condivise">
 <h2>Note visibili nei link condivisi</h2>
 <p class="vw-muted">Le note generali della vacanza restano private. Le note inserite qui saranno leggibili da chi possiede un link valido che include lo scenario selezionato. Una nota riferita all'intera vacanza sarà visibile in tutti i link validi.</p>
 <?php if($canAddPublicNote): ?>
 <form method="post" class="d-flex flex-column gap-2 mb-3">
 <input type="hidden" name="vacanze_csrf" value="<?= vwEsc($_SESSION['vacanze_csrf']) ?>">
 <input type="hidden" name="vw_action" value="public_note">
 <label for="vwPublicNote">Nuova nota condivisibile</label>
 <textarea id="vwPublicNote" name="testo" class="vw-input w-100" maxlength="5000" rows="4" required placeholder="Informazioni da mostrare a chi riceve il link…"><?= vwEsc((string)($_POST['vw_action'] ?? '')==='public_note'?($_POST['testo'] ?? ''):'') ?></textarea>
 <label for="vwPublicNoteAlt">Visibile per</label>
 <select class="vw-input w-100" id="vwPublicNoteAlt" name="id_viaggio_alternativa">
 <option value="0">Intera vacanza (tutti i link validi)</option>
 <?php foreach($scenarios as $s): ?><option value="<?= (int)$s['id_viaggio_alternativa'] ?>"><?= vwEsc($s['breve_descrizione']) ?></option><?php endforeach; ?>
 </select>
 <button class="vw-btn vw-btn-primary align-self-start" type="submit">Pubblica nota nel link</button>
 </form>
 <?php else: ?>
 <p class="vw-muted">Per aggiungere note condivise serve il permesso di inserimento dei commenti.</p>
 <?php endif; ?>
 <h3>Note già condivisibili</h3>
 <?php if(!$publicNotes): ?><p class="vw-muted">Nessuna nota pubblica inserita.</p><?php endif; ?>
 <?php foreach($publicNotes as $note): ?>
 <article class="vw-note"><div class="vw-muted mb-2"><?= vwEsc($note['username'] ?? 'Utente') ?> · <?= vwEsc($note['creato_il']) ?> · <?= $note['id_viaggio_alternativa']===null?'Intera vacanza':vwEsc($note['breve_descrizione'] ?? 'Scenario') ?></div><div><?= nl2br(vwEsc($note['testo'])) ?></div></article>
 <?php endforeach; ?>
 </section>
 <div class="vw-panel"><h2>Link condivisi</h2><?php foreach($shared as $share): ?><p><b><?= vwEsc($share['scenari'] ?: 'Nessuno scenario') ?></b><span class="vw-muted"> · <?= vwEsc($share['revocato_il']?'Revocato':(strtotime($share['scade_il'].' UTC')<=time()?'Scaduto':'Attivo')) ?> · scade <?= vwEsc($share['scade_il']) ?> UTC</span></p><?php endforeach; ?><?php if(!$shared): ?><p class="vw-muted">Nessun link creato.</p><?php endif; ?></div>
 
 <div class="vw-section-head" id="recensioni"><h2>Recensioni e domande</h2></div>
 <div class="vw-panel"><p class="vw-muted">Valutazione media: <b><?= (int)($reviewStats['num'] ?? 0)>0?number_format((float)$reviewStats['media'],1,',','.').' / 10':'Non disponibile' ?></b> · <?= (int)($reviewStats['num'] ?? 0) ?> voti</p>
 <?php if(has_permission($conn,'ajax:add_viaggi_feedback','insert')): ?>
 <form method="post" class="d-flex flex-column gap-2">
 <input type="hidden" name="vacanze_csrf" value="<?= vwEsc($_SESSION['vacanze_csrf']) ?>">
 <input type="hidden" name="vw_action" value="add_review">
 <label for="vwRating">Voto (facoltativo, da 0 a 10)</label><input id="vwRating" class="vw-input" name="voto" type="number" min="0" max="10" style="max-width:130px">
 <label for="vwReviewText">Recensione o domanda</label><textarea id="vwReviewText" class="vw-input w-100" rows="3" name="commento" maxlength="5000"></textarea>
 <button class="vw-btn vw-btn-primary align-self-start" type="submit">Pubblica</button>
 </form><?php endif; ?>
 </div>
 <?php foreach($reviews as $review): ?><article class="vw-panel"><b><?= vwEsc($review['username'] ?: 'Utente') ?></b> <span class="vw-muted"><?= vwEsc($review['creato_il']) ?></span> <?php if($review['voto']!==null): ?><span class="vw-pill"><?= (int)$review['voto'] ?>/10</span><?php endif; ?><p class="mt-2"><?= nl2br(vwEsc($review['commento'])) ?></p></article><?php endforeach; ?>
 <?php if(!$reviews): ?><div class="vw-panel vw-muted">Non ci sono ancora recensioni o domande.</div><?php endif; ?>
 <?php elseif($tab==='map'): ?>
 <div class="vw-section-head"><h2>Mappa della vacanza</h2><a class="vw-btn" href="<?= vwUrl($id,'overview') ?>">Torna al riepilogo</a></div>
 <div class="vw-panel">
 <p class="vw-muted">Seleziona uno scenario per visualizzare le tappe, gli alloggi e i locali collegati. La mappa regola automaticamente l'inquadratura.</p>
 <nav class="vw-map-scopes" aria-label="Scenario sulla mappa">
 <?php foreach($mapScenarios as $ms): ?><a class="<?= (int)$ms['id_viaggio_alternativa']===$mapAlternative?'active':'' ?>" href="<?= vwUrl($id,'map') ?>&alt=<?= (int)$ms['id_viaggio_alternativa'] ?>"><?= vwEsc($ms['breve_descrizione']) ?></a><?php endforeach; ?>
 </nav>
 <?php if(empty($config['GOOGLE_MAPS_API'])): ?>
 <p class="vw-empty">La chiave Google Maps non è configurata per questa installazione.</p>
 <?php elseif(!$mapPoints): ?>
 <p class="vw-empty">Aggiungi coordinate alla destinazione o alle spese per visualizzarle sulla mappa.</p>
 <?php else: ?>
 <div id="vacanzeMap" class="vw-map" role="region" aria-label="Mappa delle tappe della vacanza"></div>
 <div class="vw-map-legend"><span><i>●</i> Destinazione</span><span><i>●</i> Alloggi</span><span><i>●</i> Partenza</span><span><i>●</i> Arrivo</span><span><i>●</i> Ristoranti</span></div>
 <?php endif; ?>
 </div>
 <?php endif; ?>
 <nav class="vw-mobile-nav" aria-label="Navigazione mobile">
 <?php foreach(['overview'=>['bi-house','Riepilogo'],'compare'=>['bi-bar-chart','Confronta'],'catalog'=>['bi-receipt','Spese'],'checklist'=>['bi-check2-square','Checklist']] as $key=>$spec): ?><a href="<?= vwUrl($id,$key) ?>" class="<?= $tab===$key?'active':'' ?>"><i class="bi <?= $spec[0] ?>"></i><?= $spec[1] ?></a><?php endforeach; ?>
 <button type="button" id="vwMoreButton" aria-expanded="false"><i class="bi bi-three-dots"></i>Altro</button></nav>
 <div class="vw-mobile-more" id="vwMobileMore"><?php foreach(['notes'=>'Note e documenti','photos'=>'Foto','sharing'=>'Condivisione','map'=>'Mappa'] as $key=>$label): ?><a href="<?= vwUrl($id,$key) ?>"><?= vwEsc($label) ?></a><?php endforeach; ?></div>
</main>
<script>
(function(){
 const search=document.getElementById('vwSearch'),category=document.getElementById('vwCategory'),assigned=document.getElementById('vwAssigned');
 function filter(){ document.querySelectorAll('.vw-expense').forEach(row=>{const ok=(!search||row.dataset.search.includes(search.value.toLocaleLowerCase('it-IT')))&&(!category||!category.value||row.dataset.category===category.value)&&(!assigned||!assigned.value||row.dataset.assigned===assigned.value);row.hidden=!ok;row.style.display=ok?'':'none';});}
 if(assigned && new URLSearchParams(location.search).get('filter')==='unassigned'){assigned.value='unassigned';filter();}
 [search,category,assigned].forEach(el=>{if(el)el.addEventListener(el===search?'input':'change',filter)});
 const deleteScenario=document.querySelector('.vw-delete-scenario');
 if(deleteScenario)deleteScenario.addEventListener('click',async()=>{
 if(!confirm('Eliminare questo scenario? Le spese resteranno disponibili negli altri scenari o nel catalogo.'))return;
 deleteScenario.disabled=true;
 const data=new FormData();data.set('id_viaggio',document.getElementById('vacanzeWorkspace').dataset.viaggioId);
 data.set('id_viaggio_alternativa',deleteScenario.dataset.alt);
 data.set('vacanze_csrf',document.getElementById('vacanzeWorkspace').dataset.csrf);
 try{const resp=await fetch('ajax/delete_viaggi_alternativa.php',{method:'POST',body:data});const json=await resp.json();
 if(!resp.ok||!json.success)throw new Error(json.error||'Errore durante l’eliminazione');
 location.href='vacanze_workspace.php?id='+encodeURIComponent(data.get('id_viaggio'))+'&tab=overview';
 }catch(err){alert(err.message||'Impossibile eliminare lo scenario');deleteScenario.disabled=false;}
 });
 const toggles=document.querySelectorAll('.vw-payment');
 toggles.forEach(toggle=>toggle.addEventListener('change',async()=>{
 const value=toggle.checked?1:0;toggle.disabled=true;
 const data=new FormData();data.set('table',toggle.dataset.table);data.set('id',toggle.dataset.id);
 data.set('id_viaggio',document.getElementById('vacanzeWorkspace').dataset.viaggioId);
 data.set('id_viaggio_alternativa',document.getElementById('vacanzeWorkspace').dataset.scenarioId);
 data.set('value',String(value));data.set('vacanze_csrf',document.getElementById('vacanzeWorkspace').dataset.csrf);
 try{const resp=await fetch('ajax/update_viaggi_pagamento.php',{method:'POST',body:data});const json=await resp.json();
 if(!resp.ok||!json.success)throw new Error(json.error||'Errore');
 }catch(err){toggle.checked=!toggle.checked;alert(err.message||'Impossibile aggiornare');}
 finally{toggle.disabled=false;}
 }));
 const docs=document.getElementById('vwDocForm');
 if(docs)docs.addEventListener('submit',async e=>{
  e.preventDefault();const status=document.getElementById('vwDocResult');status.textContent='Salvataggio…';
  const fd=new FormData(docs);fd.append('id_viaggio',Number(document.getElementById('vacanzeWorkspace')?.dataset.viaggioId||0));
  try{const resp=await fetch('ajax/add_viaggi_documento.php',{method:'POST',body:fd});const result=await resp.json();
  if(!resp.ok||!result.success)throw new Error(result.error||'Errore nel salvataggio');location.reload();
  }catch(err){status.textContent=err.message||'Impossibile salvare il documento';}
 });
 const more=document.getElementById('vwMoreButton'),menu=document.getElementById('vwMobileMore');
 if(more&&menu)more.addEventListener('click',()=>{const open=menu.classList.toggle('open');more.setAttribute('aria-expanded',String(open));});
})();
</script>
<?php if($tab==='map' && $mapPoints && !empty($config['GOOGLE_MAPS_API'])): ?>
<script>
window.vacanzeMapPoints = <?= json_encode($mapPoints, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
window.initVacanzeMap = function(){
 const node=document.getElementById('vacanzeMap');
 if(!node || !window.google || !google.maps) return;
 const map=new google.maps.Map(node,{center:{lat:41.9,lng:12.5},zoom:6,mapTypeControl:false,streetViewControl:false,fullscreenControl:true,gestureHandling:'cooperative'});
 const bounds=new google.maps.LatLngBounds();
 const palette={destination:'#435f86',lodging:'#648c63',origin:'#c28c3e',arrival:'#c76a55',meal:'#8b6cac'};
 const unique=new Set();
 let count=0;
 (window.vacanzeMapPoints||[]).forEach(point=>{
   const key=point.kind+'|'+point.lat+'|'+point.lng+'|'+point.title;
   if(unique.has(key))return;unique.add(key);
   const pos={lat:Number(point.lat),lng:Number(point.lng)};
   if(!Number.isFinite(pos.lat)||!Number.isFinite(pos.lng))return;
   const marker=new google.maps.Marker({map,position:pos,title:point.title||'Tappa',icon:{
     path:google.maps.SymbolPath.CIRCLE,scale:7,fillColor:palette[point.kind]||'#687481',fillOpacity:1,
     strokeColor:'#ffffff',strokeWeight:2}});
   const info=new google.maps.InfoWindow({content:document.createElement('div')});
   const title=document.createElement('span');title.textContent=point.title||'Tappa';
   info.setContent(title);
   marker.addListener('click',()=>info.open(map,marker));
   bounds.extend(pos);count++;
 });
 if(count>1){
   map.fitBounds(bounds,55);
   google.maps.event.addListenerOnce(map,'idle',()=>{if(map.getZoom()>13)map.setZoom(13);});
 } else if(count===1){map.setCenter(bounds.getCenter());map.setZoom(11);}
};
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= rawurlencode((string)$config['GOOGLE_MAPS_API']) ?>&callback=initVacanzeMap&loading=async" async defer></script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
