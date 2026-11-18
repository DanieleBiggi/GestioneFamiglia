-- Deploy before PHP files: additive changes, MySQL 5.6 compatible.
ALTER TABLE salvadanai
  ADD COLUMN accantonamento_modalita ENUM('automatico','fisso') NOT NULL DEFAULT 'automatico',
  ADD COLUMN accantonamento_fisso DECIMAL(10,2) DEFAULT NULL;

-- Manual shared-account ledger. No fictitious bank movements are created.
-- Positive amount = a receivable owed to the owner; negative = credit used/owner owes.
CREATE TABLE etichette_conti_condivisi (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_etichetta INT NOT NULL,
  id_famiglia INT NOT NULL,
  controparte VARCHAR(120) NOT NULL,
  data_movimento DATE NOT NULL,
  tipo ENUM('quota','compensazione','pagamento','rettifica') NOT NULL,
  importo DECIMAL(10,2) NOT NULL,
  descrizione VARCHAR(255) NOT NULL,
  creato_il TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_conto_etichetta (id_etichetta,id_famiglia,data_movimento),
  KEY idx_conto_controparte (id_etichetta,controparte)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
