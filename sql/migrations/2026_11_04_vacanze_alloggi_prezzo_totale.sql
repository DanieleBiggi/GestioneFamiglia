-- Estensione additiva per gli alloggi. Compatibile con MySQL 5.6.
-- Eseguire PRIMA di distribuire il codice PHP che usa costo_totale_eur.
-- NULL significa tariffa per notte (modalita' esistente).
-- Un valore, compreso 0.00, significa prezzo totale del soggiorno.
-- Non modifica gli importi esistenti: restano tutti nella modalita' per notte.
ALTER TABLE viaggi_alloggi
  ADD COLUMN costo_totale_eur DECIMAL(12,2) NULL DEFAULT NULL AFTER costo_notte_eur;

-- La vista di riepilogo generale v_totali_alternative usa questa vista di supporto;
-- MySQL 5.6 accetta il cambio senza subquery nella clausola FROM.
CREATE OR REPLACE VIEW v_vacanze_totali_alloggi AS
SELECT x.id_viaggio_alternativa,
       SUM(
         CASE
           WHEN e.costo_totale_eur IS NOT NULL THEN e.costo_totale_eur
           ELSE DATEDIFF(e.data_checkout,e.data_checkin)*COALESCE(e.costo_notte_eur,0)
         END
       ) AS totale
FROM viaggi_alloggi_alternative x
JOIN viaggi_alloggi e ON e.id_alloggio=x.id_alloggio
JOIN viaggi_alternative a ON a.id_viaggio_alternativa=x.id_viaggio_alternativa
  AND a.id_viaggio=e.id_viaggio
GROUP BY x.id_viaggio_alternativa;

-- Verifica: nessun totale deve cambiare per gli alloggi preesistenti.
-- Vedi i totali degli scenari, inclusi quelli con alloggi a prezzo totale.
SELECT id_viaggio,id_viaggio_alternativa,totale_alloggi,totale_viaggio
FROM v_totali_alternative ORDER BY id_viaggio,id_viaggio_alternativa;
