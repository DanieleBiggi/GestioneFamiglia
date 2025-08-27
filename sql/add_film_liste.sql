CREATE TABLE `film_liste` (
  `id_lista` INT NOT NULL AUTO_INCREMENT,
  `id_utente` INT NOT NULL,
  `nome` VARCHAR(100) NOT NULL,
  `inserito_il` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aggiornato_il` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_lista`),
  KEY `idx_film_liste_utente` (`id_utente`),
  CONSTRAINT `fk_film_liste_utenti` FOREIGN KEY (`id_utente`) REFERENCES `utenti`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `film2liste` (
  `id_film` INT NOT NULL,
  `id_lista` INT NOT NULL,
  PRIMARY KEY (`id_film`, `id_lista`),
  KEY `idx_film2liste_lista` (`id_lista`),
  CONSTRAINT `fk_film2liste_film` FOREIGN KEY (`id_film`) REFERENCES `film`(`id_film`) ON DELETE CASCADE,
  CONSTRAINT `fk_film2liste_lista` FOREIGN KEY (`id_lista`) REFERENCES `film_liste`(`id_lista`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
