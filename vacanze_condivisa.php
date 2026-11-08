<?php
// Public read-only vacation preview. Never include the authenticated header.
require_once 'includes/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
$token = (string)($_GET['token'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
    http_response_code(404); exit('Link non valido');
}
$hash = hash('sha256', $token);
$stmt = $conn->prepare('SELECT c.id_condivisione,c.id_viaggio,v.titolo,c.scade_il FROM viaggi_condivisioni c JOIN viaggi v ON v.id_viaggio=c.id_viaggio WHERE c.token_sha256=? AND c.revocato_il IS NULL AND c.scade_il>UTC_TIMESTAMP() LIMIT 1');
$stmt->bind_param('s', $hash);
$stmt->execute();
$share = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$share) { http_response_code(404); exit('Link scaduto o non valido'); }
$shareId = (int)$share['id_condivisione'];
$altStmt = $conn->prepare('SELECT a.id_viaggio_alternativa,a.breve_descrizione FROM viaggi_condivisioni_alternative ca JOIN viaggi_alternative a ON a.id_viaggio_alternativa=ca.id_viaggio_alternativa WHERE ca.id_condivisione=? AND a.id_viaggio=? ORDER BY a.id_viaggio_alternativa');
$altStmt->bind_param('ii', $shareId, $share['id_viaggio']);
$altStmt->execute();
$alternatives = $altStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$altStmt->close();
$comments = $conn->prepare("SELECT c.testo,c.creato_il,u.username,a.breve_descrizione FROM viaggi_commenti c JOIN utenti u ON u.id=c.id_utente LEFT JOIN viaggi_alternative a ON a.id_viaggio_alternativa=c.id_viaggio_alternativa AND a.id_viaggio=c.id_viaggio WHERE c.id_viaggio=? AND c.visibilita='pubblico' AND (c.id_viaggio_alternativa IS NULL OR EXISTS (SELECT 1 FROM viaggi_condivisioni_alternative ca WHERE ca.id_condivisione=? AND ca.id_viaggio_alternativa=c.id_viaggio_alternativa)) ORDER BY c.creato_il DESC");
$comments->bind_param('ii', $share['id_viaggio'], $shareId);
$comments->execute();
$publicComments = $comments->get_result()->fetch_all(MYSQLI_ASSOC);
$comments->close();
// La pagina pubblica non ha sessione: carica il tema Lilla gia' salvato nel portale.
$themeStmt=$conn->prepare("SELECT background_color,text_color,primary_color,secondary_color FROM temi WHERE LOWER(nome) LIKE '%lilla%' ORDER BY CASE WHEN LOWER(TRIM(nome))='lilla' THEN 0 ELSE 1 END,id LIMIT 1");
$themeStmt->execute();
$shareTheme=$themeStmt->get_result()->fetch_assoc();
$themeStmt->close();
if(!$shareTheme){
    error_log('Tema Lilla non trovato nella tabella temi');
    http_response_code(503);
    exit('Tema Lilla non disponibile. Verificare la configurazione dei temi.');
}
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vacanza condivisa</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* I quattro colori vengono dal tema Lilla presente nel database, non da una seconda palette. */
:root {
  color-scheme:light;
  --share-bg:<?= h($shareTheme['background_color']) ?>;
  --share-fg:<?= h($shareTheme['text_color']) ?>;
  --share-primary:<?= h($shareTheme['primary_color']) ?>;
  --share-secondary:<?= h($shareTheme['secondary_color']) ?>;
}
body.vacanze-share { background:var(--share-bg); color:var(--share-fg); min-height:100vh; }
.vacanze-share main { max-width:940px; }
.vacanze-share h1,.vacanze-share h2,.vacanze-share h3 { color:var(--share-fg); }
.vacanze-share h1 { font-weight:750; }
.vacanze-share h2 { font-weight:700; }
.vacanze-share .share-card {
 background:#fff; color:#29243a;
 border:1px solid var(--share-secondary)!important;
 border-radius:16px!important;
 box-shadow:0 4px 18px rgba(40,25,65,.08);
}
.vacanze-share .share-card h3,.vacanze-share .share-card h4 { color:#29243a; }
.vacanze-share .share-row { background:#fff; color:#29243a; border-color:var(--share-secondary); }
.vacanze-share .share-total { margin-top:14px; border-radius:10px; padding:10px 13px; background:var(--share-primary); color:var(--share-fg); }
.vacanze-share .text-secondary { color:var(--share-fg)!important; opacity:.75; }
.vacanze-share .share-card .text-secondary { color:#585166!important; opacity:1; }
.vacanze-share .share-heading { border-bottom:1px solid var(--share-secondary); padding-bottom:12px; }
@media(max-width:576px){.vacanze-share main{padding-inline:16px}.vacanze-share .share-card{padding:15px!important}}
</style></head>
<body class="vacanze-share"><main class="container py-4">
<h1 class="h3 share-heading"><?= h($share['titolo']) ?></h1>
<p class="text-secondary">Consultazione in sola lettura. Link valido fino al <?= h($share['scade_il']) ?> (UTC).</p>
<h2 class="h5 mt-4">Scenari condivisi</h2>
<?php if (!$alternatives): ?><p>Nessuno scenario disponibile.</p><?php endif; ?>
<?php foreach ($alternatives as $a): ?>
<section class="share-card p-3 mb-3"><h3 class="h6"><?= h($a['breve_descrizione']) ?></h3>
<?php
$categories = [
 ['Viaggi e trasporti','viaggi_tratte','viaggi_tratte_alternative','id_tratta','descrizione','COALESCE(distanza_km,0)*COALESCE(consumo_litri_100km,0)/100*COALESCE(prezzo_carburante_eur_litro,0)+COALESCE(pedaggi_eur,0)+COALESCE(costo_traghetto_eur,0)+COALESCE(costo_volo_eur,0)+COALESCE(costo_noleggio_eur,0)+COALESCE(altri_costi_eur,0)'],
 ['Alloggi','viaggi_alloggi','viaggi_alloggi_alternative','id_alloggio','nome_alloggio','COALESCE(costo_totale_eur,DATEDIFF(data_checkout,data_checkin)*COALESCE(costo_notte_eur,0))'],
 ['Pasti','viaggi_pasti','viaggi_pasti_alternative','id_pasto','nome_locale','COALESCE(costo_medio_eur,0)'],
 ['Altri costi','viaggi_altri_costi','viaggi_altri_costi_alternative','id_costo','descrizione','COALESCE(importo_eur,0)']
];
$grandTotal = 0;
foreach ($categories as [$label,$table,$link,$key,$description,$formula]) {
 $q = $conn->prepare("SELECT e.`$description` AS descrizione,($formula) AS importo FROM `$table` e JOIN `$link` l ON l.`$key`=e.`$key` WHERE e.id_viaggio=? AND l.id_viaggio_alternativa=? ORDER BY e.`$key`");
 $q->bind_param('ii', $share['id_viaggio'], $a['id_viaggio_alternativa']);
 $q->execute(); $items=$q->get_result()->fetch_all(MYSQLI_ASSOC); $q->close();
 if (!$items) continue;
 echo '<h4 class="h6 mt-3">'.h($label).'</h4><ul class="list-group">';
 foreach ($items as $item) {
  $amount=(float)$item['importo']; $grandTotal+=$amount;
  echo '<li class="list-group-item share-row d-flex justify-content-between gap-3"><span>'.h($item['descrizione'] ?: $label).'</span><span>€ '.number_format($amount,2,',','.').'</span></li>';
 }
 echo '</ul>';
}
?>
<div class="share-total fw-bold">Totale scenario: € <?= number_format($grandTotal,2,',','.') ?></div>
</section>
<?php endforeach; ?>
<h2 class="h5 mt-4">Note e commenti condivisi</h2>
<?php if (!$publicComments): ?><p class="text-secondary">Nessun commento pubblico.</p><?php endif; ?>
<?php foreach ($publicComments as $c): ?>
<div class="share-card p-3 mb-2"><div class="small text-secondary"><?= h($c['username']) ?> · <?= h($c['creato_il']) ?><?= $c['breve_descrizione'] !== null ? ' · '.h($c['breve_descrizione']) : '' ?></div>
<div><?= nl2br(h($c['testo'])) ?></div></div>
<?php endforeach; ?>
</main></body></html>
