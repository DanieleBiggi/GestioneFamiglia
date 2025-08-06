CREATE TABLE dispositivi_riconosciuti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_utente INT NOT NULL,
    token_dispositivo VARCHAR(255) NOT NULL UNIQUE,
    user_agent TEXT,
    ip VARCHAR(45),
    data_attivazione DATETIME NOT NULL,
    scadenza DATETIME NOT NULL,
    FOREIGN KEY (id_utente) REFERENCES utenti(id)
);
