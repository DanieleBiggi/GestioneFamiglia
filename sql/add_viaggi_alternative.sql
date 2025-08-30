-- Aggiunta tabella viaggi_alternative e collegamenti
CREATE TABLE viaggi_alternative (
  id_viaggio_alternativa INT AUTO_INCREMENT PRIMARY KEY,
  id_viaggio INT NOT NULL,
  breve_descrizione VARCHAR(100),
  FOREIGN KEY (id_viaggio) REFERENCES viaggi(id_viaggio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE viaggi_tratte
  ADD COLUMN id_viaggio_alternativa INT,
  ADD CONSTRAINT fk_viaggi_tratte_alternativa FOREIGN KEY (id_viaggio_alternativa) REFERENCES viaggi_alternative(id_viaggio_alternativa),
  DROP COLUMN gruppo_alternativa;

ALTER TABLE viaggi_alloggi
  ADD COLUMN id_viaggio_alternativa INT,
  ADD CONSTRAINT fk_viaggi_alloggi_alternativa FOREIGN KEY (id_viaggio_alternativa) REFERENCES viaggi_alternative(id_viaggio_alternativa),
  DROP COLUMN gruppo_alternativa;

CREATE OR REPLACE VIEW v_totali_alternative AS
SELECT
  vt.id_viaggio,
  alt.id_viaggio_alternativa,
  alt.breve_descrizione,
  SUM(
    (COALESCE(vt.distanza_km,0) * COALESCE(vt.consumo_litri_100km,0) / 100) * COALESCE(vt.prezzo_carburante_eur_litro,0)
    + COALESCE(vt.pedaggi_eur,0)
    + COALESCE(vt.costo_traghetto_eur,0)
    + COALESCE(vt.costo_volo_eur,0)
    + COALESCE(vt.costo_noleggio_eur,0)
    + COALESCE(vt.altri_costi_eur,0)
  ) AS totale_trasporti,
  (
    SELECT COALESCE(SUM(DATEDIFF(va.data_checkout, va.data_checkin) * COALESCE(va.costo_notte_eur,0)),0)
    FROM viaggi_alloggi va
    WHERE va.id_viaggio = vt.id_viaggio AND va.id_viaggio_alternativa = vt.id_viaggio_alternativa
  ) AS totale_alloggi,
  (
    SUM(
      (COALESCE(vt.distanza_km,0) * COALESCE(vt.consumo_litri_100km,0) / 100) * COALESCE(vt.prezzo_carburante_eur_litro,0)
      + COALESCE(vt.pedaggi_eur,0)
      + COALESCE(vt.costo_traghetto_eur,0)
      + COALESCE(vt.costo_volo_eur,0)
      + COALESCE(vt.costo_noleggio_eur,0)
      + COALESCE(vt.altri_costi_eur,0)
    )
    + (
      SELECT COALESCE(SUM(DATEDIFF(va.data_checkout, va.data_checkin) * COALESCE(va.costo_notte_eur,0)),0)
      FROM viaggi_alloggi va
      WHERE va.id_viaggio = vt.id_viaggio AND va.id_viaggio_alternativa = vt.id_viaggio_alternativa
    )
  ) AS totale_viaggio
FROM viaggi_tratte vt
JOIN viaggi_alternative alt ON vt.id_viaggio_alternativa = alt.id_viaggio_alternativa
GROUP BY vt.id_viaggio, vt.id_viaggio_alternativa;
