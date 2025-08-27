CREATE TABLE `film_gruppi` (
  `id_gruppo` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `inserito_il` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aggiornato_il` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_gruppo`),
  UNIQUE KEY `uq_film_gruppi_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `film`
  ADD COLUMN `id_gruppo` INT DEFAULT NULL AFTER `lingua_originale`,
  ADD KEY `idx_film_gruppo` (`id_gruppo`),
  ADD CONSTRAINT `fk_film_gruppo` FOREIGN KEY (`id_gruppo`) REFERENCES `film_gruppi`(`id_gruppo`) ON DELETE SET NULL;
