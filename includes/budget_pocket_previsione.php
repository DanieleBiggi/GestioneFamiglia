<?php
declare(strict_types=1);

/**
 * Forecast an earmarked Pocket against actual budget deadlines.
 *
 * Annual bonuses are NOT ordinary monthly savings.
 * The 13th is included only if the upcoming December falls before the
 * relevant expense deadline. 14th timing is intentionally not guessed.
 *
 * @param array $items Each: importo, data_inizio, data_scadenza, da_13esima, da_14esima.
 */
function gf_pocket_previsione(array $items, float $saldo, float $mensile, string $oggi): array
{
    $saldo = max(0.0, $saldo);
    $mensile = max(0.0, $mensile);
    $validi = [];
    $quota13 = 0.0;
    $quota14 = 0.0;
    $obiettivo = 0.0;
    foreach ($items as $item) {
        if (($item['tipologia'] ?? 'uscita') !== 'uscita' ||
            ($item['tipologia_spesa'] ?? 'una_tantum') === 'mensile' ||
            empty($item['data_scadenza']) || $item['data_scadenza'] < $oggi) {
            continue;
        }
        $item['importo'] = max(0.0, (float)($item['importo'] ?? 0));
        $item['da_13esima'] = max(0.0, (float)($item['da_13esima'] ?? 0));
        $item['da_14esima'] = max(0.0, (float)($item['da_14esima'] ?? 0));
        $validi[] = $item;
        $obiettivo += $item['importo'];
        $quota13 += $item['da_13esima'];
        $quota14 += $item['da_14esima'];
    }
    usort($validi, static function (array $a, array $b): int {
        return strcmp((string)$a['data_scadenza'], (string)$b['data_scadenza']);
    });
    $nextDec = substr($oggi,0,4) . '-12-01';
    if ($oggi >= $nextDec) $nextDec = ((int)substr($oggi,0,4) + 1) . '-12-01';
    $monthCount = static function (string $until) use ($oggi): int {
        $y1 = (int)substr($oggi,0,4); $m1 = (int)substr($oggi,5,2);
        $y2 = (int)substr($until,0,4); $m2 = (int)substr($until,5,2);
        return max(0, ($y2 - $y1) * 12 + $m2 - $m1);
    };
    $mensileConsigliato = 0.0;
    $speseEntro = 0.0;
    $rischioSenzaMese = false;
    $peggiore = null;
    $ultima = null;
    $bonus13Totale = 0.0;
    foreach ($validi as $scadenzaItem) {
        $data = (string)$scadenzaItem['data_scadenza'];
        $speseEntro += (float)$scadenzaItem['importo'];
        $bonus13Entro = 0.0;
        foreach ($validi as $item) {
            // Only future bonus amounts explicitly assigned to this expense.
            if ($nextDec > $oggi && $nextDec <= $data &&
                $nextDec <= $item['data_scadenza'] &&
                ($item['data_inizio'] ?? '1900-01-01') <= $nextDec) {
                $bonus13Entro += $item['da_13esima'];
            }
        }
        $mesi = $monthCount($data);
        $gapForMonthly = max(0.0, $speseEntro - $saldo - $bonus13Entro);
        if ($mesi === 0 && $gapForMonthly > 0.005) $rischioSenzaMese = true;
        if ($mesi > 0) $mensileConsigliato = max($mensileConsigliato, $gapForMonthly / $mesi);
        // Forecast is net of expenses that fall due; it is not just the Pocket's gross balance.
        $restante = $saldo + $mensile * $mesi + $bonus13Entro - $speseEntro;
        if ($peggiore === null || $restante < $peggiore['saldo']) {
            $peggiore = ['data'=>$data,'saldo'=>round($restante,2)];
        }
        $ultima = ['data'=>$data,'saldo'=>round($restante,2),'obiettivo'=>$speseEntro,'mesi'=>$mesi];
        $bonus13Totale = $bonus13Entro;
    }
    return [
        'obiettivo'=>round($obiettivo,2),
        'quota_13'=>round($quota13,2),
        'quota_14'=>round($quota14,2),
        'tredicesima_futura'=>round($bonus13Totale,2),
        'consigliato'=>round($mensileConsigliato,2),
        'scoperto_immediato'=>$rischioSenzaMese,
        'peggiore'=>$peggiore,
        'ultima'=>$ultima
    ];
}
