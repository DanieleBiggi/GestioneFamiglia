-- Eseguire MANUALMENTE sul DB (MySQL 5.6+) dopo backup.
-- Solo le vacanze importate dalle API sono visibili tramite API.
-- Non associare automaticamente vacanze esistenti: verificare a chi appartengono.
CREATE TABLE IF NOT EXISTS viaggi_api_famiglie (
  id_viaggio INT(11) NOT NULL,
  id_famiglia INT(11) NOT NULL,
  PRIMARY KEY (id_viaggio),
  KEY idx_viaggi_api_famiglie_famiglia (id_famiglia, id_viaggio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
