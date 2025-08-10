ALTER TABLE utenti
  ADD COLUMN id_tema INT NOT NULL DEFAULT 1 AFTER id_famiglia_gestione;

ALTER TABLE utenti
  ADD CONSTRAINT fk_utenti_temi FOREIGN KEY (id_tema) REFERENCES temi(id),
  ADD INDEX idx_id_tema (id_tema);
