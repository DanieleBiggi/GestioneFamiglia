-- Verifica di pre-rilascio, SOLA LETTURA.
-- Valida esclusivamente PRIMA dell'attivazione della nuova gestione multi-scenario,
-- quando ogni collegamento nuovo deve riprodurre esattamente quello legacy.
-- Tutti i valori di anomalie devono risultare 0.

SELECT 'tratte: legacy senza link' AS controllo, COUNT(*) AS anomalie
FROM viaggi_tratte e
WHERE e.id_viaggio_alternativa IS NOT NULL
AND NOT EXISTS (
 SELECT 1 FROM viaggi_tratte_alternative l
 WHERE l.id_tratta=e.id_tratta AND l.id_viaggio_alternativa=e.id_viaggio_alternativa
)
UNION ALL
SELECT 'tratte: link non legacy', COUNT(*)
FROM viaggi_tratte_alternative l JOIN viaggi_tratte e ON e.id_tratta=l.id_tratta
WHERE e.id_viaggio_alternativa IS NULL OR e.id_viaggio_alternativa<>l.id_viaggio_alternativa
UNION ALL
SELECT 'alloggi: legacy senza link', COUNT(*)
FROM viaggi_alloggi e
WHERE e.id_viaggio_alternativa IS NOT NULL
AND NOT EXISTS (
 SELECT 1 FROM viaggi_alloggi_alternative l
 WHERE l.id_alloggio=e.id_alloggio AND l.id_viaggio_alternativa=e.id_viaggio_alternativa
)
UNION ALL
SELECT 'alloggi: link non legacy', COUNT(*)
FROM viaggi_alloggi_alternative l JOIN viaggi_alloggi e ON e.id_alloggio=l.id_alloggio
WHERE e.id_viaggio_alternativa IS NULL OR e.id_viaggio_alternativa<>l.id_viaggio_alternativa
UNION ALL
SELECT 'pasti: legacy senza link', COUNT(*)
FROM viaggi_pasti e
WHERE e.id_viaggio_alternativa IS NOT NULL
AND NOT EXISTS (
 SELECT 1 FROM viaggi_pasti_alternative l
 WHERE l.id_pasto=e.id_pasto AND l.id_viaggio_alternativa=e.id_viaggio_alternativa
)
UNION ALL
SELECT 'pasti: link non legacy', COUNT(*)
FROM viaggi_pasti_alternative l JOIN viaggi_pasti e ON e.id_pasto=l.id_pasto
WHERE e.id_viaggio_alternativa IS NULL OR e.id_viaggio_alternativa<>l.id_viaggio_alternativa
UNION ALL
SELECT 'altri costi: legacy senza link', COUNT(*)
FROM viaggi_altri_costi e
WHERE e.id_viaggio_alternativa IS NOT NULL
AND NOT EXISTS (
 SELECT 1 FROM viaggi_altri_costi_alternative l
 WHERE l.id_costo=e.id_costo AND l.id_viaggio_alternativa=e.id_viaggio_alternativa
)
UNION ALL
SELECT 'altri costi: link non legacy', COUNT(*)
FROM viaggi_altri_costi_alternative l JOIN viaggi_altri_costi e ON e.id_costo=l.id_costo
WHERE e.id_viaggio_alternativa IS NULL OR e.id_viaggio_alternativa<>l.id_viaggio_alternativa;

-- Spese rimaste senza scenario: per la nuova logica è previsto e non è un errore.
SELECT 'tratte' AS categoria, COUNT(*) AS non_assegnate
FROM viaggi_tratte e WHERE NOT EXISTS (
 SELECT 1 FROM viaggi_tratte_alternative l WHERE l.id_tratta=e.id_tratta)
UNION ALL SELECT 'alloggi', COUNT(*) FROM viaggi_alloggi e WHERE NOT EXISTS (
 SELECT 1 FROM viaggi_alloggi_alternative l WHERE l.id_alloggio=e.id_alloggio)
UNION ALL SELECT 'pasti', COUNT(*) FROM viaggi_pasti e WHERE NOT EXISTS (
 SELECT 1 FROM viaggi_pasti_alternative l WHERE l.id_pasto=e.id_pasto)
UNION ALL SELECT 'altri costi', COUNT(*) FROM viaggi_altri_costi e WHERE NOT EXISTS (
 SELECT 1 FROM viaggi_altri_costi_alternative l WHERE l.id_costo=e.id_costo);

-- Confronto importi per i soli scenari presenti nella vecchia vista.
-- Dovrebbe restituire 0 righe quando non sono ancora state create associazioni multiple.
-- Le nuove alternative senza tratte NON compaiono nella vista legacy e sono legittime.
SELECT v.id_viaggio, v.id_viaggio_alternativa, v.breve_descrizione,
       v.totale_viaggio AS vecchio_totale,
       COALESCE(t.totale,0)+COALESCE(a.totale,0)+COALESCE(p.totale,0)+COALESCE(c.totale,0) AS nuovo_totale
FROM v_totali_alternative v
LEFT JOIN (
 SELECT l.id_viaggio_alternativa, SUM(
 COALESCE(e.distanza_km,0)*COALESCE(e.consumo_litri_100km,0)/100*COALESCE(e.prezzo_carburante_eur_litro,0)
 +COALESCE(e.pedaggi_eur,0)+COALESCE(e.costo_traghetto_eur,0)
 +COALESCE(e.costo_volo_eur,0)+COALESCE(e.costo_noleggio_eur,0)+COALESCE(e.altri_costi_eur,0)) AS totale
 FROM viaggi_tratte_alternative l JOIN viaggi_tratte e ON e.id_tratta=l.id_tratta
 JOIN viaggi_alternative x ON x.id_viaggio_alternativa=l.id_viaggio_alternativa AND x.id_viaggio=e.id_viaggio
 GROUP BY l.id_viaggio_alternativa
) t ON t.id_viaggio_alternativa=v.id_viaggio_alternativa
LEFT JOIN (
 SELECT l.id_viaggio_alternativa, SUM(DATEDIFF(e.data_checkout,e.data_checkin)*COALESCE(e.costo_notte_eur,0)) AS totale
 FROM viaggi_alloggi_alternative l JOIN viaggi_alloggi e ON e.id_alloggio=l.id_alloggio
 JOIN viaggi_alternative x ON x.id_viaggio_alternativa=l.id_viaggio_alternativa AND x.id_viaggio=e.id_viaggio
 GROUP BY l.id_viaggio_alternativa
) a ON a.id_viaggio_alternativa=v.id_viaggio_alternativa
LEFT JOIN (
 SELECT l.id_viaggio_alternativa, SUM(COALESCE(e.costo_medio_eur,0)) AS totale
 FROM viaggi_pasti_alternative l JOIN viaggi_pasti e ON e.id_pasto=l.id_pasto
 JOIN viaggi_alternative x ON x.id_viaggio_alternativa=l.id_viaggio_alternativa AND x.id_viaggio=e.id_viaggio
 GROUP BY l.id_viaggio_alternativa
) p ON p.id_viaggio_alternativa=v.id_viaggio_alternativa
LEFT JOIN (
 SELECT l.id_viaggio_alternativa, SUM(COALESCE(e.importo_eur,0)) AS totale
 FROM viaggi_altri_costi_alternative l JOIN viaggi_altri_costi e ON e.id_costo=l.id_costo
 JOIN viaggi_alternative x ON x.id_viaggio_alternativa=l.id_viaggio_alternativa AND x.id_viaggio=e.id_viaggio
 GROUP BY l.id_viaggio_alternativa
) c ON c.id_viaggio_alternativa=v.id_viaggio_alternativa
WHERE ABS(COALESCE(v.totale_viaggio,0) -
 (COALESCE(t.totale,0)+COALESCE(a.totale,0)+COALESCE(p.totale,0)+COALESCE(c.totale,0))) > 0.02;
