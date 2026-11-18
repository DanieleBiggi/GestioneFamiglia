-- Applicare DOPO 20261118_mobile_pocket_conti_condivisi.sql.
-- Compatibile MySQL 5.6. Non converte i movimenti Disney+.
ALTER TABLE etichette_conti_condivisi
  ADD COLUMN id_utente INT DEFAULT NULL COMMENT 'Persona collegata alla famiglia',
  ADD COLUMN saldata TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN data_saldo DATETIME DEFAULT NULL,
  ADD COLUMN id_entrata_origine INT DEFAULT NULL COMMENT 'ID storico di entrata riconvertita (non e una FK)',
  ADD UNIQUE KEY uq_compensazione_origine (id_entrata_origine),
  ADD KEY idx_conto_utente_saldo (id_famiglia,id_utente,saldata);
