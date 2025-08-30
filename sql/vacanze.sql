CREATE TABLE viaggi_luoghi (
  id_luogo INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  citta VARCHAR(100),
  regione VARCHAR(100),
  paese VARCHAR(100),
  lat DECIMAL(9,6),
  lng DECIMAL(9,6),
  url_maps VARCHAR(255),
  sito_web VARCHAR(255),
  place_id VARCHAR(255),
  note_apertura TEXT,
  note TEXT,
  creato_il DATETIME DEFAULT CURRENT_TIMESTAMP,
  aggiornato_il DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE viaggi_luogo_foto (
  id_foto INT AUTO_INCREMENT PRIMARY KEY,
  id_luogo INT NOT NULL,
  photo_reference VARCHAR(255) NOT NULL,
  FOREIGN KEY (id_luogo) REFERENCES viaggi_luoghi(id_luogo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE viaggi (
  id_viaggio INT AUTO_INCREMENT PRIMARY KEY,
  titolo VARCHAR(150) NOT NULL,
  id_luogo INT,
  data_inizio DATE,
  data_fine DATE,
  notti INT,
  persone INT,
  stato ENUM('idea','shortlist','pianificato','prenotato','fatto','scartato') DEFAULT 'idea',
  priorita TINYINT,
  visibilita ENUM('private','shared','public') DEFAULT 'private',
  token_condivisione CHAR(22),
  id_foto INT,
  breve_descrizione VARCHAR(255),
  note TEXT,
  meteo_previsto_json JSON,
  meteo_aggiornato_il DATETIME,
  creato_il DATETIME DEFAULT CURRENT_TIMESTAMP,
  aggiornato_il DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (id_luogo) REFERENCES viaggi_luoghi(id_luogo),
  FOREIGN KEY (id_foto) REFERENCES viaggi_luogo_foto(id_foto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE viaggi_alternative (
  id_viaggio_alternativa INT AUTO_INCREMENT PRIMARY KEY,
  id_viaggio INT NOT NULL,
  breve_descrizione VARCHAR(100),
  FOREIGN KEY (id_viaggio) REFERENCES viaggi(id_viaggio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE viaggi_tratte (
  id_tratta INT AUTO_INCREMENT PRIMARY KEY,
  id_viaggio INT NOT NULL,
  id_viaggio_alternativa INT,
  tipo_tratta ENUM('auto','aereo','traghetto','treno') NOT NULL,
  descrizione TEXT,
  origine_testo VARCHAR(255),
  origine_lat DECIMAL(9,6),
  origine_lng DECIMAL(9,6),
  destinazione_testo VARCHAR(255),
  destinazione_lat DECIMAL(9,6),
  destinazione_lng DECIMAL(9,6),
  distanza_km DECIMAL(8,2),
  durata_ore DECIMAL(5,2),
  consumo_litri_100km DECIMAL(5,2),
  prezzo_carburante_eur_litro DECIMAL(5,3),
  pedaggi_eur DECIMAL(10,2),
  costo_traghetto_eur DECIMAL(10,2),
  costo_volo_eur DECIMAL(10,2),
  costo_noleggio_eur DECIMAL(10,2),
  altri_costi_eur DECIMAL(10,2),
  note TEXT,
  FOREIGN KEY (id_viaggio) REFERENCES viaggi(id_viaggio),
  FOREIGN KEY (id_viaggio_alternativa) REFERENCES viaggi_alternative(id_viaggio_alternativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE viaggi_alloggi (
  id_alloggio INT AUTO_INCREMENT PRIMARY KEY,
  id_viaggio INT NOT NULL,
  id_viaggio_alternativa INT,
  giorno_indice INT,
  nome_alloggio VARCHAR(255),
  indirizzo VARCHAR(255),
  lat DECIMAL(9,6),
  lng DECIMAL(9,6),
  data_checkin DATE,
  data_checkout DATE,
  costo_notte_eur DECIMAL(10,2),
  note TEXT,
  FOREIGN KEY (id_viaggio) REFERENCES viaggi(id_viaggio),
  FOREIGN KEY (id_viaggio_alternativa) REFERENCES viaggi_alternative(id_viaggio_alternativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE viaggi_etichette (
  id_etichetta INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE viaggi2etichette (
  id_viaggio INT NOT NULL,
  id_etichetta INT NOT NULL,
  PRIMARY KEY (id_viaggio, id_etichetta),
  FOREIGN KEY (id_viaggio) REFERENCES viaggi(id_viaggio) ON DELETE CASCADE,
  FOREIGN KEY (id_etichetta) REFERENCES viaggi_etichette(id_etichetta) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE viaggi_checklist (
  id_checklist INT AUTO_INCREMENT PRIMARY KEY,
  id_viaggio INT NOT NULL,
  voce VARCHAR(255) NOT NULL,
  completata TINYINT(1) DEFAULT 0,
  id_utente INT,
  FOREIGN KEY (id_viaggio) REFERENCES viaggi(id_viaggio) ON DELETE CASCADE,
  FOREIGN KEY (id_utente) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE viaggi_checklist_messaggi (
  id_messaggio INT AUTO_INCREMENT PRIMARY KEY,
  id_checklist INT NOT NULL,
  id_utente INT NOT NULL,
  messaggio TEXT NOT NULL,
  creato_il DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_checklist) REFERENCES viaggi_checklist(id_checklist) ON DELETE CASCADE,
  FOREIGN KEY (id_utente) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE viaggi_feedback (
  id_feedback INT AUTO_INCREMENT PRIMARY KEY,
  id_viaggio INT NOT NULL,
  id_utente INT,
  voto TINYINT,
  commento TEXT,
  creato_il DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_viaggio) REFERENCES viaggi(id_viaggio) ON DELETE CASCADE,
  FOREIGN KEY (id_utente) REFERENCES utenti(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE viaggi2caricamenti (
  id_viaggio INT NOT NULL,
  id_caricamento INT NOT NULL,
  PRIMARY KEY (id_viaggio, id_caricamento),
  FOREIGN KEY (id_viaggio) REFERENCES viaggi(id_viaggio) ON DELETE CASCADE,
  FOREIGN KEY (id_caricamento) REFERENCES ocr_caricamenti(id_caricamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE VIEW v_totali_alternative AS
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

CREATE VIEW v_eventi_viaggi AS
SELECT v.titolo, v.data_inizio, v.data_fine
FROM viaggi v
WHERE v.stato IN ('pianificato','prenotato');
