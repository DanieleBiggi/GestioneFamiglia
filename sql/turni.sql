CREATE TABLE turni_tipi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descrizione VARCHAR(100) NOT NULL,
    ora_inizio TIME DEFAULT NULL,
    ora_fine TIME DEFAULT NULL,
    colore_bg VARCHAR(7) NOT NULL,
    colore_testo VARCHAR(7) NOT NULL DEFAULT '#000000',
    attivo TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE turni_calendario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_famiglia INT NOT NULL,
    data DATE NOT NULL,
    ora_inizio TIME NOT NULL,
    ora_fine TIME NOT NULL,
    id_tipo INT NOT NULL,
    google_calendar_eventid VARCHAR(255) DEFAULT NULL,
    id_utenti_bambini VARCHAR(255) DEFAULT NULL,
    note TEXT DEFAULT NULL,
    aggiornato_il DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    data_ultima_sincronizzazione DATETIME DEFAULT NULL,
    UNIQUE KEY uniq_turno (id_famiglia, data),
    FOREIGN KEY (id_famiglia) REFERENCES famiglie(id_famiglia),
    FOREIGN KEY (id_tipo) REFERENCES turni_tipi(id)
);
