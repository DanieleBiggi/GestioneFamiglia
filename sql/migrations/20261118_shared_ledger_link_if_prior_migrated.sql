-- ONLY if the original 20261118_mobile_pocket_conti_condivisi.sql was already
-- executed BEFORE the id_e2o column was added to the CREATE TABLE statement.
-- Do not run after the current full migration, where id_e2o already exists.
ALTER TABLE etichette_conti_condivisi
  ADD COLUMN id_e2o INT DEFAULT NULL COMMENT 'Movimento bancario etichettato di origine, se presente',
  ADD UNIQUE KEY uq_movimento_persona (id_e2o,controparte);
