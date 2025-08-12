CREATE TABLE `lista_spesa` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_famiglia` INT NOT NULL,
  `nome` VARCHAR(255) NOT NULL,
  `quantita` VARCHAR(100) DEFAULT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `checked` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lista_spesa_famiglia` (`id_famiglia`),
  CONSTRAINT `fk_lista_spesa_famiglie` FOREIGN KEY (`id_famiglia`) REFERENCES `famiglie`(`id_famiglia`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
