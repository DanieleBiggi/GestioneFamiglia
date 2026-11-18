<?php
require_once __DIR__ . '/../includes/budget_pocket_previsione.php';
function checkBudget(bool $ok,string $msg): void { if (!$ok) { fwrite(STDERR,"FAIL: ".$msg."\n"); exit(1); } }
$winter=[['tipologia'=>'uscita','tipologia_spesa'=>'una_tantum','importo'=>2135.68,
'da_13esima'=>1000,'da_14esima'=>0,'data_inizio'=>'2026-01-01','data_scadenza'=>'2027-01-01']];
$r=gf_pocket_previsione($winter,736.09,100,'2026-11-18');
checkBudget(abs($r['ultima']['saldo']+299.59)<0.01,'November first has passed: only December deposit before Jan 1');
checkBudget($r['ultima']['mesi']===1,'one future transfer before deadline');
checkBudget($r['tredicesima_futura']===1000.0,'December 21 included before Jan 1');
checkBudget(abs(gf_budget_importo_mensile($winter[0],'2026-11-18')-81.99)<0.01,'shared monthly budget calculation');
$rDec=gf_pocket_previsione($winter,736.09,100,'2026-12-22');
checkBudget($rDec['tredicesima_futura']===0.0,'do not add a past bonus to current balance');
$summer=[['tipologia'=>'uscita','tipologia_spesa'=>'una_tantum','importo'=>1000,
'da_13esima'=>0,'da_14esima'=>500,'data_inizio'=>'2026-07-01','data_scadenza'=>'2027-07-01']];
$rs=gf_pocket_previsione($summer,0,0,'2026-11-18');
checkBudget($rs['quattordicesima_futura']===500.0 && $rs['ultima']['saldo']===-500.0,'June 30 bonus planned');
$early=[['tipologia'=>'uscita','tipologia_spesa'=>'una_tantum','importo'=>200,
'da_13esima'=>200,'da_14esima'=>0,'data_inizio'=>'2026-01-01','data_scadenza'=>'2026-12-20']];
$re=gf_pocket_previsione($early,0,0,'2026-11-18');
checkBudget($re['tredicesima_futura']===0.0,'December 21 not available for December 20 expense');
$same=[['tipologia'=>'uscita','tipologia_spesa'=>'una_tantum','importo'=>100,
'da_13esima'=>0,'da_14esima'=>0,'data_inizio'=>'2026-01-01','data_scadenza'=>'2026-12-01']];
$rx=gf_pocket_previsione($same,0,100,'2026-11-18');
checkBudget($rx['ultima']['mesi']===0,'expense on the first precedes first-day deposit');
$multi=[['tipologia'=>'uscita','tipologia_spesa'=>'una_tantum','importo'=>300,'da_13esima'=>0,'da_14esima'=>0,'data_inizio'=>'2026-01-01','data_scadenza'=>'2026-12-15'],
['tipologia'=>'uscita','tipologia_spesa'=>'una_tantum','importo'=>200,'da_13esima'=>0,'da_14esima'=>0,'data_inizio'=>'2026-01-01','data_scadenza'=>'2027-02-15']];
$rm=gf_pocket_previsione($multi,0,100,'2026-11-18');
checkBudget($rm['peggiore']['data']==='2026-12-15','intermediate deadlines checked');
echo "Pocket budget forecasts OK\n";
