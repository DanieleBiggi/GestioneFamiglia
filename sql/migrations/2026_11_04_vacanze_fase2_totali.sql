-- Fase 2 compatibile con MySQL 5.6 (Aruba).
-- IMPORTANTE: eseguire insieme al rilascio del codice PHP della PR.
-- La vista originale rimane intatta finché non viene eseguito l'ultimo CREATE OR REPLACE.
-- Le viste di supporto non modificano dati e non hanno sottoquery nel FROM.
-- Non eliminare le viste v_vacanze_totali_*: la vista finale ne dipende.

CREATE OR REPLACE VIEW v_vacanze_totali_trasporti AS
SELECT x.id_viaggio_alternativa,
       SUM(
         COALESCE(e.distanza_km,0)*COALESCE(e.consumo_litri_100km,0)/100*COALESCE(e.prezzo_carburante_eur_litro,0)
         + COALESCE(e.pedaggi_eur,0)
         + COALESCE(e.costo_traghetto_eur,0)
         + COALESCE(e.costo_volo_eur,0)
         + COALESCE(e.costo_noleggio_eur,0)
         + COALESCE(e.altri_costi_eur,0)
       ) AS totale
FROM viaggi_tratte_alternative x
JOIN viaggi_tratte e ON e.id_tratta=x.id_tratta
JOIN viaggi_alternative a ON a.id_viaggio_alternativa=x.id_viaggio_alternativa
  AND a.id_viaggio=e.id_viaggio
GROUP BY x.id_viaggio_alternativa;

CREATE OR REPLACE VIEW v_vacanze_totali_alloggi AS
SELECT x.id_viaggio_alternativa,
       SUM(DATEDIFF(e.data_checkout,e.data_checkin)*COALESCE(e.costo_notte_eur,0)) AS totale
FROM viaggi_alloggi_alternative x
JOIN viaggi_alloggi e ON e.id_alloggio=x.id_alloggio
JOIN viaggi_alternative a ON a.id_viaggio_alternativa=x.id_viaggio_alternativa
  AND a.id_viaggio=e.id_viaggio
GROUP BY x.id_viaggio_alternativa;

CREATE OR REPLACE VIEW v_vacanze_totali_pasti AS
SELECT x.id_viaggio_alternativa,
       SUM(COALESCE(e.costo_medio_eur,0)) AS totale
FROM viaggi_pasti_alternative x
JOIN viaggi_pasti e ON e.id_pasto=x.id_pasto
JOIN viaggi_alternative a ON a.id_viaggio_alternativa=x.id_viaggio_alternativa
  AND a.id_viaggio=e.id_viaggio
GROUP BY x.id_viaggio_alternativa;

CREATE OR REPLACE VIEW v_vacanze_totali_altri_costi AS
SELECT x.id_viaggio_alternativa,
       SUM(COALESCE(e.importo_eur,0)) AS totale
FROM viaggi_altri_costi_alternative x
JOIN viaggi_altri_costi e ON e.id_costo=x.id_costo
JOIN viaggi_alternative a ON a.id_viaggio_alternativa=x.id_viaggio_alternativa
  AND a.id_viaggio=e.id_viaggio
GROUP BY x.id_viaggio_alternativa;

-- Compatibile con MySQL 5.6: JOIN tra viste, nessuna sottoquery nel FROM.
-- Preserva esattamente gli 8 nomi delle colonne usate dal PHP.
-- Include anche alternative senza tratte e ignora le spese non assegnate.
CREATE OR REPLACE VIEW v_totali_alternative AS
SELECT a.id_viaggio,
       a.id_viaggio_alternativa,
       a.breve_descrizione,
       COALESCE(t.totale,0) AS totale_trasporti,
       COALESCE(h.totale,0) AS totale_alloggi,
       COALESCE(p.totale,0) AS totale_pasti,
       COALESCE(c.totale,0) AS totale_altri_costi,
       COALESCE(t.totale,0)+COALESCE(h.totale,0)+COALESCE(p.totale,0)+COALESCE(c.totale,0) AS totale_viaggio
FROM viaggi_alternative a
LEFT JOIN v_vacanze_totali_trasporti t ON t.id_viaggio_alternativa=a.id_viaggio_alternativa
LEFT JOIN v_vacanze_totali_alloggi h ON h.id_viaggio_alternativa=a.id_viaggio_alternativa
LEFT JOIN v_vacanze_totali_pasti p ON p.id_viaggio_alternativa=a.id_viaggio_alternativa
LEFT JOIN v_vacanze_totali_altri_costi c ON c.id_viaggio_alternativa=a.id_viaggio_alternativa;
