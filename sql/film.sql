CREATE TABLE `film_gruppi` (
  `id_gruppo` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `inserito_il` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aggiornato_il` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_gruppo`),
  UNIQUE KEY `uq_film_gruppi_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `film` (
  `id_film` INT NOT NULL AUTO_INCREMENT,
  `tmdb_id` INT NOT NULL,
  `titolo` VARCHAR(255) NOT NULL,
  `titolo_originale` VARCHAR(255) DEFAULT NULL,
  `anno` YEAR DEFAULT NULL,
  `durata` INT DEFAULT NULL,
  `trama` TEXT,
  `poster_url` VARCHAR(255) DEFAULT NULL,
  `lingua_originale` VARCHAR(10) DEFAULT NULL,
  `id_gruppo` INT DEFAULT NULL,
  `inserito_il` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aggiornato_il` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_film`),
  UNIQUE KEY `uq_film_tmdb` (`tmdb_id`),
  KEY `idx_film_gruppo` (`id_gruppo`),
  CONSTRAINT `fk_film_gruppo` FOREIGN KEY (`id_gruppo`) REFERENCES `film_gruppi`(`id_gruppo`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `film_generi` (
  `id_genere` INT NOT NULL,
  `nome` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id_genere`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `film2generi` (
  `id_film` INT NOT NULL,
  `id_genere` INT NOT NULL,
  PRIMARY KEY (`id_film`, `id_genere`),
  KEY `idx_film2generi_genere` (`id_genere`),
  CONSTRAINT `fk_film2generi_film` FOREIGN KEY (`id_film`) REFERENCES `film`(`id_film`) ON DELETE CASCADE,
  CONSTRAINT `fk_film2generi_genere` FOREIGN KEY (`id_genere`) REFERENCES `film_generi`(`id_genere`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `film_utenti` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_film` INT NOT NULL,
  `id_utente` INT NOT NULL,
  `data_visto` DATE DEFAULT NULL,
  `voto` DECIMAL(3,1) DEFAULT NULL,
  `inserito_il` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aggiornato_il` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_film_utente` (`id_film`,`id_utente`),
  KEY `idx_film_utenti_utente` (`id_utente`),
  CONSTRAINT `fk_film_utenti_film` FOREIGN KEY (`id_film`) REFERENCES `film`(`id_film`) ON DELETE CASCADE,
  CONSTRAINT `fk_film_utenti_utenti` FOREIGN KEY (`id_utente`) REFERENCES `utenti`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `film_commenti` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_film` INT NOT NULL,
  `id_utente` INT NOT NULL,
  `commento` TEXT NOT NULL,
  `inserito_il` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_film_commenti_film` (`id_film`),
  KEY `idx_film_commenti_utente` (`id_utente`),
  CONSTRAINT `fk_film_commenti_film` FOREIGN KEY (`id_film`) REFERENCES `film`(`id_film`) ON DELETE CASCADE,
  CONSTRAINT `fk_film_commenti_utente` FOREIGN KEY (`id_utente`) REFERENCES `utenti`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
