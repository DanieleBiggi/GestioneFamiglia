-- RIPRISTINO Disney+ (SOLO se lo script cutover e' stato eseguito).
-- Non eseguire per ora. MySQL 5.6.
-- Il ripristino riporta il database allo stato precedente SOLO per le nove
-- entrate, le nove associazioni a Disney+ e le nove quote originali.
-- Non eseguire se nuove operazioni sono gia' state aggiunte su Disney+
-- dopo la conversione: prima riconciliarle.
-- Le tre tabelle backup devono contenere rispettivamente 9, 9, 9 righe.
-- Le 9 righe del ledger vengono individuate per esatta data/importo/descrizione
-- delle nove vecchie entrate archiviate e PERSONA='Bocci'.
-- La cancellazione del ledger usa il matching con backup, mai tutti i
-- movimenti della controparte.
DELIMITER $$
CREATE PROCEDURE ripristina_disney2026_compensazioni()
BEGIN
  DECLARE n INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;
  START TRANSACTION;
  SELECT (SELECT COUNT(*) FROM backup_disney2026_entrate_20261118)
       + (SELECT COUNT(*) FROM backup_disney2026_etichette_20261118)
       + (SELECT COUNT(*) FROM backup_disney2026_quote_20261118) INTO n;
  IF n<>27 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Backup incompleti: interrotto';
  END IF;
  SELECT COUNT(*) INTO n FROM bilancio_entrate
  WHERE id_entrata IN (2637,2638,2639,2640,2679,2680,2681,2694,2702);
  IF n<>0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Entrate originali gia presenti';
  END IF;
  SELECT COUNT(*) INTO n
  FROM etichette_conti_condivisi ec
  JOIN backup_disney2026_entrate_20261118 b
    ON ec.id_entrata_origine=b.id_entrata
  WHERE ec.id_etichetta=164 AND ec.id_famiglia=1
    AND ec.id_utente=7 AND ec.controparte='Bocci'
    AND ec.tipo='compensazione'
    AND ec.importo=-ABS(b.importo)
    AND ec.saldata=0 AND ec.data_saldo IS NULL;
  IF n<>9 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Compensazioni diverse dal backup o gia saldate: interrotto';
  END IF;
  DELETE ec FROM etichette_conti_condivisi ec
  JOIN backup_disney2026_entrate_20261118 b
    ON ec.id_entrata_origine=b.id_entrata
  WHERE ec.id_etichetta=164 AND ec.id_famiglia=1 AND ec.id_utente=7;
  INSERT INTO bilancio_entrate SELECT * FROM backup_disney2026_entrate_20261118;
  INSERT INTO bilancio_etichette2operazioni SELECT * FROM backup_disney2026_etichette_20261118;
  INSERT INTO bilancio_utenti2operazioni_etichettate SELECT * FROM backup_disney2026_quote_20261118;
  COMMIT;
END$$
DELIMITER ;
CALL ripristina_disney2026_compensazioni();
DROP PROCEDURE ripristina_disney2026_compensazioni;
