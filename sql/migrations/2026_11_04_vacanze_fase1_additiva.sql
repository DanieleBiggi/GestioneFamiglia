-- Vacanze, fase 1: migrazione ADDITIVA e retrocompatibile.
-- Non elimina colonne, non cambia le viste esistenti, non modifica le query PHP.
-- Prima di eseguire: backup e verifica dello schema reale rispetto a sql/struttura.sql.
-- NON rieseguire dopo un'applicazione parziale senza verificare le tabelle create.
-- Le nuove associazioni diventano fonte primaria SOLO dopo il rilascio coordinato del codice.
-- I commenti pubblici saranno mostrati ai visitatori SOLO dopo controlli server su token/scadenza/scenari.
-- Il token deve essere generato con random_bytes(32), salvando SOLO SHA-256 esadecimale.

CREATE TABLE IF NOT EXISTS viaggi_tratte_alternative (
  id_tratta INT(11) NOT NULL,
  id_viaggio_alternativa INT(11) NOT NULL,
  PRIMARY KEY (id_tratta,id_viaggio_alternativa),
  KEY idx_vta_alternativa (id_viaggio_alternativa),
  CONSTRAINT fk_vta_tratta FOREIGN KEY (id_tratta) REFERENCES viaggi_tratte(id_tratta) ON DELETE CASCADE,
  CONSTRAINT fk_vta_alternativa FOREIGN KEY (id_viaggio_alternativa) REFERENCES viaggi_alternative(id_viaggio_alternativa) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS viaggi_alloggi_alternative (
  id_alloggio INT(11) NOT NULL,
  id_viaggio_alternativa INT(11) NOT NULL,
  PRIMARY KEY (id_alloggio,id_viaggio_alternativa),
  KEY idx_vaa_alternativa (id_viaggio_alternativa),
  CONSTRAINT fk_vaa_alloggio FOREIGN KEY (id_alloggio) REFERENCES viaggi_alloggi(id_alloggio) ON DELETE CASCADE,
  CONSTRAINT fk_vaa_alternativa FOREIGN KEY (id_viaggio_alternativa) REFERENCES viaggi_alternative(id_viaggio_alternativa) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS viaggi_pasti_alternative (
  id_pasto INT(11) NOT NULL,
  id_viaggio_alternativa INT(11) NOT NULL,
  PRIMARY KEY (id_pasto,id_viaggio_alternativa),
  KEY idx_vpa_alternativa (id_viaggio_alternativa),
  CONSTRAINT fk_vpa_pasto FOREIGN KEY (id_pasto) REFERENCES viaggi_pasti(id_pasto) ON DELETE CASCADE,
  CONSTRAINT fk_vpa_alternativa FOREIGN KEY (id_viaggio_alternativa) REFERENCES viaggi_alternative(id_viaggio_alternativa) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS viaggi_altri_costi_alternative (
  id_costo INT(11) NOT NULL,
  id_viaggio_alternativa INT(11) NOT NULL,
  PRIMARY KEY (id_costo,id_viaggio_alternativa),
  KEY idx_vaca_alternativa (id_viaggio_alternativa),
  CONSTRAINT fk_vaca_costo FOREIGN KEY (id_costo) REFERENCES viaggi_altri_costi(id_costo) ON DELETE CASCADE,
  CONSTRAINT fk_vaca_alternativa FOREIGN KEY (id_viaggio_alternativa) REFERENCES viaggi_alternative(id_viaggio_alternativa) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Importazione dei collegamenti preesistenti, senza alterare le colonne legacy.
-- La JOIN su id_viaggio impedisce collegamenti incrociati tra vacanze.
INSERT IGNORE INTO viaggi_tratte_alternative (id_tratta,id_viaggio_alternativa)
SELECT e.id_tratta,a.id_viaggio_alternativa FROM viaggi_tratte e
JOIN viaggi_alternative a ON a.id_viaggio_alternativa=e.id_viaggio_alternativa AND a.id_viaggio=e.id_viaggio;
INSERT IGNORE INTO viaggi_alloggi_alternative (id_alloggio,id_viaggio_alternativa)
SELECT e.id_alloggio,a.id_viaggio_alternativa FROM viaggi_alloggi e
JOIN viaggi_alternative a ON a.id_viaggio_alternativa=e.id_viaggio_alternativa AND a.id_viaggio=e.id_viaggio;
INSERT IGNORE INTO viaggi_pasti_alternative (id_pasto,id_viaggio_alternativa)
SELECT e.id_pasto,a.id_viaggio_alternativa FROM viaggi_pasti e
JOIN viaggi_alternative a ON a.id_viaggio_alternativa=e.id_viaggio_alternativa AND a.id_viaggio=e.id_viaggio;
INSERT IGNORE INTO viaggi_altri_costi_alternative (id_costo,id_viaggio_alternativa)
SELECT e.id_costo,a.id_viaggio_alternativa FROM viaggi_altri_costi e
JOIN viaggi_alternative a ON a.id_viaggio_alternativa=e.id_viaggio_alternativa AND a.id_viaggio=e.id_viaggio;

-- Una condivisione ha un insieme di scenari e scade dopo 7 giorni.
-- L'applicazione imposterà scade_il = UTC_TIMESTAMP() + INTERVAL 7 DAY.
CREATE TABLE IF NOT EXISTS viaggi_condivisioni (
  id_condivisione BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_viaggio INT(11) NOT NULL,
  token_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  creato_da INT(11) NOT NULL,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  scade_il DATETIME NOT NULL,
  revocato_il DATETIME DEFAULT NULL,
  PRIMARY KEY(id_condivisione),
  UNIQUE KEY uq_vc_token (token_sha256),
  KEY idx_vc_viaggio (id_viaggio),
  KEY idx_vc_scadenza (scade_il),
  CONSTRAINT fk_vc_viaggio FOREIGN KEY(id_viaggio) REFERENCES viaggi(id_viaggio) ON DELETE CASCADE,
  CONSTRAINT fk_vc_autore FOREIGN KEY(creato_da) REFERENCES utenti(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS viaggi_condivisioni_alternative (
  id_condivisione BIGINT UNSIGNED NOT NULL,
  id_viaggio_alternativa INT(11) NOT NULL,
  PRIMARY KEY(id_condivisione,id_viaggio_alternativa),
  KEY idx_vca_alternativa (id_viaggio_alternativa),
  CONSTRAINT fk_vca_condivisione FOREIGN KEY(id_condivisione) REFERENCES viaggi_condivisioni(id_condivisione) ON DELETE CASCADE,
  CONSTRAINT fk_vca_alternativa FOREIGN KEY(id_viaggio_alternativa) REFERENCES viaggi_alternative(id_viaggio_alternativa) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Commenti sulla vacanza o su una specifica alternativa, indipendenti dal link.
-- visibilita='pubblico': leggibile via link valido soltanto nel perimetro autorizzato.
-- visibilita='interno': accessibile solo nel portale con permessi adeguati.
CREATE TABLE IF NOT EXISTS viaggi_commenti (
  id_commento BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_viaggio INT(11) NOT NULL,
  id_viaggio_alternativa INT(11) DEFAULT NULL,
  id_utente INT(11) NOT NULL,
  visibilita ENUM('pubblico','interno') NOT NULL DEFAULT 'interno',
  testo TEXT NOT NULL,
  creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificato_il DATETIME DEFAULT NULL,
  PRIMARY KEY(id_commento),
  KEY idx_vcommenti_viaggio_visibilita (id_viaggio,visibilita,creato_il),
  KEY idx_vcommenti_alternativa (id_viaggio_alternativa),
  CONSTRAINT fk_vcommenti_viaggio FOREIGN KEY(id_viaggio) REFERENCES viaggi(id_viaggio) ON DELETE CASCADE,
  CONSTRAINT fk_vcommenti_alternativa FOREIGN KEY(id_viaggio_alternativa) REFERENCES viaggi_alternative(id_viaggio_alternativa) ON DELETE CASCADE,
  CONSTRAINT fk_vcommenti_autore FOREIGN KEY(id_utente) REFERENCES utenti(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verifiche manuali: ogni conteggio dei link legacy deve coincidere con i link importati.
SELECT 'tratte' AS categoria,COUNT(*) AS legacy FROM viaggi_tratte WHERE id_viaggio_alternativa IS NOT NULL
UNION ALL SELECT 'alloggi',COUNT(*) FROM viaggi_alloggi WHERE id_viaggio_alternativa IS NOT NULL
UNION ALL SELECT 'pasti',COUNT(*) FROM viaggi_pasti WHERE id_viaggio_alternativa IS NOT NULL
UNION ALL SELECT 'altri_costi',COUNT(*) FROM viaggi_altri_costi WHERE id_viaggio_alternativa IS NOT NULL;
SELECT 'tratte' AS categoria,COUNT(*) AS importati FROM viaggi_tratte_alternative
UNION ALL SELECT 'alloggi',COUNT(*) FROM viaggi_alloggi_alternative
UNION ALL SELECT 'pasti',COUNT(*) FROM viaggi_pasti_alternative
UNION ALL SELECT 'altri_costi',COUNT(*) FROM viaggi_altri_costi_alternative;

-- ATTENZIONE: la prima fase NON attiva il nuovo modello.
-- Prima di scrivere associazioni multiple adeguare: letture e CRUD spese, cancellazione
-- alternativa, update pagamento, v_totali_alternative, vacanze_view.php, vacanze_lista.php.
-- Il codice deve verificare che spesa, alternativa e condivisione appartengano
-- alla stessa vacanza; le FK semplici da sole non impongono questa regola.
