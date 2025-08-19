-- phpMyAdmin SQL Dump
-- version 5.1.0
-- https://www.phpmyadmin.net/
--
-- Host: 89.46.111.63:3306
-- Creato il: Ago 19, 2025 alle 13:22
-- Versione del server: 5.6.51-91.0-log
-- Versione PHP: 8.0.7

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `Sql1203781_2`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `amazon_orders`
--

CREATE TABLE `amazon_orders` (
  `id_amazon_order` int(11) NOT NULL,
  `id_movimento_revolut` int(11) DEFAULT NULL,
  `id_uscita` int(11) DEFAULT NULL,
  `order_id` varchar(255) DEFAULT NULL,
  `order_url` varchar(255) DEFAULT NULL,
  `items` mediumtext,
  `recipient` varchar(255) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `total_amount` decimal(11,5) DEFAULT NULL,
  `shipping_cost` decimal(11,5) DEFAULT NULL,
  `shipping_refund` decimal(11,5) DEFAULT NULL,
  `gift` decimal(11,5) DEFAULT NULL,
  `vat_amount` decimal(11,5) DEFAULT NULL,
  `refund_amount` decimal(11,5) DEFAULT NULL,
  `payments` varchar(255) DEFAULT NULL,
  `descrizione_extra` varchar(250) DEFAULT NULL,
  `completato` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `bilancio`
--

CREATE TABLE `bilancio` (
  `id_bilancio` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `banca` decimal(32,2) NOT NULL,
  `carte` decimal(32,2) NOT NULL,
  `liquidi` decimal(32,2) NOT NULL,
  `data_inizio` datetime NOT NULL,
  `data_fine` datetime NOT NULL,
  `attivo` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `bilancio_carte`
--

CREATE TABLE `bilancio_carte` (
  `id_bilancio` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_carta` int(11) NOT NULL,
  `carte` decimal(32,2) NOT NULL,
  `data_inizio` datetime NOT NULL,
  `data_fine` datetime NOT NULL,
  `attivo` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `bilancio_descrizione2id`
--

CREATE TABLE `bilancio_descrizione2id` (
  `id_d2id` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `descrizione` varchar(100) NOT NULL,
  `id_gruppo_transazione` int(11) NOT NULL,
  `id_metodo_pagamento` int(11) NOT NULL,
  `id_etichetta` int(11) DEFAULT NULL,
  `descrizione_extra` varchar(250) DEFAULT NULL,
  `conto` varchar(50) DEFAULT 'credit'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `bilancio_entrate`
--

CREATE TABLE `bilancio_entrate` (
  `id_entrata` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_caricamento` int(11) DEFAULT NULL,
  `mezzo` enum('banca','carte','contanti') NOT NULL DEFAULT 'banca',
  `id_tipologia` int(11) DEFAULT NULL,
  `id_gruppo_transazione` int(11) DEFAULT NULL,
  `id_metodo_pagamento` int(11) DEFAULT NULL,
  `descrizione_operazione` longtext,
  `descrizione_extra` varchar(250) DEFAULT NULL,
  `importo` decimal(32,2) NOT NULL,
  `note` mediumtext,
  `data_operazione` datetime NOT NULL,
  `data_inserimento` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_aggiornamento` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `bilancio_etichette`
--

CREATE TABLE `bilancio_etichette` (
  `id_etichetta` int(11) NOT NULL,
  `descrizione` varchar(200) NOT NULL,
  `attivo` int(11) NOT NULL DEFAULT '1',
  `da_dividere` int(11) DEFAULT '0',
  `anno` int(11) DEFAULT NULL,
  `mese` tinyint(4) DEFAULT NULL,
  `utenti_tra_cui_dividere` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `bilancio_etichette2operazioni`
--

CREATE TABLE `bilancio_etichette2operazioni` (
  `id_e2o` int(11) NOT NULL,
  `id_etichetta` int(11) NOT NULL,
  `id_caricamento` int(11) DEFAULT NULL,
  `tabella_operazione` varchar(50) NOT NULL DEFAULT 'bilancio_uscite',
  `id_tabella` int(11) NOT NULL,
  `descrizione_extra` varchar(250) DEFAULT NULL,
  `importo` decimal(11,2) DEFAULT NULL,
  `dividere_rimborsare` varchar(20) NOT NULL DEFAULT 'dividere',
  `allegato` varchar(100) DEFAULT NULL,
  `escludi_da_finanze_evento` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `bilancio_gruppi_categorie`
--

CREATE TABLE `bilancio_gruppi_categorie` (
  `id_categoria` int(11) NOT NULL,
  `descrizione_categoria` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `bilancio_gruppi_transazione`
--

CREATE TABLE `bilancio_gruppi_transazione` (
  `id_gruppo_transazione` int(11) NOT NULL,
  `id_categoria` int(11) DEFAULT NULL,
  `id_utente` int(11) NOT NULL DEFAULT '1',
  `descrizione` varchar(100) NOT NULL,
  `tipo_gruppo` enum('spese_base','divertimento','risparmio','') NOT NULL DEFAULT 'spese_base',
  `attivo` int(11) NOT NULL DEFAULT '1',
  `ricorsivo` int(11) NOT NULL DEFAULT '0',
  `ogni_quanto` int(11) NOT NULL,
  `cosa_quanto` enum('mese','settimana','giorno','anno') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `bilancio_metodo_pagamento`
--

CREATE TABLE `bilancio_metodo_pagamento` (
  `id_metodo_pagamento` int(11) NOT NULL,
  `descrizione_metodo_pagamento` varchar(100) NOT NULL,
  `attivo` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `bilancio_ricorsivi`
--

CREATE TABLE `bilancio_ricorsivi` (
  `id_ricorsivo` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `descrizione` varchar(100) NOT NULL,
  `attivo` int(11) NOT NULL DEFAULT '1',
  `ogni` enum('anno','mese','settimana','giorno','data_precisa') NOT NULL,
  `quanto` varchar(20) DEFAULT NULL,
  `importo` decimal(11,2) NOT NULL,
  `descrizione_operazione` varchar(100) DEFAULT NULL,
  `id_gruppo_transazione` int(11) DEFAULT NULL,
  `condivise` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `bilancio_tipologie`
--

CREATE TABLE `bilancio_tipologie` (
  `id_tipologia` int(11) NOT NULL,
  `nome_tipologia` varchar(100) NOT NULL,
  `attivo` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `bilancio_uscite`
--

CREATE TABLE `bilancio_uscite` (
  `id_uscita` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_caricamento` int(11) DEFAULT NULL,
  `mezzo` enum('banca','carte','contanti') NOT NULL DEFAULT 'banca',
  `id_tipologia` int(11) DEFAULT NULL,
  `id_gruppo_transazione` int(11) DEFAULT NULL,
  `id_metodo_pagamento` int(11) DEFAULT NULL,
  `descrizione_operazione` longtext,
  `descrizione_extra` varchar(250) DEFAULT NULL,
  `importo` decimal(32,2) NOT NULL,
  `note` mediumtext,
  `data_operazione` datetime NOT NULL,
  `data_inserimento` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_aggiornamento` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `bilancio_utenti2operazioni_etichettate`
--

CREATE TABLE `bilancio_utenti2operazioni_etichettate` (
  `id_u2o` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_e2o` int(11) NOT NULL,
  `importo_utente` decimal(11,7) DEFAULT NULL,
  `utente_pagante` int(11) NOT NULL DEFAULT '0',
  `saldata` int(11) NOT NULL DEFAULT '0',
  `data_saldo` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `quote` decimal(11,5) DEFAULT '1.00000'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `budget`
--

CREATE TABLE `budget` (
  `id_budget` int(11) NOT NULL,
  `id_famiglia` int(11) NOT NULL,
  `id_salvadanaio` int(11) DEFAULT NULL,
  `tipologia` enum('entrata','uscita') NOT NULL,
  `importo` decimal(10,2) NOT NULL,
  `descrizione` varchar(255) DEFAULT NULL,
  `data_inizio` date NOT NULL,
  `data_scadenza` date DEFAULT NULL,
  `tipologia_spesa` enum('fissa','una_tantum','mensile') NOT NULL,
  `da_13esima` decimal(10,2) DEFAULT '0.00',
  `da_14esima` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `carte`
--

CREATE TABLE `carte` (
  `id_carta` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `numero` varchar(100) NOT NULL,
  `descrizione` varchar(100) NOT NULL,
  `attiva` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `codici_2fa`
--

CREATE TABLE `codici_2fa` (
  `id` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `codice` varchar(6) NOT NULL,
  `scadenza` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `contabilita`
--

CREATE TABLE `contabilita` (
  `id_contabilita` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `entrata_uscita` char(1) NOT NULL,
  `descrizione` mediumtext NOT NULL,
  `importo` decimal(10,2) NOT NULL,
  `data` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `dati_remoti`
--

CREATE TABLE `dati_remoti` (
  `id_dato_remoto` int(11) NOT NULL,
  `descrizione` varchar(200) DEFAULT NULL,
  `stringa_da_completare` mediumtext,
  `parametri` mediumtext,
  `risultati` longtext,
  `archiviato` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 PACK_KEYS=0;

-- --------------------------------------------------------

--
-- Struttura della tabella `dispositivi_riconosciuti`
--

CREATE TABLE `dispositivi_riconosciuti` (
  `id` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `token_dispositivo` varchar(180) NOT NULL,
  `user_agent` mediumtext,
  `ip` varchar(45) DEFAULT NULL,
  `data_attivazione` datetime NOT NULL,
  `scadenza` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `eventi`
--

CREATE TABLE `eventi` (
  `id` int(11) NOT NULL,
  `titolo` varchar(100) DEFAULT NULL,
  `data_evento` date DEFAULT NULL,
  `ora_evento` varchar(10) DEFAULT NULL,
  `data_fine` date DEFAULT NULL,
  `ora_fine` time DEFAULT NULL,
  `descrizione` varchar(100) DEFAULT NULL,
  `id_tipo_evento` int(11) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `note` mediumtext,
  `google_calendar_eventid` varchar(180) DEFAULT NULL,
  `creator_email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `eventi_cibo`
--

CREATE TABLE `eventi_cibo` (
  `id` int(11) NOT NULL,
  `id_famiglia` int(11) NOT NULL,
  `piatto` varchar(100) NOT NULL,
  `dolce` int(11) NOT NULL DEFAULT '0',
  `bere` int(11) NOT NULL DEFAULT '0',
  `um` enum('etti','quantita','porzioni','litri') NOT NULL,
  `attivo` int(11) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `eventi_cibo2ocr_prodotti_spesa`
--

CREATE TABLE `eventi_cibo2ocr_prodotti_spesa` (
  `id_ec2sp` int(11) NOT NULL,
  `id_e2c` int(11) NOT NULL,
  `id_ca2pr` int(11) NOT NULL,
  `id_utente_pagante` int(11) NOT NULL,
  `id_prodotto` int(11) NOT NULL COMMENT 'Se senza scontrino',
  `prezzo_prodotto` decimal(11,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Coll eventi_eventi2cibo e ocr_caricamenti2prodotti_spesa';

-- --------------------------------------------------------

--
-- Struttura della tabella `eventi_cibo2spesa_prodotti`
--

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `eventi_cibo2spesa_prodotti`  AS SELECT `Sql1203781_2`.`spesa_prodotti`.`nome_prodotto` AS `nome_prodotto`, `Sql1203781_2`.`eventi_cibo2ocr_prodotti_spesa`.`id_e2c` AS `id_e2c`, `Sql1203781_2`.`eventi_cibo2ocr_prodotti_spesa`.`id_ec2sp` AS `id_ec2sp`, `Sql1203781_2`.`eventi_cibo2ocr_prodotti_spesa`.`id_utente_pagante` AS `id_utente_pagante`, `Sql1203781_2`.`eventi_cibo2ocr_prodotti_spesa`.`id_prodotto` AS `id_prodotto`, `Sql1203781_2`.`spesa_prodotti_prezzi`.`prezzo` AS `prezzo_scontrino`, `Sql1203781_2`.`spesa_prodotti_prezzi`.`sconto` AS `sconto_scontrino` FROM (((`eventi_cibo2ocr_prodotti_spesa` join `ocr_caricamenti2prodotti_spesa` on((`Sql1203781_2`.`eventi_cibo2ocr_prodotti_spesa`.`id_ca2pr` = `Sql1203781_2`.`ocr_caricamenti2prodotti_spesa`.`id_ca2pr`))) join `spesa_prodotti` on((`Sql1203781_2`.`ocr_caricamenti2prodotti_spesa`.`id_prodotto` = `Sql1203781_2`.`spesa_prodotti`.`id_prodotto`))) join `spesa_prodotti_prezzi` on(((`Sql1203781_2`.`spesa_prodotti_prezzi`.`id_prodotto` = `Sql1203781_2`.`spesa_prodotti`.`id_prodotto`) and (`Sql1203781_2`.`spesa_prodotti_prezzi`.`id_caricamento` = `Sql1203781_2`.`ocr_caricamenti2prodotti_spesa`.`id_caricamento`)))) WHERE (`Sql1203781_2`.`eventi_cibo2ocr_prodotti_spesa`.`id_prodotto` = 0) ;

-- --------------------------------------------------------

--
-- Struttura della tabella `eventi_eventi2cibo`
--

CREATE TABLE `eventi_eventi2cibo` (
  `id_e2c` int(11) NOT NULL,
  `id_evento` int(11) NOT NULL,
  `id_cibo` int(11) NOT NULL,
  `id_invitato` int(11) DEFAULT NULL,
  `quantita` decimal(11,2) DEFAULT NULL,
  `utente` int(11) DEFAULT NULL,
  `comprato` int(11) NOT NULL DEFAULT '0',
  `note` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `eventi_eventi2famiglie`
--

CREATE TABLE `eventi_eventi2famiglie` (
  `id_e2f` int(11) NOT NULL,
  `id_evento` int(11) NOT NULL,
  `id_famiglia` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `eventi_eventi2invitati`
--

CREATE TABLE `eventi_eventi2invitati` (
  `id_e2i` int(11) NOT NULL,
  `id_evento` int(11) NOT NULL,
  `id_invitato` int(11) NOT NULL,
  `partecipa` int(11) NOT NULL DEFAULT '0',
  `forse` int(11) NOT NULL DEFAULT '0',
  `assente` int(11) NOT NULL DEFAULT '0',
  `note` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `eventi_eventi2luogo`
--

CREATE TABLE `eventi_eventi2luogo` (
  `id_e2l` int(11) NOT NULL,
  `id_evento` int(11) NOT NULL,
  `id_luogo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `eventi_eventi2salvadanai_etichette`
--

CREATE TABLE `eventi_eventi2salvadanai_etichette` (
  `id_e2se` int(11) NOT NULL,
  `id_evento` int(11) DEFAULT NULL,
  `id_salvadanaio` int(11) DEFAULT NULL,
  `id_etichetta` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `eventi_google_rules`
--

CREATE TABLE `eventi_google_rules` (
  `id` int(11) NOT NULL,
  `creator_email` varchar(255) DEFAULT NULL,
  `description_keyword` varchar(100) DEFAULT NULL,
  `id_tipo_evento` int(11) DEFAULT NULL,
  `attiva` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `eventi_google_rules_invitati`
--

CREATE TABLE `eventi_google_rules_invitati` (
  `id_rule` int(11) NOT NULL,
  `id_invitato` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `eventi_invitati`
--

CREATE TABLE `eventi_invitati` (
  `id` int(11) NOT NULL,
  `id_utente` int(11) DEFAULT NULL,
  `nome` varchar(50) NOT NULL,
  `cognome` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `eventi_invitati2famiglie`
--

CREATE TABLE `eventi_invitati2famiglie` (
  `id_i2f` int(11) NOT NULL,
  `id_invitato` int(11) NOT NULL,
  `id_famiglia` int(11) NOT NULL,
  `data_inizio` date NOT NULL,
  `data_fine` date NOT NULL DEFAULT '9999-12-31',
  `attivo` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `eventi_luogo`
--

CREATE TABLE `eventi_luogo` (
  `id` int(11) NOT NULL,
  `indirizzo` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `eventi_tipi_eventi`
--

CREATE TABLE `eventi_tipi_eventi` (
  `id` int(11) NOT NULL,
  `tipo_evento` varchar(100) NOT NULL,
  `colore` varchar(10) NOT NULL DEFAULT '#71843f',
  `colore_testo` varchar(7) NOT NULL DEFAULT '#ffffff',
  `attivo` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `famiglie`
--

CREATE TABLE `famiglie` (
  `id_famiglia` int(11) NOT NULL,
  `nome_famiglia` varchar(100) NOT NULL,
  `in_gestione` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `gestione_account_password`
--

CREATE TABLE `gestione_account_password` (
  `id_account_password` int(11) NOT NULL,
  `id_famiglia` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `url_login` varchar(250) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_account` varchar(100) NOT NULL,
  `condivisa_con_famiglia` int(11) NOT NULL DEFAULT '0',
  `attiva` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `importazioni_file`
--

CREATE TABLE `importazioni_file` (
  `id_file` int(11) NOT NULL,
  `nome_file` varchar(100) NOT NULL,
  `mezzo` varchar(20) NOT NULL DEFAULT 'banca',
  `delimitatore` char(1) NOT NULL,
  `separatore` char(1) NOT NULL,
  `data_ultimo_upload` datetime NOT NULL,
  `offset` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `importazioni_file_colonne`
--

CREATE TABLE `importazioni_file_colonne` (
  `id_colonna` int(11) NOT NULL,
  `id_file` int(11) DEFAULT NULL,
  `nome_colonna_file` varchar(100) NOT NULL,
  `nome_colonna_db` varchar(100) NOT NULL,
  `posizione_colonna` int(11) DEFAULT NULL,
  `lettera_colonna_file` char(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `importazioni_file_file2colonne`
--

CREATE TABLE `importazioni_file_file2colonne` (
  `id_c2f` int(11) NOT NULL,
  `id_file` int(11) NOT NULL,
  `id_colonna` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `lista_spesa`
--

CREATE TABLE `lista_spesa` (
  `id` int(11) NOT NULL,
  `id_famiglia` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `quantita` varchar(100) NOT NULL,
  `note` varchar(255) NOT NULL,
  `checked` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `menu_smartadmin`
--

CREATE TABLE `menu_smartadmin` (
  `id` int(12) NOT NULL,
  `id_blocco` int(12) NOT NULL DEFAULT '0',
  `label` varchar(255) NOT NULL DEFAULT '',
  `link` varchar(255) NOT NULL DEFAULT '',
  `posizione` int(11) NOT NULL DEFAULT '0',
  `icona` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `mezzi`
--

CREATE TABLE `mezzi` (
  `id_mezzo` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_famiglia` int(11) NOT NULL,
  `nome_mezzo` varchar(100) NOT NULL,
  `data_immatricolazione` date NOT NULL,
  `attivo` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `mezzi_chilometri`
--

CREATE TABLE `mezzi_chilometri` (
  `id_chilometro` int(11) NOT NULL,
  `id_mezzo` int(11) NOT NULL,
  `data_chilometro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `chilometri` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `mezzi_mezzi2tagliandi`
--

CREATE TABLE `mezzi_mezzi2tagliandi` (
  `id_m2t` int(11) NOT NULL,
  `id_mezzo` int(11) NOT NULL,
  `id_tagliando` int(11) DEFAULT NULL,
  `km_tagliando` int(11) DEFAULT NULL,
  `data_tagliando` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `mezzi_tagliandi`
--

CREATE TABLE `mezzi_tagliandi` (
  `id_tagliando` int(11) NOT NULL,
  `id_mezzo` int(11) NOT NULL,
  `id_famiglia` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `mesi_da_immatricolazione` int(11) NOT NULL,
  `mesi_da_precedente_tagliando` int(11) NOT NULL,
  `massimo_km_tagliando` int(11) NOT NULL,
  `frequenza_mesi` int(11) NOT NULL,
  `frequenza_km` int(11) NOT NULL,
  `nome_tagliando` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `movimenti2caricamenti`
--

CREATE TABLE `movimenti2caricamenti` (
  `id` int(11) NOT NULL,
  `id_movimento_revolut` int(11) NOT NULL,
  `id_caricamento` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `movimenti_hype`
--

CREATE TABLE `movimenti_hype` (
  `id_movimento_hype` int(11) NOT NULL,
  `id_gruppo_transazione` int(11) DEFAULT NULL,
  `data_operazione` date DEFAULT NULL,
  `data_contabile` date DEFAULT NULL,
  `tipologia` varchar(50) DEFAULT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `descrizione` mediumtext,
  `importo` decimal(10,2) DEFAULT NULL,
  `inserita_il` datetime DEFAULT CURRENT_TIMESTAMP,
  `aggiornata_il` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `id_etichetta` int(11) DEFAULT NULL,
  `tabella_operazione` varchar(20) DEFAULT 'movimenti_hype'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `movimenti_poste`
--

CREATE TABLE `movimenti_poste` (
  `id_movimento_posta` int(11) NOT NULL,
  `id_categoria_posta` int(11) DEFAULT NULL,
  `id_gruppo_transazione` int(11) DEFAULT NULL,
  `data_contabile` date DEFAULT NULL,
  `data_valuta` date DEFAULT NULL,
  `addebito` decimal(11,2) DEFAULT NULL,
  `accredito` decimal(11,2) DEFAULT NULL,
  `descrizione` varchar(500) DEFAULT NULL,
  `descrizione_extra` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 PACK_KEYS=0;

-- --------------------------------------------------------

--
-- Struttura della tabella `movimenti_poste_categoria`
--

CREATE TABLE `movimenti_poste_categoria` (
  `id_categoria_posta` int(11) NOT NULL,
  `descrizione_categoria` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 PACK_KEYS=0;

-- --------------------------------------------------------

--
-- Struttura della tabella `movimenti_revolut`
--

CREATE TABLE `movimenti_revolut` (
  `id_movimento_revolut` int(11) NOT NULL,
  `id_caricamento` int(11) DEFAULT NULL,
  `id_gruppo_transazione` int(11) DEFAULT NULL,
  `id_salvadanaio` int(11) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `product` varchar(100) DEFAULT NULL,
  `started_date` datetime DEFAULT NULL,
  `completed_date` datetime DEFAULT NULL,
  `description` varchar(250) DEFAULT NULL,
  `amount` decimal(11,5) DEFAULT NULL,
  `note` mediumtext,
  `descrizione_extra` varchar(250) DEFAULT NULL,
  `tabella_operazione` varchar(20) DEFAULT 'movimenti_revolut',
  `id_etichetta` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `movimenti_revolut2salvadanaio_importazione`
--

CREATE TABLE `movimenti_revolut2salvadanaio_importazione` (
  `id_mov2salv` int(11) NOT NULL,
  `id_salvadanaio` int(11) DEFAULT NULL,
  `descrizione_importazione` varchar(250) DEFAULT NULL,
  `inserita_il` datetime(6) DEFAULT CURRENT_TIMESTAMP(6),
  `aggiornata_il` datetime(6) DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `ocr_caricamenti`
--

CREATE TABLE `ocr_caricamenti` (
  `id_caricamento` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_supermercato` int(11) NOT NULL,
  `data_caricamento` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `nome_file` varchar(200) NOT NULL,
  `data_scontrino` datetime DEFAULT NULL,
  `totale_scontrino` decimal(11,2) NOT NULL,
  `indirizzo_ip` varchar(20) NOT NULL,
  `JSON_linee` mediumtext NOT NULL,
  `descrizione` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `ocr_caricamenti2prodotti_spesa`
--

CREATE TABLE `ocr_caricamenti2prodotti_spesa` (
  `id_ca2pr` int(11) NOT NULL,
  `id_caricamento` int(11) NOT NULL,
  `id_prodotto` int(11) NOT NULL,
  `data_scontrino` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `ocr_prodotti2spesa_prodotti`
--

CREATE TABLE `ocr_prodotti2spesa_prodotti` (
  `id_d2id` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `descrizione` varchar(100) NOT NULL,
  `id_prodotto` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `ocr_supermercati`
--

CREATE TABLE `ocr_supermercati` (
  `id_supermercato` int(11) NOT NULL,
  `descrizione_supermercato` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `quanloop`
--

CREATE TABLE `quanloop` (
  `id_quanloop` int(11) NOT NULL,
  `data` date DEFAULT NULL,
  `totale_investito` decimal(15,5) DEFAULT NULL,
  `profitto_del_giorno` decimal(11,5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 PACK_KEYS=0;

-- --------------------------------------------------------

--
-- Struttura della tabella `reset_password`
--

CREATE TABLE `reset_password` (
  `id` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `scadenza` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `resources`
--

CREATE TABLE `resources` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('table','file') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `salvadanai`
--

CREATE TABLE `salvadanai` (
  `id_salvadanaio` int(11) NOT NULL,
  `nome_salvadanaio` varchar(250) DEFAULT NULL,
  `importo_attuale` decimal(10,2) NOT NULL DEFAULT '0.00',
  `data_aggiornamento_manuale` datetime DEFAULT NULL,
  `data_scadenza` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `session`
--

CREATE TABLE `session` (
  `id` int(12) NOT NULL,
  `sessione` varchar(255) NOT NULL DEFAULT '',
  `login` varchar(255) NOT NULL DEFAULT '',
  `passw` varchar(255) NOT NULL DEFAULT '',
  `ip_adress` varchar(255) NOT NULL DEFAULT '',
  `data_ora_ultimo_accesso` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `temi`
--

CREATE TABLE `temi` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `background_color` varchar(7) NOT NULL,
  `text_color` varchar(7) NOT NULL,
  `primary_color` varchar(7) NOT NULL,
  `secondary_color` varchar(7) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `time_dimension`
--

CREATE TABLE `time_dimension` (
  `id` int(11) NOT NULL,
  `db_date` date NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `day` int(11) NOT NULL,
  `quarter` int(11) NOT NULL,
  `week` int(11) NOT NULL,
  `day_name` varchar(9) NOT NULL,
  `month_name` varchar(9) NOT NULL,
  `holiday_flag` char(1) DEFAULT 'f',
  `weekend_flag` char(1) DEFAULT 'f',
  `event` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `turni_calendario`
--

CREATE TABLE `turni_calendario` (
  `id` int(11) NOT NULL,
  `id_famiglia` int(11) NOT NULL,
  `data` date NOT NULL,
  `ora_inizio` time NOT NULL,
  `ora_fine` time NOT NULL,
  `id_tipo` int(11) NOT NULL,
  `google_calendar_eventid` varchar(255) DEFAULT NULL,
  `id_utenti_bambini` varchar(255) DEFAULT NULL,
  `note` text,
  `aggiornato_il` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `data_ultima_sincronizzazione` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `turni_sync_google_log`
--

CREATE TABLE `turni_sync_google_log` (
  `id` int(11) NOT NULL,
  `id_turno` int(11) DEFAULT NULL,
  `id_evento` int(11) DEFAULT NULL,
  `azione` varchar(50) NOT NULL,
  `esito` enum('success','error') NOT NULL,
  `messaggio` text,
  `dati_evento` text,
  `data_creazione` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `turni_tipi`
--

CREATE TABLE `turni_tipi` (
  `id` int(11) NOT NULL,
  `descrizione` varchar(100) NOT NULL,
  `ora_inizio` time DEFAULT NULL,
  `ora_fine` time DEFAULT NULL,
  `colore_bg` varchar(7) NOT NULL,
  `colore_testo` varchar(7) NOT NULL DEFAULT '#000000',
  `attivo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `unita_misura`
--

CREATE TABLE `unita_misura` (
  `id_unita_misura` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `um` varchar(10) NOT NULL,
  `descrizione` varchar(100) NOT NULL,
  `id_padre` int(11) NOT NULL,
  `cambio_da_1_padre` decimal(11,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `userlevelpermissions`
--

CREATE TABLE `userlevelpermissions` (
  `userlevelid` int(11) NOT NULL,
  `tablename` varchar(191) NOT NULL DEFAULT '',
  `permission` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `userlevels`
--

CREATE TABLE `userlevels` (
  `userlevelid` int(11) NOT NULL,
  `userlevelname` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `userlevel_permissions`
--

CREATE TABLE `userlevel_permissions` (
  `userlevelid` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT '0',
  `can_insert` tinyint(1) NOT NULL DEFAULT '0',
  `can_update` tinyint(1) NOT NULL DEFAULT '0',
  `can_delete` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `utenti`
--

CREATE TABLE `utenti` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cognome` varchar(100) NOT NULL,
  `soprannome` char(10) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `id_famiglia_attuale` int(11) NOT NULL,
  `id_famiglia_gestione` int(11) NOT NULL,
  `id_tema` int(11) NOT NULL DEFAULT '1',
  `admin` int(11) NOT NULL,
  `bilancio_voluto_fine_anno` decimal(11,2) NOT NULL,
  `userlevelid` int(11) NOT NULL,
  `profile` mediumtext NOT NULL,
  `id_file` int(11) DEFAULT NULL,
  `attivo` int(11) DEFAULT '1',
  `disponibile_per_soso` int(11) DEFAULT '0',
  `passcode` varchar(255) DEFAULT NULL,
  `passcode_locked_until` datetime DEFAULT NULL,
  `passcode_attempts` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `utenti2famiglie`
--

CREATE TABLE `utenti2famiglie` (
  `id_u2f` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_famiglia` int(11) NOT NULL,
  `userlevelid` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `utenti2ip`
--

CREATE TABLE `utenti2ip` (
  `id_u2i` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `ip_address` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `utenti2menu_smartadmin`
--

CREATE TABLE `utenti2menu_smartadmin` (
  `id_collut2me` int(11) NOT NULL,
  `id_utenti` int(11) NOT NULL DEFAULT '0',
  `id_menu` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `utenti2salvadanai`
--

CREATE TABLE `utenti2salvadanai` (
  `id_u2s` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_salvadanaio` int(11) NOT NULL,
  `nascosto` tinyint(1) NOT NULL DEFAULT '0',
  `preferito` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_poste_categorie_totali`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_poste_categorie_totali` (
`id_categoria_posta` int(11)
,`descrizione_categoria` varchar(200)
,`accredito_tot` decimal(33,2)
,`addebito_tot` decimal(33,2)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_poste_gruppi_totali`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_poste_gruppi_totali` (
`id_gruppo_transazione` int(11)
,`descrizione` varchar(100)
,`accredito_tot` decimal(33,2)
,`addebito_tot` decimal(33,2)
,`anno` int(4)
,`mese` int(2)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_spese_mensili`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_spese_mensili` (
`id_utente` bigint(11)
,`descrizione` varchar(100)
,`attivo` int(11)
,`id_gruppo_transazione` int(11)
,`totale_speso` decimal(54,2)
,`totale_entrato` decimal(54,2)
,`ultima_operazione` datetime
,`anno` int(4)
,`mese` int(2)
);

-- --------------------------------------------------------

--
-- Struttura della tabella `v_turni`
--

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_turni`  AS SELECT `Sql1203781_2`.`turni`.`id_turno` AS `id_turno`, `Sql1203781_2`.`turni`.`id_famiglia` AS `id_famiglia`, `Sql1203781_2`.`turni`.`id_utente` AS `id_utente`, `Sql1203781_2`.`turni`.`data_turno` AS `data_turno`, (case when (ifnull(`Sql1203781_2`.`turni`.`orario_da`,0) > 0) then `Sql1203781_2`.`turni`.`orario_da` else `Sql1203781_2`.`turni_tipi`.`orario_da` end) AS `orario_da`, (case when (ifnull(`Sql1203781_2`.`turni`.`orario_a`,0) > 0) then `Sql1203781_2`.`turni`.`orario_a` else `Sql1203781_2`.`turni_tipi`.`orario_a` end) AS `orario_a`, (case when (`Sql1203781_2`.`turni_tipi`.`finisce_giorno_dopo` > 0) then (`Sql1203781_2`.`turni`.`data_turno` + interval 1 day) else `Sql1203781_2`.`turni`.`data_turno` end) AS `data_fine`, `Sql1203781_2`.`turni`.`descrizione` AS `descrizione`, `Sql1203781_2`.`turni`.`id_tipo_turno` AS `id_tipo_turno`, `Sql1203781_2`.`turni`.`note` AS `note`, `Sql1203781_2`.`turni`.`ore_straordinari` AS `ore_straordinari`, `Sql1203781_2`.`turni_tipi`.`tipo_turno` AS `tipo_turno`, `Sql1203781_2`.`turni_tipi`.`icon` AS `icon`, `Sql1203781_2`.`turni_tipi`.`colore` AS `colore`, 0 AS `aggiungi_turni_fino_a_fine_mese`, ifnull(`Sql1203781_2`.`turni`.`serve_qualcuno_per_soso`,`Sql1203781_2`.`turni_tipi`.`serve_qualcuno_per_soso`) AS `serve_qualcuno_per_soso`, `Sql1203781_2`.`turni`.`id_utente_per_soso` AS `id_utente_per_soso`, `Sql1203781_2`.`utenti`.`soprannome` AS `utente_per_soso` FROM ((`turni` left join `turni_tipi` on((`Sql1203781_2`.`turni`.`id_tipo_turno` = `Sql1203781_2`.`turni_tipi`.`id_tipo_turno`))) left join `utenti` on((`Sql1203781_2`.`turni`.`id_utente_per_soso` = `Sql1203781_2`.`utenti`.`id`))) ;

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_utenti2famiglie`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_utenti2famiglie` (
`id_famiglia` int(11)
,`nome_famiglia` varchar(100)
,`id` int(11)
,`nome` varchar(100)
,`cognome` varchar(100)
,`email` varchar(100)
,`id_famiglia_attuale` int(11)
,`userlevelid` int(11)
,`admin` int(11)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_utenti2famiglie_ricerca`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_utenti2famiglie_ricerca` (
`nome` varchar(100)
,`cognome` varchar(100)
,`email` varchar(100)
,`nome_famiglia` varchar(100)
,`in_gestione` int(11)
,`id_utente` int(11)
,`id_famiglia` int(11)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_utenti_ricerca`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_utenti_ricerca` (
`id_utente` int(11)
,`nome` varchar(100)
,`cognome` varchar(100)
,`fullname` varchar(201)
,`email` varchar(100)
,`attivo` int(11)
,`id_famiglia_attuale` int(11)
,`disponibile_per_soso` int(11)
);

-- --------------------------------------------------------

--
-- Struttura della tabella `webauthn_credentials`
--

CREATE TABLE `webauthn_credentials` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `credential_id` varchar(250) NOT NULL,
  `public_key` text NOT NULL,
  `counter` int(11) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura per vista `v_poste_categorie_totali`
--
DROP TABLE IF EXISTS `v_poste_categorie_totali`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_poste_categorie_totali`  AS SELECT `movimenti_poste_categoria`.`id_categoria_posta` AS `id_categoria_posta`, `movimenti_poste_categoria`.`descrizione_categoria` AS `descrizione_categoria`, sum(`movimenti_poste`.`accredito`) AS `accredito_tot`, sum(`movimenti_poste`.`addebito`) AS `addebito_tot` FROM (`movimenti_poste_categoria` join `movimenti_poste` on((`movimenti_poste_categoria`.`id_categoria_posta` = `movimenti_poste`.`id_categoria_posta`))) GROUP BY `movimenti_poste`.`id_categoria_posta` ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_poste_gruppi_totali`
--
DROP TABLE IF EXISTS `v_poste_gruppi_totali`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_poste_gruppi_totali`  AS SELECT `bilancio_gruppi_transazione`.`id_gruppo_transazione` AS `id_gruppo_transazione`, `bilancio_gruppi_transazione`.`descrizione` AS `descrizione`, sum(`movimenti_poste`.`accredito`) AS `accredito_tot`, sum(`movimenti_poste`.`addebito`) AS `addebito_tot`, year(`movimenti_poste`.`data_contabile`) AS `anno`, month(`movimenti_poste`.`data_contabile`) AS `mese` FROM (`bilancio_gruppi_transazione` join `movimenti_poste` on((`bilancio_gruppi_transazione`.`id_gruppo_transazione` = `movimenti_poste`.`id_gruppo_transazione`))) GROUP BY `movimenti_poste`.`id_gruppo_transazione`, year(`movimenti_poste`.`data_contabile`), month(`movimenti_poste`.`data_contabile`) ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_spese_mensili`
--
DROP TABLE IF EXISTS `v_spese_mensili`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_spese_mensili`  AS SELECT ifnull(`bilancio_uscite`.`id_utente`,`bilancio_entrate`.`id_utente`) AS `id_utente`, `bilancio_gruppi_transazione`.`descrizione` AS `descrizione`, `bilancio_gruppi_transazione`.`attivo` AS `attivo`, `bilancio_gruppi_transazione`.`id_gruppo_transazione` AS `id_gruppo_transazione`, sum(`bilancio_uscite`.`importo`) AS `totale_speso`, sum(`bilancio_entrate`.`importo`) AS `totale_entrato`, max(`bilancio_uscite`.`data_operazione`) AS `ultima_operazione`, year(ifnull(`bilancio_uscite`.`data_operazione`,`bilancio_entrate`.`data_operazione`)) AS `anno`, month(ifnull(`bilancio_uscite`.`data_operazione`,`bilancio_entrate`.`data_operazione`)) AS `mese` FROM ((`bilancio_gruppi_transazione` left join `bilancio_uscite` on((`bilancio_gruppi_transazione`.`id_gruppo_transazione` = `bilancio_uscite`.`id_gruppo_transazione`))) left join `bilancio_entrate` on((`bilancio_gruppi_transazione`.`id_gruppo_transazione` = `bilancio_entrate`.`id_gruppo_transazione`))) GROUP BY ifnull(`bilancio_uscite`.`id_utente`,`bilancio_entrate`.`id_utente`), year(ifnull(`bilancio_uscite`.`data_operazione`,`bilancio_entrate`.`data_operazione`)), month(ifnull(`bilancio_uscite`.`data_operazione`,`bilancio_entrate`.`data_operazione`)), ifnull(`bilancio_uscite`.`id_gruppo_transazione`,`bilancio_entrate`.`id_gruppo_transazione`) ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_utenti2famiglie`
--
DROP TABLE IF EXISTS `v_utenti2famiglie`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_utenti2famiglie`  AS SELECT `utenti2famiglie`.`id_famiglia` AS `id_famiglia`, `famiglie`.`nome_famiglia` AS `nome_famiglia`, `utenti`.`id` AS `id`, `utenti`.`nome` AS `nome`, `utenti`.`cognome` AS `cognome`, `utenti`.`email` AS `email`, `utenti`.`id_famiglia_attuale` AS `id_famiglia_attuale`, `utenti`.`userlevelid` AS `userlevelid`, `utenti`.`admin` AS `admin` FROM ((`utenti` left join `utenti2famiglie` on((`utenti`.`id` = `utenti2famiglie`.`id_utente`))) left join `famiglie` on((`utenti2famiglie`.`id_famiglia` = `famiglie`.`id_famiglia`))) ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_utenti2famiglie_ricerca`
--
DROP TABLE IF EXISTS `v_utenti2famiglie_ricerca`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_utenti2famiglie_ricerca`  AS SELECT `utenti`.`nome` AS `nome`, `utenti`.`cognome` AS `cognome`, `utenti`.`email` AS `email`, `famiglie`.`nome_famiglia` AS `nome_famiglia`, `famiglie`.`in_gestione` AS `in_gestione`, `utenti2famiglie`.`id_utente` AS `id_utente`, `utenti2famiglie`.`id_famiglia` AS `id_famiglia` FROM ((`utenti2famiglie` join `famiglie` on((`utenti2famiglie`.`id_famiglia` = `famiglie`.`id_famiglia`))) join `utenti` on((`utenti2famiglie`.`id_utente` = `utenti`.`id`))) ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_utenti_ricerca`
--
DROP TABLE IF EXISTS `v_utenti_ricerca`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_utenti_ricerca`  AS SELECT `utenti`.`id` AS `id_utente`, `utenti`.`nome` AS `nome`, `utenti`.`cognome` AS `cognome`, concat(`utenti`.`cognome`,' ',`utenti`.`nome`) AS `fullname`, `utenti`.`email` AS `email`, `utenti`.`attivo` AS `attivo`, `utenti`.`id_famiglia_attuale` AS `id_famiglia_attuale`, `utenti`.`disponibile_per_soso` AS `disponibile_per_soso` FROM `utenti` ;

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `amazon_orders`
--
ALTER TABLE `amazon_orders`
  ADD PRIMARY KEY (`id_amazon_order`);

--
-- Indici per le tabelle `bilancio`
--
ALTER TABLE `bilancio`
  ADD PRIMARY KEY (`id_bilancio`),
  ADD UNIQUE KEY `bilancio_idx1` (`id_utente`,`data_inizio`),
  ADD KEY `bilancio_idx2` (`id_utente`),
  ADD KEY `bilancio_idx3` (`id_utente`,`data_inizio`,`data_fine`);

--
-- Indici per le tabelle `bilancio_carte`
--
ALTER TABLE `bilancio_carte`
  ADD PRIMARY KEY (`id_bilancio`);

--
-- Indici per le tabelle `bilancio_descrizione2id`
--
ALTER TABLE `bilancio_descrizione2id`
  ADD PRIMARY KEY (`id_d2id`),
  ADD KEY `fk_descrizione2id_utente` (`id_utente`),
  ADD KEY `fk_descrizione2id_gruppo` (`id_gruppo_transazione`);

--
-- Indici per le tabelle `bilancio_entrate`
--
ALTER TABLE `bilancio_entrate`
  ADD PRIMARY KEY (`id_entrata`),
  ADD KEY `bilancio_entrate_idx1` (`id_utente`),
  ADD KEY `bilancio_entrate_idx2` (`id_utente`,`data_operazione`),
  ADD KEY `bilancio_entrate_idx3` (`id_utente`,`id_gruppo_transazione`),
  ADD KEY `bilancio_entrate_idx4` (`data_operazione`,`id_gruppo_transazione`,`id_utente`),
  ADD KEY `fk_entrate_metodo` (`id_metodo_pagamento`),
  ADD KEY `idx_entrate_id_tipologia` (`id_tipologia`),
  ADD KEY `idx_entrate_id_gruppo` (`id_gruppo_transazione`);

--
-- Indici per le tabelle `bilancio_etichette`
--
ALTER TABLE `bilancio_etichette`
  ADD PRIMARY KEY (`id_etichetta`);

--
-- Indici per le tabelle `bilancio_etichette2operazioni`
--
ALTER TABLE `bilancio_etichette2operazioni`
  ADD PRIMARY KEY (`id_e2o`),
  ADD KEY `bilancio_etichette2operazioni_idx1` (`id_tabella`),
  ADD KEY `bilancio_etichette2operazioni_idx2` (`tabella_operazione`),
  ADD KEY `bilancio_etichette2operazioni_idx3` (`tabella_operazione`,`id_tabella`),
  ADD KEY `fk_e2o_etichetta` (`id_etichetta`);

--
-- Indici per le tabelle `bilancio_gruppi_categorie`
--
ALTER TABLE `bilancio_gruppi_categorie`
  ADD PRIMARY KEY (`id_categoria`);

--
-- Indici per le tabelle `bilancio_gruppi_transazione`
--
ALTER TABLE `bilancio_gruppi_transazione`
  ADD PRIMARY KEY (`id_gruppo_transazione`),
  ADD KEY `bilancio_gruppi_transazione_fk1` (`id_categoria`);

--
-- Indici per le tabelle `bilancio_metodo_pagamento`
--
ALTER TABLE `bilancio_metodo_pagamento`
  ADD PRIMARY KEY (`id_metodo_pagamento`);

--
-- Indici per le tabelle `bilancio_ricorsivi`
--
ALTER TABLE `bilancio_ricorsivi`
  ADD PRIMARY KEY (`id_ricorsivo`),
  ADD KEY `bilancio_ricorsivi_idx1` (`ogni`),
  ADD KEY `bilancio_ricorsivi_idx2` (`quanto`),
  ADD KEY `bilancio_ricorsivi_idx3` (`ogni`,`quanto`);

--
-- Indici per le tabelle `bilancio_tipologie`
--
ALTER TABLE `bilancio_tipologie`
  ADD PRIMARY KEY (`id_tipologia`);

--
-- Indici per le tabelle `bilancio_uscite`
--
ALTER TABLE `bilancio_uscite`
  ADD PRIMARY KEY (`id_uscita`),
  ADD KEY `bilancio_uscite_idx1` (`id_utente`),
  ADD KEY `bilancio_uscite_idx2` (`id_utente`,`id_gruppo_transazione`),
  ADD KEY `Bilancio_uscite - OCR caricamenti` (`id_caricamento`),
  ADD KEY `bilancio_uscite_idx3` (`id_utente`,`id_gruppo_transazione`,`data_operazione`),
  ADD KEY `fk_uscite_metodo` (`id_metodo_pagamento`),
  ADD KEY `idx_uscite_id_utente` (`id_utente`),
  ADD KEY `idx_uscite_id_tipologia` (`id_tipologia`),
  ADD KEY `idx_uscite_id_gruppo` (`id_gruppo_transazione`);

--
-- Indici per le tabelle `bilancio_utenti2operazioni_etichettate`
--
ALTER TABLE `bilancio_utenti2operazioni_etichettate`
  ADD PRIMARY KEY (`id_u2o`),
  ADD KEY `bilancio_utenti2operazioni_etichettate_idx1` (`id_e2o`),
  ADD KEY `idx_u2o_id_utente` (`id_utente`);

--
-- Indici per le tabelle `budget`
--
ALTER TABLE `budget`
  ADD PRIMARY KEY (`id_budget`),
  ADD KEY `idx_budget_famiglia` (`id_famiglia`),
  ADD KEY `idx_budget_salvadanaio` (`id_salvadanaio`);

--
-- Indici per le tabelle `carte`
--
ALTER TABLE `carte`
  ADD PRIMARY KEY (`id_carta`);

--
-- Indici per le tabelle `codici_2fa`
--
ALTER TABLE `codici_2fa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_utente` (`id_utente`);

--
-- Indici per le tabelle `contabilita`
--
ALTER TABLE `contabilita`
  ADD PRIMARY KEY (`id_contabilita`);

--
-- Indici per le tabelle `dati_remoti`
--
ALTER TABLE `dati_remoti`
  ADD PRIMARY KEY (`id_dato_remoto`),
  ADD UNIQUE KEY `id_dato_remoto` (`id_dato_remoto`);

--
-- Indici per le tabelle `dispositivi_riconosciuti`
--
ALTER TABLE `dispositivi_riconosciuti`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_dispositivo` (`token_dispositivo`),
  ADD KEY `id_utente` (`id_utente`);

--
-- Indici per le tabelle `eventi`
--
ALTER TABLE `eventi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `google_calendar_eventid` (`google_calendar_eventid`);

--
-- Indici per le tabelle `eventi_cibo`
--
ALTER TABLE `eventi_cibo`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `eventi_cibo2ocr_prodotti_spesa`
--
ALTER TABLE `eventi_cibo2ocr_prodotti_spesa`
  ADD PRIMARY KEY (`id_ec2sp`);

--
-- Indici per le tabelle `eventi_eventi2cibo`
--
ALTER TABLE `eventi_eventi2cibo`
  ADD PRIMARY KEY (`id_e2c`);

--
-- Indici per le tabelle `eventi_eventi2famiglie`
--
ALTER TABLE `eventi_eventi2famiglie`
  ADD PRIMARY KEY (`id_e2f`);

--
-- Indici per le tabelle `eventi_eventi2invitati`
--
ALTER TABLE `eventi_eventi2invitati`
  ADD PRIMARY KEY (`id_e2i`),
  ADD KEY `idx_eventi2invitati_id_evento` (`id_evento`),
  ADD KEY `idx_eventi2invitati_id_invitato` (`id_invitato`);

--
-- Indici per le tabelle `eventi_eventi2luogo`
--
ALTER TABLE `eventi_eventi2luogo`
  ADD PRIMARY KEY (`id_e2l`);

--
-- Indici per le tabelle `eventi_eventi2salvadanai_etichette`
--
ALTER TABLE `eventi_eventi2salvadanai_etichette`
  ADD PRIMARY KEY (`id_e2se`),
  ADD KEY `idx_e2se_id_evento` (`id_evento`),
  ADD KEY `idx_e2se_id_salvadanaio` (`id_salvadanaio`),
  ADD KEY `idx_e2se_id_etichetta` (`id_etichetta`);

--
-- Indici per le tabelle `eventi_google_rules`
--
ALTER TABLE `eventi_google_rules`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `eventi_google_rules_invitati`
--
ALTER TABLE `eventi_google_rules_invitati`
  ADD PRIMARY KEY (`id_rule`,`id_invitato`),
  ADD KEY `idx_egri_id_invitato` (`id_invitato`);

--
-- Indici per le tabelle `eventi_invitati`
--
ALTER TABLE `eventi_invitati`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `eventi_invitati2famiglie`
--
ALTER TABLE `eventi_invitati2famiglie`
  ADD PRIMARY KEY (`id_i2f`);

--
-- Indici per le tabelle `eventi_luogo`
--
ALTER TABLE `eventi_luogo`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `eventi_tipi_eventi`
--
ALTER TABLE `eventi_tipi_eventi`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `famiglie`
--
ALTER TABLE `famiglie`
  ADD PRIMARY KEY (`id_famiglia`);

--
-- Indici per le tabelle `gestione_account_password`
--
ALTER TABLE `gestione_account_password`
  ADD PRIMARY KEY (`id_account_password`),
  ADD KEY `fk_password_utente` (`id_utente`),
  ADD KEY `fk_password_famiglia` (`id_famiglia`);

--
-- Indici per le tabelle `importazioni_file`
--
ALTER TABLE `importazioni_file`
  ADD PRIMARY KEY (`id_file`);

--
-- Indici per le tabelle `importazioni_file_colonne`
--
ALTER TABLE `importazioni_file_colonne`
  ADD PRIMARY KEY (`id_colonna`);

--
-- Indici per le tabelle `importazioni_file_file2colonne`
--
ALTER TABLE `importazioni_file_file2colonne`
  ADD PRIMARY KEY (`id_c2f`);

--
-- Indici per le tabelle `lista_spesa`
--
ALTER TABLE `lista_spesa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lista_spesa_famiglia` (`id_famiglia`);

--
-- Indici per le tabelle `menu_smartadmin`
--
ALTER TABLE `menu_smartadmin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_blocco` (`id_blocco`);

--
-- Indici per le tabelle `mezzi`
--
ALTER TABLE `mezzi`
  ADD PRIMARY KEY (`id_mezzo`),
  ADD KEY `fk_mezzi_utente` (`id_utente`),
  ADD KEY `fk_mezzi_famiglia` (`id_famiglia`);

--
-- Indici per le tabelle `mezzi_chilometri`
--
ALTER TABLE `mezzi_chilometri`
  ADD PRIMARY KEY (`id_chilometro`),
  ADD KEY `fk_chilometri_mezzo` (`id_mezzo`);

--
-- Indici per le tabelle `mezzi_mezzi2tagliandi`
--
ALTER TABLE `mezzi_mezzi2tagliandi`
  ADD PRIMARY KEY (`id_m2t`),
  ADD KEY `idx_m2t_id_mezzo` (`id_mezzo`),
  ADD KEY `idx_m2t_id_tagliando` (`id_tagliando`);

--
-- Indici per le tabelle `mezzi_tagliandi`
--
ALTER TABLE `mezzi_tagliandi`
  ADD PRIMARY KEY (`id_tagliando`),
  ADD KEY `fk_tagliandi_mezzo` (`id_mezzo`),
  ADD KEY `fk_tagliandi_famiglia` (`id_famiglia`),
  ADD KEY `fk_tagliandi_utente` (`id_utente`);

--
-- Indici per le tabelle `movimenti2caricamenti`
--
ALTER TABLE `movimenti2caricamenti`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_movimento_revolut` (`id_movimento_revolut`),
  ADD KEY `id_caricamento` (`id_caricamento`);

--
-- Indici per le tabelle `movimenti_hype`
--
ALTER TABLE `movimenti_hype`
  ADD PRIMARY KEY (`id_movimento_hype`);

--
-- Indici per le tabelle `movimenti_poste`
--
ALTER TABLE `movimenti_poste`
  ADD PRIMARY KEY (`id_movimento_posta`),
  ADD UNIQUE KEY `id_movimento_posta` (`id_movimento_posta`);

--
-- Indici per le tabelle `movimenti_poste_categoria`
--
ALTER TABLE `movimenti_poste_categoria`
  ADD PRIMARY KEY (`id_categoria_posta`),
  ADD UNIQUE KEY `id_categoria_posta` (`id_categoria_posta`);

--
-- Indici per le tabelle `movimenti_revolut`
--
ALTER TABLE `movimenti_revolut`
  ADD PRIMARY KEY (`id_movimento_revolut`),
  ADD KEY `idx_revolut_id_gruppo` (`id_gruppo_transazione`),
  ADD KEY `idx_revolut_id_etichetta` (`id_etichetta`),
  ADD KEY `fk_revolut_salvadanaio` (`id_salvadanaio`);

--
-- Indici per le tabelle `movimenti_revolut2salvadanaio_importazione`
--
ALTER TABLE `movimenti_revolut2salvadanaio_importazione`
  ADD PRIMARY KEY (`id_mov2salv`);

--
-- Indici per le tabelle `ocr_caricamenti`
--
ALTER TABLE `ocr_caricamenti`
  ADD PRIMARY KEY (`id_caricamento`);

--
-- Indici per le tabelle `ocr_caricamenti2prodotti_spesa`
--
ALTER TABLE `ocr_caricamenti2prodotti_spesa`
  ADD PRIMARY KEY (`id_ca2pr`);

--
-- Indici per le tabelle `ocr_prodotti2spesa_prodotti`
--
ALTER TABLE `ocr_prodotti2spesa_prodotti`
  ADD PRIMARY KEY (`id_d2id`);

--
-- Indici per le tabelle `ocr_supermercati`
--
ALTER TABLE `ocr_supermercati`
  ADD PRIMARY KEY (`id_supermercato`);

--
-- Indici per le tabelle `quanloop`
--
ALTER TABLE `quanloop`
  ADD PRIMARY KEY (`id_quanloop`),
  ADD UNIQUE KEY `id_quanloop` (`id_quanloop`);

--
-- Indici per le tabelle `reset_password`
--
ALTER TABLE `reset_password`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_utente` (`id_utente`);

--
-- Indici per le tabelle `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indici per le tabelle `salvadanai`
--
ALTER TABLE `salvadanai`
  ADD PRIMARY KEY (`id_salvadanaio`);

--
-- Indici per le tabelle `session`
--
ALTER TABLE `session`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `temi`
--
ALTER TABLE `temi`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `time_dimension`
--
ALTER TABLE `time_dimension`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `td_ymd_idx` (`year`,`month`,`day`),
  ADD UNIQUE KEY `td_dbdate_idx` (`db_date`),
  ADD KEY `time_dimension_idx1` (`day`),
  ADD KEY `time_dimension_idx2` (`day`,`month`);

--
-- Indici per le tabelle `turni_calendario`
--
ALTER TABLE `turni_calendario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_tipo` (`id_tipo`);

--
-- Indici per le tabelle `turni_sync_google_log`
--
ALTER TABLE `turni_sync_google_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_turno` (`id_turno`),
  ADD KEY `idx_evento` (`id_evento`);

--
-- Indici per le tabelle `turni_tipi`
--
ALTER TABLE `turni_tipi`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `unita_misura`
--
ALTER TABLE `unita_misura`
  ADD PRIMARY KEY (`id_unita_misura`);

--
-- Indici per le tabelle `userlevelpermissions`
--
ALTER TABLE `userlevelpermissions`
  ADD PRIMARY KEY (`userlevelid`,`tablename`);

--
-- Indici per le tabelle `userlevels`
--
ALTER TABLE `userlevels`
  ADD PRIMARY KEY (`userlevelid`);

--
-- Indici per le tabelle `userlevel_permissions`
--
ALTER TABLE `userlevel_permissions`
  ADD PRIMARY KEY (`userlevelid`,`resource_id`),
  ADD KEY `fk_ulperm_resources` (`resource_id`);

--
-- Indici per le tabelle `utenti`
--
ALTER TABLE `utenti`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_id_tema` (`id_tema`);

--
-- Indici per le tabelle `utenti2famiglie`
--
ALTER TABLE `utenti2famiglie`
  ADD PRIMARY KEY (`id_u2f`),
  ADD UNIQUE KEY `uq_u2f` (`id_utente`,`id_famiglia`),
  ADD KEY `idx_userlevelid` (`userlevelid`);

--
-- Indici per le tabelle `utenti2ip`
--
ALTER TABLE `utenti2ip`
  ADD PRIMARY KEY (`id_u2i`);

--
-- Indici per le tabelle `utenti2menu_smartadmin`
--
ALTER TABLE `utenti2menu_smartadmin`
  ADD PRIMARY KEY (`id_collut2me`),
  ADD KEY `id_utenti` (`id_utenti`,`id_menu`);

--
-- Indici per le tabelle `utenti2salvadanai`
--
ALTER TABLE `utenti2salvadanai`
  ADD PRIMARY KEY (`id_u2s`),
  ADD UNIQUE KEY `uq_u2s` (`id_utente`,`id_salvadanaio`),
  ADD KEY `fk_u2s_salvadanaio` (`id_salvadanaio`);

--
-- Indici per le tabelle `webauthn_credentials`
--
ALTER TABLE `webauthn_credentials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `credential_id` (`credential_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `amazon_orders`
--
ALTER TABLE `amazon_orders`
  MODIFY `id_amazon_order` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `bilancio`
--
ALTER TABLE `bilancio`
  MODIFY `id_bilancio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `bilancio_carte`
--
ALTER TABLE `bilancio_carte`
  MODIFY `id_bilancio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `bilancio_descrizione2id`
--
ALTER TABLE `bilancio_descrizione2id`
  MODIFY `id_d2id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `bilancio_entrate`
--
ALTER TABLE `bilancio_entrate`
  MODIFY `id_entrata` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `bilancio_etichette`
--
ALTER TABLE `bilancio_etichette`
  MODIFY `id_etichetta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `bilancio_etichette2operazioni`
--
ALTER TABLE `bilancio_etichette2operazioni`
  MODIFY `id_e2o` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `bilancio_gruppi_categorie`
--
ALTER TABLE `bilancio_gruppi_categorie`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `bilancio_gruppi_transazione`
--
ALTER TABLE `bilancio_gruppi_transazione`
  MODIFY `id_gruppo_transazione` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `bilancio_metodo_pagamento`
--
ALTER TABLE `bilancio_metodo_pagamento`
  MODIFY `id_metodo_pagamento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `bilancio_ricorsivi`
--
ALTER TABLE `bilancio_ricorsivi`
  MODIFY `id_ricorsivo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `bilancio_tipologie`
--
ALTER TABLE `bilancio_tipologie`
  MODIFY `id_tipologia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `bilancio_uscite`
--
ALTER TABLE `bilancio_uscite`
  MODIFY `id_uscita` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `bilancio_utenti2operazioni_etichettate`
--
ALTER TABLE `bilancio_utenti2operazioni_etichettate`
  MODIFY `id_u2o` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `budget`
--
ALTER TABLE `budget`
  MODIFY `id_budget` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `carte`
--
ALTER TABLE `carte`
  MODIFY `id_carta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `codici_2fa`
--
ALTER TABLE `codici_2fa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `contabilita`
--
ALTER TABLE `contabilita`
  MODIFY `id_contabilita` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `dati_remoti`
--
ALTER TABLE `dati_remoti`
  MODIFY `id_dato_remoto` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `dispositivi_riconosciuti`
--
ALTER TABLE `dispositivi_riconosciuti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `eventi`
--
ALTER TABLE `eventi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `eventi_cibo`
--
ALTER TABLE `eventi_cibo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `eventi_cibo2ocr_prodotti_spesa`
--
ALTER TABLE `eventi_cibo2ocr_prodotti_spesa`
  MODIFY `id_ec2sp` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `eventi_eventi2cibo`
--
ALTER TABLE `eventi_eventi2cibo`
  MODIFY `id_e2c` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `eventi_eventi2famiglie`
--
ALTER TABLE `eventi_eventi2famiglie`
  MODIFY `id_e2f` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `eventi_eventi2invitati`
--
ALTER TABLE `eventi_eventi2invitati`
  MODIFY `id_e2i` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `eventi_eventi2luogo`
--
ALTER TABLE `eventi_eventi2luogo`
  MODIFY `id_e2l` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `eventi_eventi2salvadanai_etichette`
--
ALTER TABLE `eventi_eventi2salvadanai_etichette`
  MODIFY `id_e2se` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `eventi_google_rules`
--
ALTER TABLE `eventi_google_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `eventi_invitati`
--
ALTER TABLE `eventi_invitati`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `eventi_invitati2famiglie`
--
ALTER TABLE `eventi_invitati2famiglie`
  MODIFY `id_i2f` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `eventi_luogo`
--
ALTER TABLE `eventi_luogo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `eventi_tipi_eventi`
--
ALTER TABLE `eventi_tipi_eventi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `famiglie`
--
ALTER TABLE `famiglie`
  MODIFY `id_famiglia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `gestione_account_password`
--
ALTER TABLE `gestione_account_password`
  MODIFY `id_account_password` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `importazioni_file`
--
ALTER TABLE `importazioni_file`
  MODIFY `id_file` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `importazioni_file_colonne`
--
ALTER TABLE `importazioni_file_colonne`
  MODIFY `id_colonna` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `importazioni_file_file2colonne`
--
ALTER TABLE `importazioni_file_file2colonne`
  MODIFY `id_c2f` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `lista_spesa`
--
ALTER TABLE `lista_spesa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `menu_smartadmin`
--
ALTER TABLE `menu_smartadmin`
  MODIFY `id` int(12) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `mezzi`
--
ALTER TABLE `mezzi`
  MODIFY `id_mezzo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `mezzi_chilometri`
--
ALTER TABLE `mezzi_chilometri`
  MODIFY `id_chilometro` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `mezzi_mezzi2tagliandi`
--
ALTER TABLE `mezzi_mezzi2tagliandi`
  MODIFY `id_m2t` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `mezzi_tagliandi`
--
ALTER TABLE `mezzi_tagliandi`
  MODIFY `id_tagliando` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `movimenti2caricamenti`
--
ALTER TABLE `movimenti2caricamenti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `movimenti_hype`
--
ALTER TABLE `movimenti_hype`
  MODIFY `id_movimento_hype` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `movimenti_poste`
--
ALTER TABLE `movimenti_poste`
  MODIFY `id_movimento_posta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `movimenti_poste_categoria`
--
ALTER TABLE `movimenti_poste_categoria`
  MODIFY `id_categoria_posta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `movimenti_revolut`
--
ALTER TABLE `movimenti_revolut`
  MODIFY `id_movimento_revolut` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `movimenti_revolut2salvadanaio_importazione`
--
ALTER TABLE `movimenti_revolut2salvadanaio_importazione`
  MODIFY `id_mov2salv` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `ocr_caricamenti`
--
ALTER TABLE `ocr_caricamenti`
  MODIFY `id_caricamento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `ocr_caricamenti2prodotti_spesa`
--
ALTER TABLE `ocr_caricamenti2prodotti_spesa`
  MODIFY `id_ca2pr` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `ocr_prodotti2spesa_prodotti`
--
ALTER TABLE `ocr_prodotti2spesa_prodotti`
  MODIFY `id_d2id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `ocr_supermercati`
--
ALTER TABLE `ocr_supermercati`
  MODIFY `id_supermercato` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `quanloop`
--
ALTER TABLE `quanloop`
  MODIFY `id_quanloop` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `reset_password`
--
ALTER TABLE `reset_password`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `resources`
--
ALTER TABLE `resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `salvadanai`
--
ALTER TABLE `salvadanai`
  MODIFY `id_salvadanaio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `session`
--
ALTER TABLE `session`
  MODIFY `id` int(12) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `temi`
--
ALTER TABLE `temi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `turni_calendario`
--
ALTER TABLE `turni_calendario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `turni_sync_google_log`
--
ALTER TABLE `turni_sync_google_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `turni_tipi`
--
ALTER TABLE `turni_tipi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `unita_misura`
--
ALTER TABLE `unita_misura`
  MODIFY `id_unita_misura` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `utenti`
--
ALTER TABLE `utenti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `utenti2famiglie`
--
ALTER TABLE `utenti2famiglie`
  MODIFY `id_u2f` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `utenti2ip`
--
ALTER TABLE `utenti2ip`
  MODIFY `id_u2i` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `utenti2menu_smartadmin`
--
ALTER TABLE `utenti2menu_smartadmin`
  MODIFY `id_collut2me` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `utenti2salvadanai`
--
ALTER TABLE `utenti2salvadanai`
  MODIFY `id_u2s` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `webauthn_credentials`
--
ALTER TABLE `webauthn_credentials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `bilancio`
--
ALTER TABLE `bilancio`
  ADD CONSTRAINT `fk_bilancio_utente` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`);

--
-- Limiti per la tabella `bilancio_descrizione2id`
--
ALTER TABLE `bilancio_descrizione2id`
  ADD CONSTRAINT `fk_descrizione2id_gruppo` FOREIGN KEY (`id_gruppo_transazione`) REFERENCES `bilancio_gruppi_transazione` (`id_gruppo_transazione`),
  ADD CONSTRAINT `fk_descrizione2id_utente` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`);

--
-- Limiti per la tabella `bilancio_entrate`
--
ALTER TABLE `bilancio_entrate`
  ADD CONSTRAINT `fk_entrate_gruppo` FOREIGN KEY (`id_gruppo_transazione`) REFERENCES `bilancio_gruppi_transazione` (`id_gruppo_transazione`),
  ADD CONSTRAINT `fk_entrate_metodo` FOREIGN KEY (`id_metodo_pagamento`) REFERENCES `bilancio_metodo_pagamento` (`id_metodo_pagamento`),
  ADD CONSTRAINT `fk_entrate_tipologia` FOREIGN KEY (`id_tipologia`) REFERENCES `bilancio_tipologie` (`id_tipologia`),
  ADD CONSTRAINT `fk_entrate_utente` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`);

--
-- Limiti per la tabella `bilancio_etichette2operazioni`
--
ALTER TABLE `bilancio_etichette2operazioni`
  ADD CONSTRAINT `fk_e2o_etichetta` FOREIGN KEY (`id_etichetta`) REFERENCES `bilancio_etichette` (`id_etichetta`) ON DELETE CASCADE;

--
-- Limiti per la tabella `bilancio_gruppi_transazione`
--
ALTER TABLE `bilancio_gruppi_transazione`
  ADD CONSTRAINT `bilancio_gruppi_transazione_fk1` FOREIGN KEY (`id_categoria`) REFERENCES `bilancio_gruppi_categorie` (`id_categoria`);

--
-- Limiti per la tabella `bilancio_uscite`
--
ALTER TABLE `bilancio_uscite`
  ADD CONSTRAINT `Bilancio_uscite - OCR caricamenti` FOREIGN KEY (`id_caricamento`) REFERENCES `ocr_caricamenti` (`id_caricamento`),
  ADD CONSTRAINT `fk_uscite_gruppo` FOREIGN KEY (`id_gruppo_transazione`) REFERENCES `bilancio_gruppi_transazione` (`id_gruppo_transazione`),
  ADD CONSTRAINT `fk_uscite_metodo` FOREIGN KEY (`id_metodo_pagamento`) REFERENCES `bilancio_metodo_pagamento` (`id_metodo_pagamento`),
  ADD CONSTRAINT `fk_uscite_tipologia` FOREIGN KEY (`id_tipologia`) REFERENCES `bilancio_tipologie` (`id_tipologia`),
  ADD CONSTRAINT `fk_uscite_utente` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`);

--
-- Limiti per la tabella `bilancio_utenti2operazioni_etichettate`
--
ALTER TABLE `bilancio_utenti2operazioni_etichettate`
  ADD CONSTRAINT `fk_u2o_e2o` FOREIGN KEY (`id_e2o`) REFERENCES `bilancio_etichette2operazioni` (`id_e2o`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_u2o_utente` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `budget`
--
ALTER TABLE `budget`
  ADD CONSTRAINT `budget_ibfk_1` FOREIGN KEY (`id_famiglia`) REFERENCES `famiglie` (`id_famiglia`),
  ADD CONSTRAINT `budget_ibfk_2` FOREIGN KEY (`id_salvadanaio`) REFERENCES `salvadanai` (`id_salvadanaio`);

--
-- Limiti per la tabella `eventi_eventi2invitati`
--
ALTER TABLE `eventi_eventi2invitati`
  ADD CONSTRAINT `fk_eventi2invitati_evento` FOREIGN KEY (`id_evento`) REFERENCES `eventi` (`id`),
  ADD CONSTRAINT `fk_eventi2invitati_invitato` FOREIGN KEY (`id_invitato`) REFERENCES `eventi_invitati` (`id`);

--
-- Limiti per la tabella `eventi_eventi2salvadanai_etichette`
--
ALTER TABLE `eventi_eventi2salvadanai_etichette`
  ADD CONSTRAINT `fk_e2se_etichetta` FOREIGN KEY (`id_etichetta`) REFERENCES `bilancio_etichette` (`id_etichetta`),
  ADD CONSTRAINT `fk_e2se_evento` FOREIGN KEY (`id_evento`) REFERENCES `eventi` (`id`),
  ADD CONSTRAINT `fk_e2se_salvadanaio` FOREIGN KEY (`id_salvadanaio`) REFERENCES `salvadanai` (`id_salvadanaio`);

--
-- Limiti per la tabella `gestione_account_password`
--
ALTER TABLE `gestione_account_password`
  ADD CONSTRAINT `fk_password_famiglia` FOREIGN KEY (`id_famiglia`) REFERENCES `famiglie` (`id_famiglia`),
  ADD CONSTRAINT `fk_password_utente` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`);

--
-- Limiti per la tabella `lista_spesa`
--
ALTER TABLE `lista_spesa`
  ADD CONSTRAINT `fk_lista_spesa_famiglie` FOREIGN KEY (`id_famiglia`) REFERENCES `famiglie` (`id_famiglia`) ON DELETE CASCADE;

--
-- Limiti per la tabella `mezzi`
--
ALTER TABLE `mezzi`
  ADD CONSTRAINT `fk_mezzi_famiglia` FOREIGN KEY (`id_famiglia`) REFERENCES `famiglie` (`id_famiglia`),
  ADD CONSTRAINT `fk_mezzi_utente` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`);

--
-- Limiti per la tabella `mezzi_chilometri`
--
ALTER TABLE `mezzi_chilometri`
  ADD CONSTRAINT `fk_chilometri_mezzo` FOREIGN KEY (`id_mezzo`) REFERENCES `mezzi` (`id_mezzo`);

--
-- Limiti per la tabella `mezzi_mezzi2tagliandi`
--
ALTER TABLE `mezzi_mezzi2tagliandi`
  ADD CONSTRAINT `fk_m2t_mezzo` FOREIGN KEY (`id_mezzo`) REFERENCES `mezzi` (`id_mezzo`),
  ADD CONSTRAINT `fk_m2t_tagliando` FOREIGN KEY (`id_tagliando`) REFERENCES `mezzi_tagliandi` (`id_tagliando`);

--
-- Limiti per la tabella `mezzi_tagliandi`
--
ALTER TABLE `mezzi_tagliandi`
  ADD CONSTRAINT `fk_tagliandi_famiglia` FOREIGN KEY (`id_famiglia`) REFERENCES `famiglie` (`id_famiglia`),
  ADD CONSTRAINT `fk_tagliandi_mezzo` FOREIGN KEY (`id_mezzo`) REFERENCES `mezzi` (`id_mezzo`),
  ADD CONSTRAINT `fk_tagliandi_utente` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`);

--
-- Limiti per la tabella `movimenti_revolut`
--
ALTER TABLE `movimenti_revolut`
  ADD CONSTRAINT `fk_revolut_etichetta` FOREIGN KEY (`id_etichetta`) REFERENCES `bilancio_etichette` (`id_etichetta`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_revolut_gruppo` FOREIGN KEY (`id_gruppo_transazione`) REFERENCES `bilancio_gruppi_transazione` (`id_gruppo_transazione`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_revolut_salvadanaio` FOREIGN KEY (`id_salvadanaio`) REFERENCES `salvadanai` (`id_salvadanaio`) ON DELETE SET NULL;

--
-- Limiti per la tabella `userlevel_permissions`
--
ALTER TABLE `userlevel_permissions`
  ADD CONSTRAINT `fk_ulperm_resources` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ulperm_userlevels` FOREIGN KEY (`userlevelid`) REFERENCES `userlevels` (`userlevelid`) ON DELETE CASCADE;

--
-- Limiti per la tabella `utenti`
--
ALTER TABLE `utenti`
  ADD CONSTRAINT `fk_utenti_temi` FOREIGN KEY (`id_tema`) REFERENCES `temi` (`id`);

--
-- Limiti per la tabella `utenti2salvadanai`
--
ALTER TABLE `utenti2salvadanai`
  ADD CONSTRAINT `fk_u2s_salvadanaio` FOREIGN KEY (`id_salvadanaio`) REFERENCES `salvadanai` (`id_salvadanaio`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_u2s_utente` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
