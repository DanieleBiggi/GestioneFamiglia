-- VERIFICA SOLA LETTURA: conversione Disney+ 2026, etichetta 164.
-- L'utente ha confermato che queste nove entrate manuali per 127,50 EUR
-- non sono incassi reali, ma compensazioni con Bocci.
-- Questo script NON modifica alcun dato. Eseguire su phpMyAdmin
-- per verificare altri collegamenti prima di preparare la migrazione.
SELECT
    be.id_entrata, be.data_operazione, be.descrizione_operazione,
    be.descrizione_extra, be.importo, be.mezzo, be.id_utente,
    be.id_caricamento, be.id_gruppo_transazione,
    e2o.id_e2o, e2o.id_etichetta, e2o.importo AS importo_etichetta,
    e2o.dividere_rimborsare, e2o.escludi_da_finanze_evento
FROM bilancio_entrate AS be
JOIN bilancio_etichette2operazioni AS e2o
 ON e2o.tabella_operazione='bilancio_entrate'
 AND e2o.id_tabella=be.id_entrata AND e2o.id_etichetta=164
WHERE be.id_entrata IN (2638,2637,2639,2640,2679,2680,2681,2694,2702)
ORDER BY be.data_operazione,be.id_entrata;

-- Deve risultare esattamente 9 righe, totale 127,50 EUR.
SELECT COUNT(*) AS numero, SUM(be.importo) AS totale
FROM bilancio_entrate AS be
JOIN bilancio_etichette2operazioni AS e2o
 ON e2o.tabella_operazione='bilancio_entrate'
 AND e2o.id_tabella=be.id_entrata AND e2o.id_etichetta=164
WHERE be.id_entrata IN (2638,2637,2639,2640,2679,2680,2681,2694,2702);

-- Se sono associate anche ad ALTRE etichette, la conversione
-- cambierebbe altri saldi: NON procedere senza riconciliazione.
SELECT eo.id_tabella AS id_entrata,eo.id_e2o,eo.id_etichetta,
       et.descrizione AS etichetta
FROM bilancio_etichette2operazioni eo
LEFT JOIN bilancio_etichette et ON et.id_etichetta=eo.id_etichetta
WHERE eo.tabella_operazione='bilancio_entrate'
  AND eo.id_tabella IN (2638,2637,2639,2640,2679,2680,2681,2694,2702)
  AND eo.id_etichetta<>164;

-- Verificare le eventuali quote/saldi già collegati alle nove righe;
-- una conversione non deve eliminarli senza ricostruirli.
SELECT eo.id_tabella AS id_entrata,uo.id_u2o,uo.id_utente,uo.importo_utente,
       uo.utente_pagante,uo.saldata,uo.data_saldo,uo.quote
FROM bilancio_etichette2operazioni eo
JOIN bilancio_utenti2operazioni_etichettate uo ON uo.id_e2o=eo.id_e2o
WHERE eo.tabella_operazione='bilancio_entrate'
  AND eo.id_tabella IN (2638,2637,2639,2640,2679,2680,2681,2694,2702)
ORDER BY eo.id_tabella,uo.id_u2o;
