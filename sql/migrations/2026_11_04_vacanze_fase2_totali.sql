-- Fase 2: eseguire insieme al rilascio del codice che usa le associazioni.
-- Ogni scenario compare anche senza tratte. Spese senza associazione escluse.
CREATE OR REPLACE VIEW v_totali_alternative AS
SELECT a.id_viaggio, a.id_viaggio_alternativa, a.breve_descrizione,
 COALESCE(t.totale,0) AS totale_trasporti,
 COALESCE(h.totale,0) AS totale_alloggi,
 COALESCE(p.totale,0) AS totale_pasti,
 COALESCE(c.totale,0) AS totale_altri_costi,
 COALESCE(t.totale,0)+COALESCE(h.totale,0)+COALESCE(p.totale,0)+COALESCE(c.totale,0) AS totale_viaggio
FROM viaggi_alternative a
LEFT JOIN (
 SELECT x.id_viaggio_alternativa, SUM(
   COALESCE(e.distanza_km,0)*COALESCE(e.consumo_litri_100km,0)/100*COALESCE(e.prezzo_carburante_eur_litro,0)
   +COALESCE(e.pedaggi_eur,0)+COALESCE(e.costo_traghetto_eur,0)+COALESCE(e.costo_volo_eur,0)
   +COALESCE(e.costo_noleggio_eur,0)+COALESCE(e.altri_costi_eur,0)) AS totale
 FROM viaggi_tratte_alternative x JOIN viaggi_tratte e ON e.id_tratta=x.id_tratta
 JOIN viaggi_alternative a2 ON a2.id_viaggio_alternativa=x.id_viaggio_alternativa AND a2.id_viaggio=e.id_viaggio
 GROUP BY x.id_viaggio_alternativa
) t ON t.id_viaggio_alternativa=a.id_viaggio_alternativa
LEFT JOIN (
 SELECT x.id_viaggio_alternativa, SUM(DATEDIFF(e.data_checkout,e.data_checkin)*COALESCE(e.costo_notte_eur,0)) AS totale
 FROM viaggi_alloggi_alternative x JOIN viaggi_alloggi e ON e.id_alloggio=x.id_alloggio
 JOIN viaggi_alternative a2 ON a2.id_viaggio_alternativa=x.id_viaggio_alternativa AND a2.id_viaggio=e.id_viaggio
 GROUP BY x.id_viaggio_alternativa
) h ON h.id_viaggio_alternativa=a.id_viaggio_alternativa
LEFT JOIN (
 SELECT x.id_viaggio_alternativa, SUM(COALESCE(e.costo_medio_eur,0)) AS totale
 FROM viaggi_pasti_alternative x JOIN viaggi_pasti e ON e.id_pasto=x.id_pasto
 JOIN viaggi_alternative a2 ON a2.id_viaggio_alternativa=x.id_viaggio_alternativa AND a2.id_viaggio=e.id_viaggio
 GROUP BY x.id_viaggio_alternativa
) p ON p.id_viaggio_alternativa=a.id_viaggio_alternativa
LEFT JOIN (
 SELECT x.id_viaggio_alternativa, SUM(COALESCE(e.importo_eur,0)) AS totale
 FROM viaggi_altri_costi_alternative x JOIN viaggi_altri_costi e ON e.id_costo=x.id_costo
 JOIN viaggi_alternative a2 ON a2.id_viaggio_alternativa=x.id_viaggio_alternativa AND a2.id_viaggio=e.id_viaggio
 GROUP BY x.id_viaggio_alternativa
) c ON c.id_viaggio_alternativa=a.id_viaggio_alternativa;
