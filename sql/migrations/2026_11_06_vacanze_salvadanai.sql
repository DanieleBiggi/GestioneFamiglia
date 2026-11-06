-- Migration additive: conferma scenario e collegamento a salvadanaio.
-- Da applicare dopo un backup; non altera i record esistenti.
-- L'obiettivo è separato dal saldo e dai movimenti del salvadanaio.
CREATE TABLE IF NOT EXISTS viaggi_pianificazione (
  id_viaggio INT(11) NOT NULL,
  id_alternativa_confermata INT(11) DEFAULT NULL,
  id_salvadanaio INT(11) DEFAULT NULL,
  obiettivo_salvadanaio DECIMAL(10,2) DEFAULT NULL,
  PRIMARY KEY (id_viaggio),
  UNIQUE KEY uq_vp_salvadanaio (id_salvadanaio),
  KEY idx_vp_scenario (id_alternativa_confermata),
  CONSTRAINT fk_vp_viaggio FOREIGN KEY (id_viaggio)
    REFERENCES viaggi(id_viaggio) ON DELETE CASCADE,
  CONSTRAINT fk_vp_scenario FOREIGN KEY (id_alternativa_confermata)
    REFERENCES viaggi_alternative(id_viaggio_alternativa) ON DELETE SET NULL,
  CONSTRAINT fk_vp_salvadanaio FOREIGN KEY (id_salvadanaio)
    REFERENCES salvadanai(id_salvadanaio) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
