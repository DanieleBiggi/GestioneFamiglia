CREATE TABLE utenti2salvadanai (
  id_u2s INT(11) NOT NULL AUTO_INCREMENT,
  id_utente INT(11) NOT NULL,
  id_salvadanaio INT(11) NOT NULL,
  nascosto TINYINT(1) NOT NULL DEFAULT 0,
  preferito TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id_u2s),
  UNIQUE KEY uq_u2s (id_utente, id_salvadanaio),
  CONSTRAINT fk_u2s_utente FOREIGN KEY (id_utente) REFERENCES utenti(id) ON DELETE CASCADE,
  CONSTRAINT fk_u2s_salvadanaio FOREIGN KEY (id_salvadanaio) REFERENCES salvadanai(id_salvadanaio) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
