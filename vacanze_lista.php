<?php
include 'includes/session_check.php';
include 'includes/db.php';
require_once 'includes/permissions.php';

if (!has_permission($conn, 'table:viaggi', 'view') && !has_permission($conn, 'page:vacanze.php', 'view')) {
    http_response_code(403); exit('Accesso negato');
}
$conn->set_charset('utf8mb4');
function vacanzeH($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function vacanzeEuro($value): string { return '€ '.number_format((float)$value,2,',','.'); }
$stati = ['idea','shortlist','pianificato','prenotato','fatto','scartato'];
$ordinamenti = [
 'data_desc'=>'Data inizio: più recenti',
 'data_asc'=>'Data inizio: meno recenti',
 'titolo_asc'=>'Titolo: A-Z',
 'titolo_desc'=>'Titolo: Z-A',
 'prezzo_asc'=>'Budget: crescente',
 'prezzo_desc'=>'Budget: decrescente',
 'stato_asc'=>'Stato',
 'creazione_desc'=>'Ultime aggiunte'
];
$ordiniSql = [
 'data_desc'=>'v.data_inizio DESC, v.id_viaggio DESC',
 'data_asc'=>'v.data_inizio ASC, v.id_viaggio ASC',
 'titolo_asc'=>'v.titolo ASC, v.id_viaggio DESC',
 'titolo_desc'=>'v.titolo DESC, v.id_viaggio DESC',
 'prezzo_asc'=>'min_totale IS NULL ASC, min_totale ASC, v.id_viaggio DESC',
 'prezzo_desc'=>'min_totale IS NULL ASC, min_totale DESC, v.id_viaggio DESC',
 'stato_asc'=>'v.stato ASC, v.data_inizio DESC',
 'creazione_desc'=>'v.id_viaggio DESC'
];
$search=trim((string)($_GET['q'] ?? ''));
$stato=(string)($_GET['stato'] ?? '');
$notti=(string)($_GET['notti'] ?? '');
$prezzoMin=(string)($_GET['prezzo_min'] ?? '');
$prezzoMax=(string)($_GET['prezzo_max'] ?? ($_GET['budget_max'] ?? ''));
$ordine=(string)($_GET['ordine'] ?? 'data_desc');
if (!isset($ordiniSql[$ordine])) $ordine='data_desc';
if (!in_array($stato,$stati,true)) $stato='';
$canInsert=has_permission($conn,'table:viaggi','insert');
$canUpdate=has_permission($conn,'table:viaggi','update');

$sql="SELECT v.id_viaggio,v.titolo,v.breve_descrizione,v.stato,v.data_inizio,v.data_fine,v.notti,v.persone,
 l.nome AS luogo_nome,l.citta,l.regione,l.paese,
 (SELECT MIN(t.totale_viaggio) FROM v_totali_alternative t WHERE t.id_viaggio=v.id_viaggio) AS min_totale,
 (SELECT COUNT(*) FROM viaggi_alternative a WHERE a.id_viaggio=v.id_viaggio) AS num_alternative
 FROM viaggi v LEFT JOIN viaggi_luoghi l ON l.id_luogo=v.id_luogo WHERE 1=1";
$types='';$params=[];
if($search!==''){
 $sql.=" AND (v.titolo LIKE ? OR v.breve_descrizione LIKE ? OR l.nome LIKE ? OR l.citta LIKE ? OR l.regione LIKE ? OR l.paese LIKE ?)";
 $like='%'.$search.'%';$types.='ssssss';
 for($i=0;$i<6;$i++)$params[]=$like;
}
if($stato!==''){$sql.=" AND v.stato=?";$types.='s';$params[]=$stato;}
if($notti!=='' && ctype_digit($notti)){$sql.=" AND v.notti=?";$types.='i';$params[]=(int)$notti;}
if($prezzoMin!=='' && is_numeric($prezzoMin)){
 $sql.=" AND (SELECT MIN(t.totale_viaggio) FROM v_totali_alternative t WHERE t.id_viaggio=v.id_viaggio)>=?";
 $types.='d';$params[]=(float)$prezzoMin;
}
if($prezzoMax!=='' && is_numeric($prezzoMax)){
 $sql.=" AND (SELECT MIN(t.totale_viaggio) FROM v_totali_alternative t WHERE t.id_viaggio=v.id_viaggio)<=?";
 $types.='d';$params[]=(float)$prezzoMax;
}
$sql.=" ORDER BY ".$ordiniSql[$ordine];
$stmt=$conn->prepare($sql);
if($types) $stmt->bind_param($types,...$params);
$stmt->execute();$vacanze=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
$count=count($vacanze);
if(isset($_GET['count'])){
 header('Content-Type: application/json; charset=utf-8');
 echo json_encode(['count'=>$count]);exit;
}
include 'includes/header.php';
?>
<link rel="stylesheet" href="assets/vacanze_workspace.css?v=4">
<style>
.vw-list-actions{display:flex;gap:9px;flex-wrap:wrap;align-items:center}
.vw-trip{display:flex;flex-direction:column;gap:11px;text-decoration:none;color:inherit;border-radius:12px;border:1px solid var(--vw-line);background:var(--vw-surface);padding:18px;min-height:195px}
.vw-trip:hover{color:inherit;outline:2px solid var(--vw-line)}
.vw-trip h2{margin:0;font-size:1.15rem}.vw-trip-meta{color:var(--vw-dim);font-size:.9rem}
.vw-trip-bottom{display:flex;align-items:end;justify-content:space-between;gap:12px;margin-top:auto}
.vw-list-select{background:var(--vw-surface);color:var(--vw-fg);border:1px solid var(--vw-line);border-radius:8px;padding:9px}
.vw-card-actions{display:flex;gap:8px}.vw-filter-bar{display:flex;flex-wrap:wrap;gap:10px;margin:20px 0}
.vw-filter-bar>*{flex:1 1 160px}.vw-list-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
</style>
<main class="vw">
<div class="vw-list-head"><div><h1>Vacanze</h1><p class="vw-muted">Tutte le vacanze, dalla prima idea a quelle già concluse.</p></div>
<?php if($canInsert): ?><a class="vw-btn vw-btn-primary" href="vacanze_modifica.php"><i class="bi bi-plus-lg"></i> Nuova vacanza</a><?php endif; ?></div>
<form method="get" class="vw-panel" aria-label="Cerca, filtra e ordina le vacanze">
<div class="vw-filter-bar">
 <div style="flex:2 1 240px"><label for="vacSearch" class="form-label">Ricerca</label><input id="vacSearch" name="q" class="vw-input w-100" value="<?= vacanzeH($search) ?>" placeholder="Titolo, destinazione, descrizione…"></div>
 <div><label for="vacStato" class="form-label">Stato</label><select id="vacStato" name="stato" class="vw-list-select w-100"><option value="">Tutti gli stati</option><?php foreach($stati as $s): ?><option value="<?= vacanzeH($s) ?>" <?= $s===$stato?'selected':'' ?>><?= vacanzeH(ucfirst($s)) ?></option><?php endforeach; ?></select></div>
 <div><label for="vacOrdine" class="form-label">Ordina per</label><select id="vacOrdine" name="ordine" class="vw-list-select w-100"><?php foreach($ordinamenti as $value=>$label): ?><option value="<?= vacanzeH($value) ?>" <?= $value===$ordine?'selected':'' ?>><?= vacanzeH($label) ?></option><?php endforeach; ?></select></div>
</div>
<details <?= ($notti!==''||$prezzoMin!==''||$prezzoMax!=='')?'open':'' ?>><summary class="mb-3" style="cursor:pointer">Filtri avanzati</summary>
<div class="vw-filter-bar"><div><label for="vacNotti" class="form-label">Notti</label><input id="vacNotti" class="vw-input w-100" type="number" min="0" name="notti" value="<?= vacanzeH($notti) ?>"></div>
<div><label for="vacMin" class="form-label">Budget minimo (€)</label><input id="vacMin" class="vw-input w-100" type="number" step="0.01" min="0" name="prezzo_min" value="<?= vacanzeH($prezzoMin) ?>"></div>
<div><label for="vacMax" class="form-label">Budget massimo (€)</label><input id="vacMax" class="vw-input w-100" type="number" step="0.01" min="0" name="prezzo_max" value="<?= vacanzeH($prezzoMax) ?>"></div></div>
</details>
<div class="vw-list-actions"><button type="submit" class="vw-btn vw-btn-primary"><i class="bi bi-search"></i> Applica</button><a href="vacanze_lista.php" class="vw-btn">Azzera filtri</a><span class="vw-muted ms-auto"><?= $count ?> <?= $count===1?'vacanza trovata':'vacanze trovate' ?></span></div>
</form>
<?php if(!$vacanze): ?><div class="vw-panel vw-empty">Nessuna vacanza corrisponde ai filtri. <a href="vacanze_lista.php">Mostra tutte</a></div><?php endif; ?>
<div class="vw-grid">
<?php foreach($vacanze as $v): $tripId=(int)$v['id_viaggio']; ?>
<article class="vw-trip">
 <div class="d-flex justify-content-between gap-2 align-items-start">
  <h2><a href="vacanze_workspace.php?id=<?= $tripId ?>"><?= vacanzeH($v['titolo']) ?></a></h2>
  <span class="vw-pill"><?= vacanzeH(ucfirst((string)$v['stato'])) ?></span>
 </div>
 <?php if($v['breve_descrizione']): ?><div class="vw-trip-meta"><?= vacanzeH($v['breve_descrizione']) ?></div><?php endif; ?>
 <div class="vw-trip-meta"><i class="bi bi-geo-alt"></i> <?= vacanzeH($v['luogo_nome'] ?: ($v['citta'] ?: 'Destinazione da definire')) ?></div>
 <div class="vw-trip-meta"><i class="bi bi-calendar3"></i> <?= vacanzeH($v['data_inizio'] ?: 'Date da definire') ?><?= $v['data_fine']?' · '.vacanzeH($v['data_fine']):'' ?><?= $v['notti']!==null?' · '.(int)$v['notti'].' notti':'' ?></div>
 <div class="vw-trip-bottom">
 <div><div class="vw-trip-meta"><?= (int)$v['num_alternative'] ?> <?= (int)$v['num_alternative']===1?'scenario':'scenari' ?> · Budget da</div><strong><?= $v['min_totale']!==null?vacanzeEuro($v['min_totale']):'Da definire' ?></strong></div>
 <div class="vw-card-actions"><a class="vw-btn" href="vacanze_workspace.php?id=<?= $tripId ?>">Apri</a><?php if($canUpdate): ?><a class="vw-btn" href="vacanze_modifica.php?id=<?= $tripId ?>" aria-label="Modifica <?= vacanzeH($v['titolo']) ?>" title="Modifica vacanza"><i class="bi bi-pencil"></i></a><?php endif; ?></div>
 </div>
</article>
<?php endforeach; ?>
</div>
</main>
<?php include 'includes/footer.php'; ?>
