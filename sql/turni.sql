CREATE TABLE turni_tipi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descrizione VARCHAR(100) NOT NULL,
    colore_bg VARCHAR(7) NOT NULL,
    colore_testo VARCHAR(7) NOT NULL DEFAULT '#000000',
    attivo TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE turni_calendario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_famiglia INT NOT NULL,
    data DATE NOT NULL,
    id_tipo INT NOT NULL,
    UNIQUE KEY uniq_turno (id_famiglia, data),
    FOREIGN KEY (id_famiglia) REFERENCES famiglie(id_famiglia),
    FOREIGN KEY (id_tipo) REFERENCES turni_tipi(id)
);
