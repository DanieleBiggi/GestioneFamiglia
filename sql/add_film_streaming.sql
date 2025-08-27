CREATE TABLE `streaming_piattaforme` (
  `id_piattaforma` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(50) NOT NULL,
  `icon` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id_piattaforma`)
);

CREATE TABLE `film2piattaforme` (
  `id_film` INT NOT NULL,
  `id_piattaforma` INT NOT NULL,
  `indicata_il` DATE NOT NULL,
  PRIMARY KEY (`id_film`, `id_piattaforma`),
  KEY `idx_film2piattaforme_piattaforma` (`id_piattaforma`),
  CONSTRAINT `fk_film2piattaforme_film` FOREIGN KEY (`id_film`) REFERENCES `film`(`id_film`) ON DELETE CASCADE,
  CONSTRAINT `fk_film2piattaforme_piattaforma` FOREIGN KEY (`id_piattaforma`) REFERENCES `streaming_piattaforme`(`id_piattaforma`) ON DELETE CASCADE
);

INSERT INTO `streaming_piattaforme` (`id_piattaforma`, `nome`, `icon`) VALUES
(1, 'Nessuna', 'assets/streaming/none.svg'),
(2, 'Netflix', 'assets/streaming/netflix.svg'),
(3, 'Prime Video', 'assets/streaming/prime.svg'),
(4, 'Disney+', 'assets/streaming/disney.svg');
