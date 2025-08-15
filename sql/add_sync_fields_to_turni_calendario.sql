ALTER TABLE `turni_calendario`
  ADD COLUMN `aggiornato_il` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `note`,
  ADD COLUMN `data_ultima_sincronizzazione` datetime DEFAULT NULL AFTER `aggiornato_il`;
