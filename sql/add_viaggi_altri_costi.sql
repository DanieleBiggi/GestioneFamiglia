CREATE TABLE viaggi_altri_costi (
  id_costo INT AUTO_INCREMENT PRIMARY KEY,
  id_viaggio INT NOT NULL,
  id_viaggio_alternativa INT,
  data DATE,
  importo_eur DECIMAL(10,2),
  note TEXT,
  FOREIGN KEY (id_viaggio) REFERENCES viaggi(id_viaggio),
  FOREIGN KEY (id_viaggio_alternativa) REFERENCES viaggi_alternative(id_viaggio_alternativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    SELECT COALESCE(SUM(vp.costo_medio_eur),0)
    FROM viaggi_pasti vp
    WHERE vp.id_viaggio = vt.id_viaggio AND vp.id_viaggio_alternativa = vt.id_viaggio_alternativa
  ) AS totale_pasti,
  (
    SELECT COALESCE(SUM(vac.importo_eur),0)
    FROM viaggi_altri_costi vac
    WHERE vac.id_viaggio = vt.id_viaggio AND vac.id_viaggio_alternativa = vt.id_viaggio_alternativa
  ) AS totale_altri_costi,
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
    + (
      SELECT COALESCE(SUM(vp.costo_medio_eur),0)
      FROM viaggi_pasti vp
      WHERE vp.id_viaggio = vt.id_viaggio AND vp.id_viaggio_alternativa = vt.id_viaggio_alternativa
    )
    + (
      SELECT COALESCE(SUM(vac.importo_eur),0)
      FROM viaggi_altri_costi vac
      WHERE vac.id_viaggio = vt.id_viaggio AND vac.id_viaggio_alternativa = vt.id_viaggio_alternativa
    )
  ) AS totale_viaggio
FROM viaggi_tratte vt
JOIN viaggi_alternative alt ON vt.id_viaggio_alternativa = alt.id_viaggio_alternativa
GROUP BY vt.id_viaggio, vt.id_viaggio_alternativa;
