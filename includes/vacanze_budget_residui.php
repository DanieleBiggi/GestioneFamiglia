<?php
/**
 * Calcola il costo ancora da pagare dello scenario confermato.
 * Le associazioni multi-scenario vengono considerate una sola volta per scenario.
 * Usato dalle viste Budget senza modificare il saldo o le righe contabili.
 */
function vacanzeBudgetResidui(mysqli $conn): array {
    $sql = "SELECT p.id_viaggio,p.id_salvadanaio,v.titolo,s.nome_salvadanaio,
                   t.totale_viaggio,p.obiettivo_salvadanaio,
                   COALESCE(trasporti.pagato,0)+COALESCE(alloggi.pagato,0)
                   +COALESCE(pasti.pagato,0)+COALESCE(altri.pagato,0) AS totale_pagato
            FROM viaggi_pianificazione p
            JOIN viaggi v ON v.id_viaggio=p.id_viaggio
            JOIN salvadanai s ON s.id_salvadanaio=p.id_salvadanaio
            JOIN v_totali_alternative t ON t.id_viaggio_alternativa=p.id_alternativa_confermata
              AND t.id_viaggio=p.id_viaggio
            LEFT JOIN (
              SELECT x.id_viaggio_alternativa,
              SUM(CASE WHEN e.pagato=1 THEN
                  COALESCE(e.distanza_km,0)*COALESCE(e.consumo_litri_100km,0)/100*COALESCE(e.prezzo_carburante_eur_litro,0)
                  +COALESCE(e.pedaggi_eur,0)+COALESCE(e.costo_traghetto_eur,0)
                  +COALESCE(e.costo_volo_eur,0)+COALESCE(e.costo_noleggio_eur,0)
                  +COALESCE(e.altri_costi_eur,0) ELSE 0 END) AS pagato
              FROM viaggi_tratte_alternative x JOIN viaggi_tratte e ON e.id_tratta=x.id_tratta
              JOIN viaggi_alternative a ON a.id_viaggio_alternativa=x.id_viaggio_alternativa AND a.id_viaggio=e.id_viaggio
              GROUP BY x.id_viaggio_alternativa
            ) trasporti ON trasporti.id_viaggio_alternativa=t.id_viaggio_alternativa
            LEFT JOIN (
              SELECT x.id_viaggio_alternativa,
              SUM(CASE WHEN e.pagato=1 THEN
                COALESCE(e.costo_totale_eur,DATEDIFF(e.data_checkout,e.data_checkin)*COALESCE(e.costo_notte_eur,0))
                ELSE 0 END) AS pagato
              FROM viaggi_alloggi_alternative x JOIN viaggi_alloggi e ON e.id_alloggio=x.id_alloggio
              JOIN viaggi_alternative a ON a.id_viaggio_alternativa=x.id_viaggio_alternativa AND a.id_viaggio=e.id_viaggio
              GROUP BY x.id_viaggio_alternativa
            ) alloggi ON alloggi.id_viaggio_alternativa=t.id_viaggio_alternativa
            LEFT JOIN (
              SELECT x.id_viaggio_alternativa,
              SUM(CASE WHEN e.pagato=1 THEN COALESCE(e.costo_medio_eur,0) ELSE 0 END) AS pagato
              FROM viaggi_pasti_alternative x JOIN viaggi_pasti e ON e.id_pasto=x.id_pasto
              JOIN viaggi_alternative a ON a.id_viaggio_alternativa=x.id_viaggio_alternativa AND a.id_viaggio=e.id_viaggio
              GROUP BY x.id_viaggio_alternativa
            ) pasti ON pasti.id_viaggio_alternativa=t.id_viaggio_alternativa
            LEFT JOIN (
              SELECT x.id_viaggio_alternativa,
              SUM(CASE WHEN e.pagato=1 THEN COALESCE(e.importo_eur,0) ELSE 0 END) AS pagato
              FROM viaggi_altri_costi_alternative x JOIN viaggi_altri_costi e ON e.id_costo=x.id_costo
              JOIN viaggi_alternative a ON a.id_viaggio_alternativa=x.id_viaggio_alternativa AND a.id_viaggio=e.id_viaggio
              GROUP BY x.id_viaggio_alternativa
            ) altri ON altri.id_viaggio_alternativa=t.id_viaggio_alternativa
            WHERE p.id_salvadanaio IS NOT NULL AND p.id_alternativa_confermata IS NOT NULL";
    $result=$conn->query($sql);
    $out=[];
    while($row=$result->fetch_assoc()){
        $total=max(0,(float)$row['totale_viaggio']);
        $paid=min($total,max(0,(float)$row['totale_pagato']));
        $row['importo_residuo']=round(max(0,$total-$paid),2);
        $row['totale_pagato']=$paid;
        $out[(int)$row['id_salvadanaio']]=$row;
    }
    return $out;
}
