ALTER TABLE `utenti2famiglie`
  ADD COLUMN `userlevelid` int(11) NOT NULL DEFAULT '0' AFTER `id_famiglia`;

ALTER TABLE `utenti2famiglie`
  ADD CONSTRAINT `fk_u2f_userlevels` FOREIGN KEY (`userlevelid`) REFERENCES `userlevels` (`userlevelid`),
  ADD UNIQUE KEY `uq_u2f` (`id_utente`,`id_famiglia`),
  ADD INDEX `idx_userlevelid` (`userlevelid`);
