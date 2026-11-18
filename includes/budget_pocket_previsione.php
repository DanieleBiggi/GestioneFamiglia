<?php
declare(strict_types=1);

/** Same monthly installment in annual and dashboard views. */
function gf_budget_importo_mensile(array $item, string $oggi): float {
    $inizio = (string)($item['data_inizio'] ?? '');
    $fine = (string)($item['data_scadenza'] ?? '');
    if (!$inizio || !$fine || $inizio >= $oggi || $fine <= $oggi) return 0.0;
    $start = new DateTimeImmutable(substr($inizio,0,7).'-01');
    $end = new DateTimeImmutable(substr($fine,0,7).'-01');
    $months = ((int)$end->format('Y')-(int)$start->format('Y'))*12 + (int)$end->format('n')-(int)$start->format('n');
    if ($months <= 0) return 0.0;
    $residual = max(0.0, (float)($item['importo'] ?? 0)-(float)($item['da_13esima'] ?? 0)-(float)($item['da_14esima'] ?? 0));
    return round($residual / $months,2);
}

/** Deposits occur on day 1, Dec 21 and Jun 30; same-day bills precede deposits. */
function gf_pocket_previsione(array $items, float $saldo, float $mensile, string $oggi): array {
    $saldo=max(0.0,$saldo); $mensile=max(0.0,$mensile); $validi=[];
    foreach ($items as $item) {
        if (($item['tipologia'] ?? 'uscita') !== 'uscita' ||
            ($item['tipologia_spesa'] ?? 'una_tantum') === 'mensile' ||
            empty($item['data_scadenza']) || $item['data_scadenza'] < $oggi) continue;
        foreach (['importo','da_13esima','da_14esima'] as $key) $item[$key]=max(0.0,(float)($item[$key] ?? 0));
        $validi[]=$item;
    }
    usort($validi,static function ($a,$b) { return strcmp($a['data_scadenza'],$b['data_scadenza']); });
    $bonusDate=static function(string $monthDay) use ($oggi): string {
        $year=(int)substr($oggi,0,4); $date=sprintf('%04d-%s',$year,$monthDay);
        return $date>$oggi ? $date : sprintf('%04d-%s',$year+1,$monthDay);
    };
    $bonus13Date=$bonusDate('12-21'); $bonus14Date=$bonusDate('06-30');
    $monthCount=static function(string $deadline) use ($oggi): int {
        $cursor=new DateTimeImmutable(substr($oggi,0,7).'-01');
        if ($cursor->format('Y-m-d') <= $oggi) $cursor=$cursor->modify('+1 month');
        $n=0;
        while ($cursor->format('Y-m-d') < $deadline) { $n++; $cursor=$cursor->modify('+1 month'); }
        return $n;
    };
    $bonuses=static function(string $depositDate,string $deadline,string $key) use ($validi): float {
        if ($depositDate >= $deadline) return 0.0;
        $sum=0.0;
        foreach ($validi as $item) {
            if (($item['data_inizio'] ?? '1900-01-01') <= $depositDate &&
                $depositDate < $item['data_scadenza']) $sum += $item[$key];
        }
        return round($sum,2);
    };
    $target=0.0; $quota13=0.0; $quota14=0.0;
    foreach ($validi as $v) { $target+=$v['importo']; $quota13+=$v['da_13esima']; $quota14+=$v['da_14esima']; }
    $peggiore=null; $ultima=null; $speseEntro=0.0; $consigliato=0.0; $rischio=false;
    foreach ($validi as $item) {
        $deadline=(string)$item['data_scadenza']; $speseEntro+=$item['importo'];
        $months=$monthCount($deadline);
        $bonus13=$bonuses($bonus13Date,$deadline,'da_13esima');
        $bonus14=$bonuses($bonus14Date,$deadline,'da_14esima');
        $gap=max(0.0,$speseEntro-$saldo-$bonus13-$bonus14);
        if ($months===0 && $gap>0.005) $rischio=true;
        if ($months>0) $consigliato=max($consigliato,$gap/$months);
        $restante=round($saldo+$mensile*$months+$bonus13+$bonus14-$speseEntro,2);
        if ($peggiore===null || $restante<$peggiore['saldo']) $peggiore=['data'=>$deadline,'saldo'=>$restante];
        $ultima=['data'=>$deadline,'saldo'=>$restante,'obiettivo'=>round($speseEntro,2),'mesi'=>$months];
    }
    $end=$ultima['data'] ?? $oggi;
    return [
        'obiettivo'=>round($target,2),'quota_13'=>round($quota13,2),'quota_14'=>round($quota14,2),
        'tredicesima_futura'=>$bonuses($bonus13Date,$end,'da_13esima'),
        'quattordicesima_futura'=>$bonuses($bonus14Date,$end,'da_14esima'),
        'consigliato'=>round($consigliato,2),'scoperto_immediato'=>$rischio,
        'peggiore'=>$peggiore,'ultima'=>$ultima
    ];
}
