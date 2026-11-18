-- Disney+ 2026, ID etichetta 164: CONVERSIONE DA ESEGUIRE MANUALMENTE.
-- NON ESEGUIRE FINCHE' NON E' STATO VALIDATO IL PASSAGGIO DALLA VECCHIA
-- VISTA "QUOTE PER PERSONA" AL REGISTRO "CONTO CONDIVISO".
--
-- MySQL 5.6, famiglia legacy 1, controparte Bocci (utente legacy ID 7).
-- PREREQUISITO: eseguire prima 20261118_conti_compensazioni_crediti.sql.
-- Cambia i dati reali! Richiede backup completo del database PRIMA di tutto.
-- Le nove entrate di bilancio_entrate saranno RIMOSSE e sostituite da
-- nove righe nella tabella etichette_conti_condivisi. I vecchi riepiloghi
-- basati sulla vista delle quote non vedranno piu' questi movimenti.
-- Nessun movimento Revolut viene modificato.
--
-- NOTA PHPMyAdmin: le istruzioni DELIMITER potrebbero richiedere
-- l'impostazione del delimitatore nella schermata SQL.
--
-- Backup persistenti con i dati originali (non cancellarli):
-- backup_disney2026_entrate_20261118
-- backup_disney2026_etichette_20261118
-- backup_disney2026_quote_20261118
--
-- Eseguire QUESTO SCRIPT UNA SOLA VOLTA quando si e' pronti al cutover.
CREATE TABLE backup_disney2026_entrate_20261118 LIKE bilancio_entrate;
CREATE TABLE backup_disney2026_etichette_20261118 LIKE bilancio_etichette2operazioni;
CREATE TABLE backup_disney2026_quote_20261118 LIKE bilancio_utenti2operazioni_etichettate;

DELIMITER $$
CREATE PROCEDURE converti_disney2026_entrate_in_compensazioni()
BEGIN
  DECLARE n INT DEFAULT 0;
  DECLARE totale DECIMAL(14,2) DEFAULT 0.00;
  DECLARE esistenti INT DEFAULT 0;
  DECLARE quote INT DEFAULT 0;
  DECLARE altre INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;

  -- Verifica dataset preciso: 9 entrate per 127,50 euro, tutte contanti,
  -- di utente 1, senza caricamento importato e con etichetta 164.
  SELECT COUNT(*), COALESCE(SUM(be.importo),0)
    INTO n,totale
  FROM bilancio_entrate be
  JOIN bilancio_etichette2operazioni eo
    ON eo.tabella_operazione='bilancio_entrate'
   AND eo.id_tabella=be.id_entrata AND eo.id_etichetta=164
  WHERE be.id_entrata IN (2637,2638,2639,2640,2679,2680,2681,2694,2702)
    AND be.id_utente=1 AND be.mezzo='contanti'
    AND be.id_caricamento IS NULL AND eo.importo IS NULL
  FOR UPDATE;
  IF n<>9 OR totale<>127.50 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dataset Disney inatteso: interrotto';
  END IF;

  -- Non cancellare movimenti legati ad altre etichette.
  SELECT COUNT(*) INTO altre
  FROM bilancio_etichette2operazioni
  WHERE tabella_operazione='bilancio_entrate'
    AND id_tabella IN (2637,2638,2639,2640,2679,2680,2681,2694,2702)
    AND id_etichetta<>164;
  IF altre<>0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Altre etichette collegate: interrotto';
  END IF;

  -- Quote esistenti esattamente come nel preflight, solo Bocci/ID 7.
  SELECT COUNT(*) INTO quote
  FROM bilancio_utenti2operazioni_etichettate uo
  JOIN bilancio_etichette2operazioni eo ON eo.id_e2o=uo.id_e2o
  WHERE eo.tabella_operazione='bilancio_entrate'
    AND eo.id_tabella IN (2637,2638,2639,2640,2679,2680,2681,2694,2702)
    AND eo.id_etichetta=164
    AND uo.id_utente=7 AND uo.importo_utente IS NULL
    AND uo.saldata=0 AND uo.quote=1;
  IF quote<>9 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Quote Disney diverse dal preflight: interrotto';
  END IF;
  SELECT COUNT(*) INTO altre
  FROM bilancio_utenti2operazioni_etichettate uo
  JOIN bilancio_etichette2operazioni eo ON eo.id_e2o=uo.id_e2o
  WHERE eo.tabella_operazione='bilancio_entrate'
    AND eo.id_tabella IN (2637,2638,2639,2640,2679,2680,2681,2694,2702)
    AND (eo.id_etichetta<>164 OR uo.id_utente<>7);
  IF altre<>0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Quote ulteriori: interrotto';
  END IF;

  -- Blocco prudenziale: non migrare se Disney ha GIA' righe nel nuovo
  -- registro. Riconciliare prima per evitare duplicati.
  SELECT COUNT(*) INTO esistenti
  FROM etichette_conti_condivisi
  WHERE id_etichetta=164 AND id_famiglia=1;
  IF esistenti<>0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro Disney non vuoto: verificare duplicati';
  END IF;

  -- I backup devono essere vuoti; non sovrascrivere una migrazione passata.
  SELECT
    (SELECT COUNT(*) FROM backup_disney2026_entrate_20261118)
    +(SELECT COUNT(*) FROM backup_disney2026_etichette_20261118)
    +(SELECT COUNT(*) FROM backup_disney2026_quote_20261118)
  INTO esistenti;
  IF esistenti<>0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Backup Disney non vuoti: interrompere';
  END IF;

  INSERT INTO backup_disney2026_entrate_20261118
    SELECT be.* FROM bilancio_entrate be
    WHERE be.id_entrata IN (2637,2638,2639,2640,2679,2680,2681,2694,2702);
  INSERT INTO backup_disney2026_etichette_20261118
    SELECT eo.* FROM bilancio_etichette2operazioni eo
    WHERE eo.tabella_operazione='bilancio_entrate'
      AND eo.id_tabella IN (2637,2638,2639,2640,2679,2680,2681,2694,2702);
  INSERT INTO backup_disney2026_quote_20261118
    SELECT uo.* FROM bilancio_utenti2operazioni_etichettate uo
    JOIN bilancio_etichette2operazioni eo ON eo.id_e2o=uo.id_e2o
    WHERE eo.tabella_operazione='bilancio_entrate'
      AND eo.id_tabella IN (2637,2638,2639,2640,2679,2680,2681,2694,2702);

  -- Conserva data, importo, descrizione e ID di origine; id_e2o
  -- deve essere NULL perche' la vecchia riga e2o sara' rimossa.
  INSERT INTO etichette_conti_condivisi
    (id_etichetta,id_famiglia,controparte,data_movimento,tipo,importo,descrizione,id_e2o,id_utente,saldata,data_saldo,id_entrata_origine)
  SELECT 164,1,'Bocci',DATE(be.data_operazione),'compensazione',
         -ABS(be.importo),
         LEFT(COALESCE(NULLIF(be.descrizione_extra,''),be.descrizione_operazione,'Compensazione'),255),
         NULL,7,0,NULL,be.id_entrata
  FROM bilancio_entrate be
  WHERE be.id_entrata IN (2637,2638,2639,2640,2679,2680,2681,2694,2702);

  SELECT COUNT(*),COALESCE(SUM(importo),0) INTO n,totale
  FROM etichette_conti_condivisi
  WHERE id_etichetta=164 AND id_famiglia=1 AND controparte='Bocci' AND tipo='compensazione';
  IF n<>9 OR totale<>-127.50 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Saldo compensazioni non coerente';
  END IF;

  DELETE uo FROM bilancio_utenti2operazioni_etichettate uo
    JOIN backup_disney2026_quote_20261118 b ON b.id_u2o=uo.id_u2o;
  DELETE eo FROM bilancio_etichette2operazioni eo
    JOIN backup_disney2026_etichette_20261118 b ON b.id_e2o=eo.id_e2o;
  DELETE be FROM bilancio_entrate be
    JOIN backup_disney2026_entrate_20261118 b ON b.id_entrata=be.id_entrata;

  SELECT COUNT(*) INTO n FROM bilancio_entrate
  WHERE id_entrata IN (2637,2638,2639,2640,2679,2680,2681,2694,2702);
  IF n<>0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Entrate originali ancora presenti';
  END IF;

  COMMIT;
END$$
DELIMITER ;

CALL converti_disney2026_entrate_in_compensazioni();
DROP PROCEDURE converti_disney2026_entrate_in_compensazioni;

-- Controllo a posteriori: totale deve essere -127,50.
SELECT COUNT(*) AS compensazioni, SUM(importo) AS totale
FROM etichette_conti_condivisi
WHERE id_etichetta=164 AND id_famiglia=1 AND controparte='Bocci';
SELECT COUNT(*) AS entrate_originali_residue
FROM bilancio_entrate
WHERE id_entrata IN (2637,2638,2639,2640,2679,2680,2681,2694,2702);
