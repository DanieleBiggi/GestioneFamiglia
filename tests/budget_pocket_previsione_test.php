<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/budget_pocket_previsione.php';
function checkBudget(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
}
$winter = [[
    'tipologia'=>'uscita', 'tipologia_spesa'=>'una_tantum',
    'importo'=>2135.68, 'da_13esima'=>1000.00, 'da_14esima'=>0,
    'data_inizio'=>'2026-09-01', 'data_scadenza'=>'2027-01-30'
]];
$r = gf_pocket_previsione($winter,1000,100,'2026-11-18');
checkBudget(abs($r['consigliato']-67.84)<0.01,'13th lowers suggested monthly savings');
checkBudget(abs($r['ultima']['saldo']-64.32)<0.01,'December 13th included as future deposit');
checkBudget(abs($r['tredicesima_futura']-1000)<0.01,'13th retained as distinct planned amount');
$r2 = gf_pocket_previsione($winter,1000,100,'2026-12-18');
checkBudget($r2['tredicesima_futura']===0.0,'Do not count a past December bonus again');
$urgent = array_merge($winter,[[
    'tipologia'=>'uscita', 'tipologia_spesa'=>'una_tantum',
    'importo'=>1200, 'da_13esima'=>0, 'da_14esima'=>0,
    'data_inizio'=>'2026-11-01', 'data_scadenza'=>'2026-11-25'
]]);
$r3 = gf_pocket_previsione($urgent,1000,100,'2026-11-18');
checkBudget($r3['scoperto_immediato'],'Future December 13th must not pay an earlier November expense');
$with14 = [[
    'tipologia'=>'uscita', 'tipologia_spesa'=>'una_tantum',
    'importo'=>1000, 'da_13esima'=>0, 'da_14esima'=>500,
    'data_inizio'=>'2026-10-01', 'data_scadenza'=>'2027-06-30'
]];
$r4 = gf_pocket_previsione($with14,0,0,'2026-11-18');
checkBudget($r4['quota_14']===500.0 && $r4['ultima']['saldo']===-1000.0,'14th remains visible, not treated as confirmed deposit');
echo "Pocket budget forecasts OK\n";
