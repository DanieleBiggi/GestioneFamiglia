CREATE TABLE turni_sync_google_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_turno INT DEFAULT NULL,
    id_evento INT DEFAULT NULL,
    azione VARCHAR(50) NOT NULL,
    esito ENUM('success','error') NOT NULL,
    messaggio TEXT,
    data_creazione DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_turno (id_turno),
    INDEX idx_evento (id_evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
