CREATE TABLE codici_2fa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_utente INT NOT NULL,
    codice VARCHAR(6) NOT NULL,
    scadenza DATETIME NOT NULL,
    FOREIGN KEY (id_utente) REFERENCES utenti(id) ON DELETE CASCADE
);

