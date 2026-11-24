-- Fondazioni menu relazionale, MySQL 5.6. Non modifica i dati esistenti.
CREATE TABLE ricette (
 id_ricetta INT NOT NULL AUTO_INCREMENT, id_famiglia INT NOT NULL,
 codice VARCHAR(80) NOT NULL, nome VARCHAR(150) NOT NULL, istruzioni TEXT,
 porzioni SMALLINT UNSIGNED, tempo_preparazione SMALLINT UNSIGNED, tempo_cottura SMALLINT UNSIGNED,
 note TEXT, attiva TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id_ricetta), UNIQUE KEY(id_famiglia,codice),
 FOREIGN KEY(id_famiglia) REFERENCES famiglie(id_famiglia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE ingredienti (
 id_ingrediente INT NOT NULL AUTO_INCREMENT, id_famiglia INT NOT NULL,
 nome VARCHAR(150) NOT NULL, unita_predefinita VARCHAR(20), note VARCHAR(255),
 attivo TINYINT(1) NOT NULL DEFAULT 1,
 PRIMARY KEY(id_ingrediente), UNIQUE KEY(id_famiglia,nome),
 FOREIGN KEY(id_famiglia) REFERENCES famiglie(id_famiglia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE ricette_ingredienti (
 id INT NOT NULL AUTO_INCREMENT, id_ricetta INT NOT NULL, id_ingrediente INT NOT NULL,
 quantita DECIMAL(10,3), unita_misura VARCHAR(20), facoltativo TINYINT(1) NOT NULL DEFAULT 0, note VARCHAR(255),
 PRIMARY KEY(id), FOREIGN KEY(id_ricetta) REFERENCES ricette(id_ricetta) ON DELETE CASCADE,
 FOREIGN KEY(id_ingrediente) REFERENCES ingredienti(id_ingrediente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE piatti (
 id_piatto INT NOT NULL AUTO_INCREMENT, id_famiglia INT NOT NULL,
 nome VARCHAR(150) NOT NULL, id_ricetta INT DEFAULT NULL, attivo TINYINT(1) NOT NULL DEFAULT 1,
 PRIMARY KEY(id_piatto), UNIQUE KEY(id_famiglia,nome),
 FOREIGN KEY(id_famiglia) REFERENCES famiglie(id_famiglia),
 FOREIGN KEY(id_ricetta) REFERENCES ricette(id_ricetta) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE menu_cene_master (
 id_master INT NOT NULL AUTO_INCREMENT, id_famiglia INT NOT NULL,
 anno_scolastico VARCHAR(9) NOT NULL, data_riferimento DATE NOT NULL,
 settimana_riferimento TINYINT UNSIGNED NOT NULL, numero_settimane TINYINT UNSIGNED NOT NULL DEFAULT 4,
 attivo TINYINT(1) NOT NULL DEFAULT 0,
 PRIMARY KEY(id_master), KEY(id_famiglia,attivo),
 FOREIGN KEY(id_famiglia) REFERENCES famiglie(id_famiglia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE menu_cene_master_piatti (
 id INT NOT NULL AUTO_INCREMENT, id_master INT NOT NULL, id_piatto INT NOT NULL,
 stagione ENUM('Estivo','Invernale') NOT NULL, settimana TINYINT UNSIGNED NOT NULL,
 giorno VARCHAR(20) NOT NULL, tipo ENUM('principale','contorno','altro') NOT NULL,
 ordine SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY(id), UNIQUE KEY(id_master,stagione,settimana,giorno,tipo,ordine),
 FOREIGN KEY(id_master) REFERENCES menu_cene_master(id_master) ON DELETE CASCADE,
 FOREIGN KEY(id_piatto) REFERENCES piatti(id_piatto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE menu_cene_piatti (
 id INT NOT NULL AUTO_INCREMENT, id_menu INT NOT NULL, id_piatto INT NOT NULL,
 nome VARCHAR(255) NOT NULL, tipo ENUM('principale','contorno','altro') NOT NULL,
 ordine SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY(id), UNIQUE KEY(id_menu,tipo,ordine),
 FOREIGN KEY(id_menu) REFERENCES menu_cene_settimanale(id) ON DELETE CASCADE,
 FOREIGN KEY(id_piatto) REFERENCES piatti(id_piatto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE lista_spesa_associazioni (
 id INT NOT NULL AUTO_INCREMENT, id_lista_spesa INT NOT NULL, id_menu_piatto INT NOT NULL,
 id_ingrediente INT DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id), UNIQUE KEY(id_lista_spesa,id_menu_piatto),
 FOREIGN KEY(id_lista_spesa) REFERENCES lista_spesa(id) ON DELETE CASCADE,
 FOREIGN KEY(id_menu_piatto) REFERENCES menu_cene_piatti(id) ON DELETE CASCADE,
 FOREIGN KEY(id_ingrediente) REFERENCES ingredienti(id_ingrediente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
