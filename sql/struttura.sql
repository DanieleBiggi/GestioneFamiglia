-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 89.46.111.63:3306
-- Creato il: Nov 04, 2026 alle 14:46
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
  `nome_tipologia` varchar(100) DEFAULT NULL,
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
-- Struttura della tabella `film`
--

CREATE TABLE `film` (
  `id_film` int(11) NOT NULL,
  `tmdb_id` int(11) NOT NULL,
  `titolo` varchar(255) NOT NULL,
  `titolo_originale` varchar(255) DEFAULT NULL,
  `anno` year(4) DEFAULT NULL,
  `durata` int(11) DEFAULT NULL,
  `voto_medio` decimal(11,5) DEFAULT NULL,
  `regista` varchar(255) DEFAULT NULL,
  `trama` text,
  `poster_url` varchar(255) DEFAULT NULL,
  `lingua_originale` varchar(10) DEFAULT NULL,
  `id_gruppo` int(11) DEFAULT NULL,
  `inserito_il` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aggiornato_il` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `budget` bigint(20) DEFAULT NULL,
  `incassi` bigint(20) DEFAULT NULL,
  `trailer_ita_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `film2generi`
--

CREATE TABLE `film2generi` (
  `id_film` int(11) NOT NULL,
  `id_genere` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `film2liste`
--

CREATE TABLE `film2liste` (
  `id_film` int(11) NOT NULL,
  `id_lista` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `film2piattaforme`
--

CREATE TABLE `film2piattaforme` (
  `id_film` int(11) NOT NULL,
  `id_piattaforma` int(11) NOT NULL,
  `indicata_il` date NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `film_commenti`
--

CREATE TABLE `film_commenti` (
  `id` int(11) NOT NULL,
  `id_film` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `commento` text NOT NULL,
  `inserito_il` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `film_generi`
--

CREATE TABLE `film_generi` (
  `id_genere` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `film_gruppi`
--

CREATE TABLE `film_gruppi` (
  `id_gruppo` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `inserito_il` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aggiornato_il` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `film_liste`
--

CREATE TABLE `film_liste` (
  `id_lista` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `inserito_il` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aggiornato_il` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `film_utenti`
--

CREATE TABLE `film_utenti` (
  `id` int(11) NOT NULL,
  `id_film` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `data_visto` date DEFAULT NULL,
  `voto` decimal(3,1) DEFAULT NULL,
  `inserito_il` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aggiornato_il` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
  `attiva` int(11) NOT NULL DEFAULT '1',
  `note` text
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
  `quantita` varchar(100) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `checked` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `menu_cene_settimanale`
--

CREATE TABLE `menu_cene_settimanale` (
  `id` int(11) NOT NULL,
  `id_famiglia` int(11) NOT NULL,
  `week_start` date NOT NULL,
  `giorno` varchar(20) NOT NULL,
  `piatto` text
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
-- Struttura della tabella `mezzi_eventi`
--

CREATE TABLE `mezzi_eventi` (
  `id_evento` int(11) NOT NULL,
  `id_mezzo` int(11) NOT NULL,
  `id_tipo_evento` int(11) NOT NULL,
  `data_evento` date NOT NULL,
  `km_evento` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `mezzi_eventi_tipi`
--

CREATE TABLE `mezzi_eventi_tipi` (
  `id_tipo_evento` int(11) NOT NULL,
  `tipo_evento` varchar(100) NOT NULL,
  `attivo` int(11) NOT NULL DEFAULT '1'
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
-- Struttura della tabella `streaming_piattaforme`
--

CREATE TABLE `streaming_piattaforme` (
  `id_piattaforma` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `icon` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

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
-- Struttura della tabella `viaggi`
--

CREATE TABLE `viaggi` (
  `id_viaggio` int(11) NOT NULL,
  `titolo` varchar(150) NOT NULL,
  `id_luogo` int(11) DEFAULT NULL,
  `data_inizio` date DEFAULT NULL,
  `data_fine` date DEFAULT NULL,
  `notti` int(11) DEFAULT NULL,
  `persone` int(11) DEFAULT NULL,
  `id_foto` int(11) DEFAULT NULL,
  `breve_descrizione` varchar(250) DEFAULT NULL,
  `stato` enum('idea','shortlist','pianificato','prenotato','fatto','scartato') DEFAULT 'idea',
  `priorita` tinyint(4) DEFAULT NULL,
  `visibilita` enum('private','shared','public') DEFAULT 'private',
  `token_condivisione` char(22) DEFAULT NULL,
  `note` text,
  `meteo_previsto_json` text,
  `meteo_aggiornato_il` datetime DEFAULT NULL,
  `creato_il` datetime DEFAULT CURRENT_TIMESTAMP,
  `aggiornato_il` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi2caricamenti`
--

CREATE TABLE `viaggi2caricamenti` (
  `id_viaggio` int(11) NOT NULL,
  `id_caricamento` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi2etichette`
--

CREATE TABLE `viaggi2etichette` (
  `id_viaggio` int(11) NOT NULL,
  `id_etichetta` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_alloggi`
--

CREATE TABLE `viaggi_alloggi` (
  `id_alloggio` int(11) NOT NULL,
  `id_viaggio` int(11) NOT NULL,
  `giorno_indice` int(11) DEFAULT NULL,
  `nome_alloggio` varchar(255) DEFAULT NULL,
  `indirizzo` varchar(255) DEFAULT NULL,
  `lat` decimal(9,6) DEFAULT NULL,
  `lng` decimal(9,6) DEFAULT NULL,
  `data_checkin` date DEFAULT NULL,
  `data_checkout` date DEFAULT NULL,
  `costo_notte_eur` decimal(10,2) DEFAULT NULL,
  `note` text,
  `pagato` tinyint(1) NOT NULL DEFAULT '0',
  `id_viaggio_alternativa` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_alloggi_alternative`
--

CREATE TABLE `viaggi_alloggi_alternative` (
  `id_alloggio` int(11) NOT NULL,
  `id_viaggio_alternativa` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_alternative`
--

CREATE TABLE `viaggi_alternative` (
  `id_viaggio_alternativa` int(11) NOT NULL,
  `id_viaggio` int(11) NOT NULL,
  `breve_descrizione` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_altri_costi`
--

CREATE TABLE `viaggi_altri_costi` (
  `id_costo` int(11) NOT NULL,
  `id_viaggio` int(11) NOT NULL,
  `id_viaggio_alternativa` int(11) DEFAULT NULL,
  `data` date DEFAULT NULL,
  `importo_eur` decimal(10,2) DEFAULT NULL,
  `descrizione` varchar(255) DEFAULT NULL,
  `note` text,
  `pagato` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_altri_costi_alternative`
--

CREATE TABLE `viaggi_altri_costi_alternative` (
  `id_costo` int(11) NOT NULL,
  `id_viaggio_alternativa` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_checklist`
--

CREATE TABLE `viaggi_checklist` (
  `id_checklist` int(11) NOT NULL,
  `id_viaggio` int(11) NOT NULL,
  `voce` varchar(255) NOT NULL,
  `completata` tinyint(1) DEFAULT '0',
  `id_utente` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_checklist_messaggi`
--

CREATE TABLE `viaggi_checklist_messaggi` (
  `id_messaggio` int(11) NOT NULL,
  `id_checklist` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `messaggio` text NOT NULL,
  `creato_il` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_commenti`
--

CREATE TABLE `viaggi_commenti` (
  `id_commento` bigint(20) UNSIGNED NOT NULL,
  `id_viaggio` int(11) NOT NULL,
  `id_viaggio_alternativa` int(11) DEFAULT NULL,
  `id_utente` int(11) NOT NULL,
  `visibilita` enum('pubblico','interno') NOT NULL DEFAULT 'interno',
  `testo` text NOT NULL,
  `creato_il` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modificato_il` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_condivisioni`
--

CREATE TABLE `viaggi_condivisioni` (
  `id_condivisione` bigint(20) UNSIGNED NOT NULL,
  `id_viaggio` int(11) NOT NULL,
  `token_sha256` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `creato_da` int(11) NOT NULL,
  `creato_il` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `scade_il` datetime NOT NULL,
  `revocato_il` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_condivisioni_alternative`
--

CREATE TABLE `viaggi_condivisioni_alternative` (
  `id_condivisione` bigint(20) UNSIGNED NOT NULL,
  `id_viaggio_alternativa` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_etichette`
--

CREATE TABLE `viaggi_etichette` (
  `id_etichetta` int(11) NOT NULL,
  `nome` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_feedback`
--

CREATE TABLE `viaggi_feedback` (
  `id_feedback` int(11) NOT NULL,
  `id_viaggio` int(11) NOT NULL,
  `id_utente` int(11) DEFAULT NULL,
  `voto` tinyint(4) DEFAULT NULL,
  `commento` text,
  `creato_il` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_luoghi`
--

CREATE TABLE `viaggi_luoghi` (
  `id_luogo` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `citta` varchar(100) DEFAULT NULL,
  `regione` varchar(100) DEFAULT NULL,
  `paese` varchar(100) DEFAULT NULL,
  `lat` decimal(9,6) DEFAULT NULL,
  `lng` decimal(9,6) DEFAULT NULL,
  `url_maps` varchar(255) DEFAULT NULL,
  `sito_web` varchar(255) DEFAULT NULL,
  `note_apertura` text,
  `note` text,
  `creato_il` datetime DEFAULT CURRENT_TIMESTAMP,
  `aggiornato_il` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `place_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_luogo_foto`
--

CREATE TABLE `viaggi_luogo_foto` (
  `id_foto` int(11) NOT NULL,
  `id_luogo` int(11) NOT NULL,
  `photo_reference` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_pasti`
--

CREATE TABLE `viaggi_pasti` (
  `id_pasto` int(11) NOT NULL,
  `id_viaggio` int(11) NOT NULL,
  `id_viaggio_alternativa` int(11) DEFAULT NULL,
  `giorno_indice` int(11) DEFAULT NULL,
  `tipo_pasto` enum('colazione','pranzo','cena') NOT NULL,
  `nome_locale` varchar(255) DEFAULT NULL,
  `indirizzo` varchar(255) DEFAULT NULL,
  `lat` decimal(9,6) DEFAULT NULL,
  `lng` decimal(9,6) DEFAULT NULL,
  `tipologia` enum('ristorante','pizzeria','cucinato') DEFAULT 'ristorante',
  `costo_medio_eur` decimal(10,2) DEFAULT NULL,
  `note` text,
  `pagato` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_pasti_alternative`
--

CREATE TABLE `viaggi_pasti_alternative` (
  `id_pasto` int(11) NOT NULL,
  `id_viaggio_alternativa` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_tratte`
--

CREATE TABLE `viaggi_tratte` (
  `id_tratta` int(11) NOT NULL,
  `id_viaggio` int(11) NOT NULL,
  `id_viaggio_alternativa` int(11) DEFAULT NULL,
  `tipo_tratta` enum('auto','aereo','traghetto','treno') NOT NULL,
  `descrizione` text,
  `origine_testo` varchar(255) DEFAULT NULL,
  `origine_lat` decimal(9,6) DEFAULT NULL,
  `origine_lng` decimal(9,6) DEFAULT NULL,
  `destinazione_testo` varchar(255) DEFAULT NULL,
  `destinazione_lat` decimal(9,6) DEFAULT NULL,
  `destinazione_lng` decimal(9,6) DEFAULT NULL,
  `distanza_km` decimal(8,2) DEFAULT NULL,
  `durata_ore` decimal(5,2) DEFAULT NULL,
  `consumo_litri_100km` decimal(5,2) DEFAULT NULL,
  `prezzo_carburante_eur_litro` decimal(5,3) DEFAULT NULL,
  `pedaggi_eur` decimal(10,2) DEFAULT NULL,
  `costo_traghetto_eur` decimal(10,2) DEFAULT NULL,
  `costo_volo_eur` decimal(10,2) DEFAULT NULL,
  `costo_noleggio_eur` decimal(10,2) DEFAULT NULL,
  `altri_costi_eur` decimal(10,2) DEFAULT NULL,
  `note` text,
  `pagato` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura della tabella `viaggi_tratte_alternative`
--

CREATE TABLE `viaggi_tratte_alternative` (
  `id_tratta` int(11) NOT NULL,
  `id_viaggio_alternativa` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_aggiornamento_bilancio`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_aggiornamento_bilancio` (
`id_uscita` int(11)
,`id_utente` int(11)
,`mezzo` varchar(8)
,`id_tipologia` int(11)
,`id_gruppo_transazione` int(11)
,`id_metodo_pagamento` int(11)
,`descrizione_operazione` longtext
,`importo` decimal(54,2)
,`data_operazione` datetime
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_amazon_orders`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_amazon_orders` (
`id_amazon_order` int(11)
,`id_movimento_revolut` int(11)
,`id_uscita` int(11)
,`order_id` varchar(255)
,`order_url` varchar(255)
,`items` mediumtext
,`recipient` varchar(255)
,`order_date` date
,`total_amount` decimal(11,5)
,`shipping_cost` decimal(11,5)
,`shipping_refund` decimal(11,5)
,`gift` decimal(11,5)
,`vat_amount` decimal(11,5)
,`refund_amount` decimal(11,5)
,`payments` varchar(255)
,`descrizione_extra` varchar(250)
,`importo` decimal(35,5)
,`data_operazione` datetime
,`completato` bigint(11)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_bilancio_etichette2operazioni`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_bilancio_etichette2operazioni` (
`id_e2o` int(11)
,`id_etichetta` int(11)
,`tabella_operazione` varchar(50)
,`id_tabella` int(11)
,`descrizione_extra` varchar(250)
,`dividere_rimborsare` varchar(20)
,`allegato` varchar(100)
,`id_utente` bigint(11)
,`descrizione_operazione` longtext
,`importo` decimal(35,5)
,`data_operazione` datetime
,`n_utenti` bigint(21)
,`quote_totali` decimal(33,5)
,`operazioni_saldate` decimal(32,0)
,`senza_utenti` int(0)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_bilancio_etichette2operazioni_a_testa`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_bilancio_etichette2operazioni_a_testa` (
`id_utente_operazione` bigint(11)
,`descrizione_operazione` longtext
,`data_operazione` datetime
,`id_e2o` int(11)
,`id_etichetta` int(11)
,`descrizione_extra` varchar(250)
,`allegato` varchar(100)
,`descrizione` varchar(200)
,`importo_totale_operazione` decimal(35,5)
,`importo_etichetta` decimal(11,2)
,`importo` decimal(44,9)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_bilancio_etichette2operazioni_a_testa_OLD20250414`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_bilancio_etichette2operazioni_a_testa_OLD20250414` (
`id_utente_operazione` bigint(11)
,`descrizione_operazione` longtext
,`data_operazione` datetime
,`id_e2o` int(11)
,`id_etichetta` int(11)
,`descrizione_extra` varchar(250)
,`allegato` varchar(100)
,`descrizione` varchar(200)
,`importo_totale_operazione` decimal(32,2)
,`importo_etichetta` decimal(11,2)
,`importo` decimal(41,6)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_bilancio_etichette2operazioni_revolut`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_bilancio_etichette2operazioni_revolut` (
`id_e2o` int(11)
,`id_etichetta` int(11)
,`tabella_operazione` varchar(50)
,`id_tabella` int(11)
,`descrizione_extra` varchar(250)
,`importo` decimal(11,2)
,`dividere_rimborsare` varchar(20)
,`allegato` varchar(100)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_bilancio_etichette2operazioni_uscite`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_bilancio_etichette2operazioni_uscite` (
`id_e2o` int(11)
,`id_etichetta` int(11)
,`id_tabella` int(11)
,`descrizione_extra` varchar(250)
,`importo` decimal(11,2)
,`dividere_rimborsare` varchar(20)
,`allegato` varchar(100)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_bilancio_etichette_da_dividere`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_bilancio_etichette_da_dividere` (
`descrizione` varchar(200)
,`id_etichetta` int(11)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_bilancio_gruppo_mese`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_bilancio_gruppo_mese` (
`id_utente` int(11)
,`descrizione` varchar(100)
,`attivo` int(11)
,`id_gruppo_transazione` int(11)
,`totale_speso` decimal(54,2)
,`totale_entrato` decimal(54,2)
,`ultima_operazione` datetime
,`anno` bigint(20)
,`mese` bigint(20)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_bilancio_ricorsivi_data`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_bilancio_ricorsivi_data` (
`id_ricorsivo` int(11)
,`descrizione` varchar(100)
,`ogni` enum('anno','mese','settimana','giorno','data_precisa')
,`quanto` varchar(20)
,`importo` decimal(11,2)
,`attivo` int(11)
,`id_utente` int(11)
,`descrizione_operazione` varchar(100)
,`id_gruppo_transazione` int(11)
,`condivise` int(11)
,`db_date` date
,`year` int(11)
,`month` int(11)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_bilancio_ricorsivi_mese`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_bilancio_ricorsivi_mese` (
`id_ricorsivo` int(11)
,`id_utente` int(11)
,`descrizione` varchar(100)
,`ogni` enum('anno','mese','settimana','giorno','data_precisa')
,`quanto` varchar(20)
,`importo` decimal(11,2)
,`descrizione_operazione` varchar(100)
,`id_gruppo_transazione` int(11)
,`da_mostrare_nel_mese_corrente` int(1)
,`month` int(11)
,`year` int(11)
,`condivise` int(11)
,`db_date` date
,`entrate_mese_previste` decimal(11,2)
,`uscite_mese_previste` decimal(11,2)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_bilancio_ricorsivi_ultima_operazione`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_bilancio_ricorsivi_ultima_operazione` (
`id_ricorsivo` int(11)
,`id_utente` int(11)
,`descrizione` varchar(100)
,`attivo` int(11)
,`ogni` enum('anno','mese','settimana','giorno','data_precisa')
,`quanto` varchar(20)
,`importo` decimal(11,2)
,`descrizione_operazione` varchar(100)
,`id_gruppo_transazione` int(11)
,`data_operazione` datetime
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_bilancio_utenti2operazioni_etichettate`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_bilancio_utenti2operazioni_etichettate` (
`id_u2o` int(11)
,`id_utente` int(11)
,`id_e2o` int(11)
,`saldata` int(11)
,`id_etichetta` int(11)
,`descrizione_operazione` longtext
,`data_operazione` datetime
,`importo` decimal(55,14)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_bilancio_utenti2operazioni_etichettate_utente`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_bilancio_utenti2operazioni_etichettate_utente` (
`id_u2o` int(11)
,`id_utente` int(11)
,`id_e2o` int(11)
,`saldata` int(11)
,`data_saldo` date
,`allegato` varchar(100)
,`id_etichetta` int(11)
,`descrizione` varchar(200)
,`id_utente_operazione` bigint(11)
,`descrizione_operazione` mediumtext
,`descrizione_extra` varchar(250)
,`data_operazione` datetime
,`importo` decimal(57,14)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_bilancio_utenti2operazioni_etichettate_utente_old20250414`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_bilancio_utenti2operazioni_etichettate_utente_old20250414` (
`id_u2o` int(11)
,`id_utente` int(11)
,`id_e2o` int(11)
,`saldata` int(11)
,`data_saldo` date
,`allegato` varchar(100)
,`id_etichetta` int(11)
,`descrizione` varchar(200)
,`id_utente_operazione` bigint(11)
,`descrizione_operazione` mediumtext
,`descrizione_extra` varchar(250)
,`data_operazione` datetime
,`importo` decimal(57,14)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_colonne_importa_movimenti`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_colonne_importa_movimenti` (
`colonna_db` varchar(64)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_elenco_operazioni`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_elenco_operazioni` (
`id_entrata_uscita` int(11)
,`tabella_operazione` varchar(16)
,`id_utente` int(11)
,`mezzo` varchar(8)
,`id_tipologia` int(11)
,`id_gruppo_transazione` int(11)
,`da_inserire_gruppo` bigint(20)
,`id_metodo_pagamento` int(11)
,`descrizione_extra` varchar(250)
,`descrizione_operazione` longtext
,`importo` decimal(32,2)
,`data_operazione` datetime
,`numero_etichette` bigint(21)
,`da_sistemare` bigint(20)
,`colonna_ignora` varchar(26)
,`colonna_ignora2` varchar(26)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_etichette2operazioni`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_etichette2operazioni` (
`id_e2o` int(11)
,`id_etichetta` int(11)
,`descrizione` varchar(200)
,`tabella_operazione` varchar(50)
,`id_tabella` int(11)
,`importo` decimal(11,2)
,`attivo` int(11)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_eventi`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_eventi` (
`id` int(11)
,`titolo` varchar(100)
,`data_evento` date
,`ora_evento` varchar(10)
,`descrizione` varchar(100)
,`id_tipo_evento` int(11)
,`icon` varchar(50)
,`note` mediumtext
,`partecipano` decimal(23,0)
,`non_partecipano` decimal(23,0)
,`in_forse` decimal(23,0)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_eventi_eventi2invitati`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_eventi_eventi2invitati` (
`id_evento` int(11)
,`id_invitato` int(11)
,`nome` varchar(50)
,`cognome` varchar(50)
,`partecipa` int(11)
,`id_utente` int(11)
,`fullname` varchar(101)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_eventi_viaggi`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_eventi_viaggi` (
`titolo` varchar(150)
,`data_inizio` date
,`data_fine` date
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_importa_movimenti`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_importa_movimenti` (
`id_entrata` int(11)
,`id_utente` int(11)
,`mezzo` enum('banca','carte','contanti')
,`id_tipologia` int(11)
,`id_gruppo_transazione` int(11)
,`id_metodo_pagamento` int(11)
,`descrizione_operazione` longtext
,`importo` decimal(32,2)
,`data_operazione` datetime
,`colonna_ignora` varchar(26)
,`colonna_ignora2` varchar(26)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_mensili_entrate`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_mensili_entrate` (
`id_utente` int(11)
,`descrizione` varchar(100)
,`attivo` int(11)
,`id_gruppo_transazione` int(11)
,`totale_speso` int(1)
,`totale_entrato` decimal(54,2)
,`ultima_operazione` datetime
,`anno` int(4)
,`mese` int(2)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_mensili_riepilogo`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_mensili_riepilogo` (
`id_utente` int(11)
,`descrizione` varchar(100)
,`attivo` int(11)
,`id_gruppo_transazione` int(11)
,`totale_speso` decimal(54,2)
,`totale_entrato` decimal(54,2)
,`differenza` decimal(55,2)
,`ultima_operazione` datetime
,`anno` int(4)
,`mese` int(2)
,`data_anno_mese` date
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_mensili_spese`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_mensili_spese` (
`id_utente` int(11)
,`descrizione` varchar(100)
,`attivo` int(11)
,`id_gruppo_transazione` int(11)
,`totale_speso` decimal(54,2)
,`totale_entrato` int(1)
,`ultima_operazione` datetime
,`anno` int(4)
,`mese` int(2)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_movimenti_generali`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_movimenti_generali` (
`tipo` varchar(7)
,`id_gruppo_transazione` int(11)
,`mezzo` varchar(8)
,`descrizione_operazione` longtext
,`descrizione_extra` varchar(250)
,`importo` decimal(35,5)
,`data_operazione` datetime
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_movimenti_generali_anno_mese`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_movimenti_generali_anno_mese` (
`tipo` varchar(7)
,`id_gruppo_transazione` int(11)
,`gruppo` varchar(100)
,`mezzo` varchar(8)
,`descrizione_operazione` longtext
,`descrizione_extra` varchar(250)
,`importo` decimal(35,5)
,`anno` bigint(20)
,`mese` bigint(20)
,`data_operazione` datetime
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_movimenti_hype`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_movimenti_hype` (
`id_movimento_hype` int(11)
,`id_gruppo_transazione` int(11)
,`descrizione_gruppo` varchar(100)
,`data_operazione` date
,`data_contabile` date
,`tipologia` varchar(50)
,`nome` varchar(100)
,`descrizione` mediumtext
,`importo` decimal(10,2)
,`id_etichetta` int(11)
,`etichette` text
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_movimenti_revolut`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_movimenti_revolut` (
`id_movimento_revolut` int(11)
,`id_caricamento` int(11)
,`id_gruppo_transazione` int(11)
,`descrizione_gruppo` varchar(100)
,`id_categoria` int(11)
,`id_salvadanaio` int(11)
,`type` varchar(100)
,`product` varchar(100)
,`started_date` datetime
,`completed_date` datetime
,`description` varchar(250)
,`amount` decimal(11,5)
,`note` mediumtext
,`descrizione_extra` varchar(250)
,`tabella_operazione` varchar(20)
,`id_etichetta` int(11)
,`etichette` text
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_movimenti_revolut_filtrati`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_movimenti_revolut_filtrati` (
`id_movimento_revolut` int(11)
,`id_gruppo_transazione` int(11)
,`id_salvadanaio` int(11)
,`type` varchar(100)
,`product` varchar(100)
,`started_date` datetime
,`completed_date` datetime
,`description` varchar(250)
,`amount` decimal(11,5)
,`note` mediumtext
,`descrizione_extra` varchar(250)
,`tabella_operazione` varchar(20)
,`id_etichetta` int(11)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_ocr_caricamenti`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_ocr_caricamenti` (
`id_caricamento` int(11)
,`id_utente` int(11)
,`utente` varchar(201)
,`id_supermercato` int(11)
,`data_caricamento` datetime
,`nome_file` varchar(200)
,`data_scontrino` datetime
,`totale_scontrino` decimal(11,2)
,`indirizzo_ip` varchar(20)
,`JSON_linee` mediumtext
,`descrizione` varchar(200)
,`buoni_utilizzati` decimal(16,0)
,`caricato` int(0)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `v_ocr_caricamenti_ricerca`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_ocr_caricamenti_ricerca` (
`id_caricamento` int(11)
,`id_utente` int(11)
,`fullname` varchar(211)
,`data_caricamento` datetime
,`nome_file` varchar(200)
,`descrizione` varchar(200)
,`caricato` int(0)
);

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
-- Struttura stand-in per le viste `v_totali_alternative`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `v_totali_alternative` (
`id_viaggio` int(11)
,`id_viaggio_alternativa` int(11)
,`breve_descrizione` varchar(100)
,`totale_trasporti` decimal(49,11)
,`totale_alloggi` decimal(38,2)
,`totale_pasti` decimal(32,2)
,`totale_altri_costi` decimal(32,2)
,`totale_viaggio` decimal(52,11)
);

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
-- Indici per le tabelle `film`
--
ALTER TABLE `film`
  ADD PRIMARY KEY (`id_film`),
  ADD UNIQUE KEY `uq_film_tmdb` (`tmdb_id`),
  ADD KEY `idx_film_gruppo` (`id_gruppo`);

--
-- Indici per le tabelle `film2generi`
--
ALTER TABLE `film2generi`
  ADD PRIMARY KEY (`id_film`,`id_genere`),
  ADD KEY `idx_film2generi_genere` (`id_genere`);

--
-- Indici per le tabelle `film2liste`
--
ALTER TABLE `film2liste`
  ADD PRIMARY KEY (`id_film`,`id_lista`),
  ADD KEY `idx_film2liste_lista` (`id_lista`);

--
-- Indici per le tabelle `film2piattaforme`
--
ALTER TABLE `film2piattaforme`
  ADD PRIMARY KEY (`id_film`,`id_piattaforma`),
  ADD KEY `idx_film2piattaforme_piattaforma` (`id_piattaforma`);

--
-- Indici per le tabelle `film_commenti`
--
ALTER TABLE `film_commenti`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_film_commenti_film` (`id_film`),
  ADD KEY `idx_film_commenti_utente` (`id_utente`);

--
-- Indici per le tabelle `film_generi`
--
ALTER TABLE `film_generi`
  ADD PRIMARY KEY (`id_genere`);

--
-- Indici per le tabelle `film_gruppi`
--
ALTER TABLE `film_gruppi`
  ADD PRIMARY KEY (`id_gruppo`),
  ADD UNIQUE KEY `uq_film_gruppi_nome` (`nome`);

--
-- Indici per le tabelle `film_liste`
--
ALTER TABLE `film_liste`
  ADD PRIMARY KEY (`id_lista`),
  ADD KEY `idx_film_liste_utente` (`id_utente`);

--
-- Indici per le tabelle `film_utenti`
--
ALTER TABLE `film_utenti`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_film_utente` (`id_film`,`id_utente`),
  ADD KEY `idx_film_utenti_utente` (`id_utente`);

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
-- Indici per le tabelle `menu_cene_settimanale`
--
ALTER TABLE `menu_cene_settimanale`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_menu_settimana_giorno_famiglia` (`id_famiglia`,`week_start`,`giorno`);

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
-- Indici per le tabelle `mezzi_eventi`
--
ALTER TABLE `mezzi_eventi`
  ADD PRIMARY KEY (`id_evento`),
  ADD KEY `fk_eventi_mezzo` (`id_mezzo`),
  ADD KEY `fk_eventi_tipo` (`id_tipo_evento`);

--
-- Indici per le tabelle `mezzi_eventi_tipi`
--
ALTER TABLE `mezzi_eventi_tipi`
  ADD PRIMARY KEY (`id_tipo_evento`);

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
  ADD KEY `fk_revolut_salvadanaio` (`id_salvadanaio`),
  ADD KEY `idx_revolut_id_caricamento` (`id_caricamento`);

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
-- Indici per le tabelle `streaming_piattaforme`
--
ALTER TABLE `streaming_piattaforme`
  ADD PRIMARY KEY (`id_piattaforma`);

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
-- Indici per le tabelle `viaggi`
--
ALTER TABLE `viaggi`
  ADD PRIMARY KEY (`id_viaggio`),
  ADD KEY `id_luogo` (`id_luogo`);

--
-- Indici per le tabelle `viaggi2caricamenti`
--
ALTER TABLE `viaggi2caricamenti`
  ADD PRIMARY KEY (`id_viaggio`,`id_caricamento`),
  ADD KEY `id_caricamento` (`id_caricamento`);

--
-- Indici per le tabelle `viaggi2etichette`
--
ALTER TABLE `viaggi2etichette`
  ADD PRIMARY KEY (`id_viaggio`,`id_etichetta`),
  ADD KEY `id_etichetta` (`id_etichetta`);

--
-- Indici per le tabelle `viaggi_alloggi`
--
ALTER TABLE `viaggi_alloggi`
  ADD PRIMARY KEY (`id_alloggio`),
  ADD KEY `id_viaggio` (`id_viaggio`),
  ADD KEY `fk_viaggi_alloggi_alternativa` (`id_viaggio_alternativa`);

--
-- Indici per le tabelle `viaggi_alloggi_alternative`
--
ALTER TABLE `viaggi_alloggi_alternative`
  ADD PRIMARY KEY (`id_alloggio`,`id_viaggio_alternativa`),
  ADD KEY `idx_vaa_alternativa` (`id_viaggio_alternativa`);

--
-- Indici per le tabelle `viaggi_alternative`
--
ALTER TABLE `viaggi_alternative`
  ADD PRIMARY KEY (`id_viaggio_alternativa`),
  ADD KEY `id_viaggio` (`id_viaggio`);

--
-- Indici per le tabelle `viaggi_altri_costi`
--
ALTER TABLE `viaggi_altri_costi`
  ADD PRIMARY KEY (`id_costo`),
  ADD KEY `id_viaggio` (`id_viaggio`),
  ADD KEY `id_viaggio_alternativa` (`id_viaggio_alternativa`);

--
-- Indici per le tabelle `viaggi_altri_costi_alternative`
--
ALTER TABLE `viaggi_altri_costi_alternative`
  ADD PRIMARY KEY (`id_costo`,`id_viaggio_alternativa`),
  ADD KEY `idx_vaca_alternativa` (`id_viaggio_alternativa`);

--
-- Indici per le tabelle `viaggi_checklist`
--
ALTER TABLE `viaggi_checklist`
  ADD PRIMARY KEY (`id_checklist`),
  ADD KEY `id_viaggio` (`id_viaggio`),
  ADD KEY `id_utente` (`id_utente`);

--
-- Indici per le tabelle `viaggi_checklist_messaggi`
--
ALTER TABLE `viaggi_checklist_messaggi`
  ADD PRIMARY KEY (`id_messaggio`),
  ADD KEY `id_checklist` (`id_checklist`),
  ADD KEY `id_utente` (`id_utente`);

--
-- Indici per le tabelle `viaggi_commenti`
--
ALTER TABLE `viaggi_commenti`
  ADD PRIMARY KEY (`id_commento`),
  ADD KEY `idx_vcommenti_viaggio_visibilita` (`id_viaggio`,`visibilita`,`creato_il`),
  ADD KEY `idx_vcommenti_alternativa` (`id_viaggio_alternativa`),
  ADD KEY `fk_vcommenti_autore` (`id_utente`);

--
-- Indici per le tabelle `viaggi_condivisioni`
--
ALTER TABLE `viaggi_condivisioni`
  ADD PRIMARY KEY (`id_condivisione`),
  ADD UNIQUE KEY `uq_vc_token` (`token_sha256`),
  ADD KEY `idx_vc_viaggio` (`id_viaggio`),
  ADD KEY `idx_vc_scadenza` (`scade_il`),
  ADD KEY `fk_vc_autore` (`creato_da`);

--
-- Indici per le tabelle `viaggi_condivisioni_alternative`
--
ALTER TABLE `viaggi_condivisioni_alternative`
  ADD PRIMARY KEY (`id_condivisione`,`id_viaggio_alternativa`),
  ADD KEY `idx_vca_alternativa` (`id_viaggio_alternativa`);

--
-- Indici per le tabelle `viaggi_etichette`
--
ALTER TABLE `viaggi_etichette`
  ADD PRIMARY KEY (`id_etichetta`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Indici per le tabelle `viaggi_feedback`
--
ALTER TABLE `viaggi_feedback`
  ADD PRIMARY KEY (`id_feedback`),
  ADD KEY `id_viaggio` (`id_viaggio`),
  ADD KEY `id_utente` (`id_utente`);

--
-- Indici per le tabelle `viaggi_luoghi`
--
ALTER TABLE `viaggi_luoghi`
  ADD PRIMARY KEY (`id_luogo`);

--
-- Indici per le tabelle `viaggi_luogo_foto`
--
ALTER TABLE `viaggi_luogo_foto`
  ADD PRIMARY KEY (`id_foto`),
  ADD KEY `idx_vlf_luogo` (`id_luogo`);

--
-- Indici per le tabelle `viaggi_pasti`
--
ALTER TABLE `viaggi_pasti`
  ADD PRIMARY KEY (`id_pasto`),
  ADD KEY `id_viaggio` (`id_viaggio`),
  ADD KEY `id_viaggio_alternativa` (`id_viaggio_alternativa`);

--
-- Indici per le tabelle `viaggi_pasti_alternative`
--
ALTER TABLE `viaggi_pasti_alternative`
  ADD PRIMARY KEY (`id_pasto`,`id_viaggio_alternativa`),
  ADD KEY `idx_vpa_alternativa` (`id_viaggio_alternativa`);

--
-- Indici per le tabelle `viaggi_tratte`
--
ALTER TABLE `viaggi_tratte`
  ADD PRIMARY KEY (`id_tratta`),
  ADD KEY `id_viaggio` (`id_viaggio`),
  ADD KEY `fk_viaggi_tratte_alternativa` (`id_viaggio_alternativa`);

--
-- Indici per le tabelle `viaggi_tratte_alternative`
--
ALTER TABLE `viaggi_tratte_alternative`
  ADD PRIMARY KEY (`id_tratta`,`id_viaggio_alternativa`),
  ADD KEY `idx_vta_alternativa` (`id_viaggio_alternativa`);

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
-- AUTO_INCREMENT per la tabella `film`
--
ALTER TABLE `film`
  MODIFY `id_film` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `film_commenti`
--
ALTER TABLE `film_commenti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `film_gruppi`
--
ALTER TABLE `film_gruppi`
  MODIFY `id_gruppo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `film_liste`
--
ALTER TABLE `film_liste`
  MODIFY `id_lista` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `film_utenti`
--
ALTER TABLE `film_utenti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT per la tabella `menu_cene_settimanale`
--
ALTER TABLE `menu_cene_settimanale`
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
-- AUTO_INCREMENT per la tabella `mezzi_eventi`
--
ALTER TABLE `mezzi_eventi`
  MODIFY `id_evento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `mezzi_eventi_tipi`
--
ALTER TABLE `mezzi_eventi_tipi`
  MODIFY `id_tipo_evento` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT per la tabella `streaming_piattaforme`
--
ALTER TABLE `streaming_piattaforme`
  MODIFY `id_piattaforma` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT per la tabella `viaggi`
--
ALTER TABLE `viaggi`
  MODIFY `id_viaggio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `viaggi_alloggi`
--
ALTER TABLE `viaggi_alloggi`
  MODIFY `id_alloggio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `viaggi_alternative`
--
ALTER TABLE `viaggi_alternative`
  MODIFY `id_viaggio_alternativa` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `viaggi_altri_costi`
--
ALTER TABLE `viaggi_altri_costi`
  MODIFY `id_costo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `viaggi_checklist`
--
ALTER TABLE `viaggi_checklist`
  MODIFY `id_checklist` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `viaggi_checklist_messaggi`
--
ALTER TABLE `viaggi_checklist_messaggi`
  MODIFY `id_messaggio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `viaggi_commenti`
--
ALTER TABLE `viaggi_commenti`
  MODIFY `id_commento` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `viaggi_condivisioni`
--
ALTER TABLE `viaggi_condivisioni`
  MODIFY `id_condivisione` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `viaggi_etichette`
--
ALTER TABLE `viaggi_etichette`
  MODIFY `id_etichetta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `viaggi_feedback`
--
ALTER TABLE `viaggi_feedback`
  MODIFY `id_feedback` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `viaggi_luoghi`
--
ALTER TABLE `viaggi_luoghi`
  MODIFY `id_luogo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `viaggi_luogo_foto`
--
ALTER TABLE `viaggi_luogo_foto`
  MODIFY `id_foto` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `viaggi_pasti`
--
ALTER TABLE `viaggi_pasti`
  MODIFY `id_pasto` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `viaggi_tratte`
--
ALTER TABLE `viaggi_tratte`
  MODIFY `id_tratta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `webauthn_credentials`
--
ALTER TABLE `webauthn_credentials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Struttura per vista `v_aggiornamento_bilancio`
--
DROP TABLE IF EXISTS `v_aggiornamento_bilancio`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_aggiornamento_bilancio`  AS SELECT `bilancio_uscite`.`id_uscita` AS `id_uscita`, `bilancio_uscite`.`id_utente` AS `id_utente`, `bilancio_uscite`.`mezzo` AS `mezzo`, `bilancio_uscite`.`id_tipologia` AS `id_tipologia`, `bilancio_uscite`.`id_gruppo_transazione` AS `id_gruppo_transazione`, `bilancio_uscite`.`id_metodo_pagamento` AS `id_metodo_pagamento`, `bilancio_uscite`.`descrizione_operazione` AS `descrizione_operazione`, sum(`bilancio_uscite`.`importo`) AS `importo`, `bilancio_uscite`.`data_operazione` AS `data_operazione` FROM `bilancio_uscite` GROUP BY `bilancio_uscite`.`data_operazione`, `bilancio_uscite`.`id_utente`union select `bilancio_entrate`.`id_entrata` AS `id_entrata`,`bilancio_entrate`.`id_utente` AS `id_utente`,`bilancio_entrate`.`mezzo` AS `mezzo`,`bilancio_entrate`.`id_tipologia` AS `id_tipologia`,`bilancio_entrate`.`id_gruppo_transazione` AS `id_gruppo_transazione`,`bilancio_entrate`.`id_metodo_pagamento` AS `id_metodo_pagamento`,`bilancio_entrate`.`descrizione_operazione` AS `descrizione_operazione`,sum(`bilancio_entrate`.`importo`) AS `importo`,`bilancio_entrate`.`data_operazione` AS `data_operazione` from `bilancio_entrate` group by `bilancio_entrate`.`data_operazione`,`bilancio_entrate`.`id_utente`  ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_amazon_orders`
--
DROP TABLE IF EXISTS `v_amazon_orders`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_amazon_orders`  AS SELECT `amazon_orders`.`id_amazon_order` AS `id_amazon_order`, `amazon_orders`.`id_movimento_revolut` AS `id_movimento_revolut`, `amazon_orders`.`id_uscita` AS `id_uscita`, `amazon_orders`.`order_id` AS `order_id`, `amazon_orders`.`order_url` AS `order_url`, `amazon_orders`.`items` AS `items`, `amazon_orders`.`recipient` AS `recipient`, `amazon_orders`.`order_date` AS `order_date`, `amazon_orders`.`total_amount` AS `total_amount`, `amazon_orders`.`shipping_cost` AS `shipping_cost`, `amazon_orders`.`shipping_refund` AS `shipping_refund`, `amazon_orders`.`gift` AS `gift`, `amazon_orders`.`vat_amount` AS `vat_amount`, `amazon_orders`.`refund_amount` AS `refund_amount`, `amazon_orders`.`payments` AS `payments`, ifnull(`movimenti_revolut`.`descrizione_extra`,`bilancio_uscite`.`descrizione_extra`) AS `descrizione_extra`, ifnull(`movimenti_revolut`.`amount`,`bilancio_uscite`.`importo`) AS `importo`, ifnull(`movimenti_revolut`.`completed_date`,`bilancio_uscite`.`data_operazione`) AS `data_operazione`, (case when (`amazon_orders`.`completato` > 0) then `amazon_orders`.`completato` when ((ifnull(`movimenti_revolut`.`descrizione_extra`,`bilancio_uscite`.`descrizione_extra`) is not null) and (ifnull(`amazon_orders`.`id_movimento_revolut`,`amazon_orders`.`id_uscita`) > 0)) then 1 else 0 end) AS `completato` FROM ((`amazon_orders` left join `movimenti_revolut` on((`amazon_orders`.`id_movimento_revolut` = `movimenti_revolut`.`id_movimento_revolut`))) left join `bilancio_uscite` on((`amazon_orders`.`id_uscita` = `bilancio_uscite`.`id_uscita`))) ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_bilancio_etichette2operazioni`
--
DROP TABLE IF EXISTS `v_bilancio_etichette2operazioni`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_bilancio_etichette2operazioni`  AS SELECT `bilancio_etichette2operazioni`.`id_e2o` AS `id_e2o`, `bilancio_etichette2operazioni`.`id_etichetta` AS `id_etichetta`, `bilancio_etichette2operazioni`.`tabella_operazione` AS `tabella_operazione`, `bilancio_etichette2operazioni`.`id_tabella` AS `id_tabella`, ifnull(`bilancio_etichette2operazioni`.`descrizione_extra`,ifnull(`bilancio_uscite`.`descrizione_extra`,ifnull(`bilancio_entrate`.`descrizione_extra`,convert(convert(`movimenti_revolut`.`descrizione_extra` using utf8) using utf8mb4)))) AS `descrizione_extra`, `bilancio_etichette2operazioni`.`dividere_rimborsare` AS `dividere_rimborsare`, `bilancio_etichette2operazioni`.`allegato` AS `allegato`, ifnull(`bilancio_uscite`.`id_utente`,`bilancio_entrate`.`id_utente`) AS `id_utente`, ifnull(`bilancio_uscite`.`descrizione_operazione`,ifnull(`bilancio_entrate`.`descrizione_operazione`,convert(ifnull(convert(`movimenti_hype`.`descrizione` using utf8),convert(`movimenti_revolut`.`description` using utf8)) using utf8mb4))) AS `descrizione_operazione`, (case when (ifnull(`bilancio_etichette2operazioni`.`importo`,0) <> 0) then `bilancio_etichette2operazioni`.`importo` else ifnull(`bilancio_uscite`.`importo`,ifnull(`bilancio_entrate`.`importo`,ifnull(`movimenti_hype`.`importo`,`movimenti_revolut`.`amount`))) end) AS `importo`, ifnull(`bilancio_uscite`.`data_operazione`,ifnull(`bilancio_entrate`.`data_operazione`,ifnull(`movimenti_hype`.`data_operazione`,`movimenti_revolut`.`completed_date`))) AS `data_operazione`, (case when (`movimenti_revolut`.`id_movimento_revolut` is not null) then 1 else count(`bilancio_utenti2operazioni_etichettate`.`id_u2o`) end) AS `n_utenti`, sum(ifnull(`bilancio_utenti2operazioni_etichettate`.`quote`,1)) AS `quote_totali`, sum(ifnull(`bilancio_utenti2operazioni_etichettate`.`saldata`,0)) AS `operazioni_saldate`, (case when (`movimenti_revolut`.`id_movimento_revolut` is not null) then 0 when (count(`bilancio_utenti2operazioni_etichettate`.`id_u2o`) > 0) then 0 else 1 end) AS `senza_utenti` FROM (((((`bilancio_etichette2operazioni` left join `bilancio_entrate` on(((`bilancio_etichette2operazioni`.`tabella_operazione` = 'bilancio_entrate') and (`bilancio_etichette2operazioni`.`id_tabella` = `bilancio_entrate`.`id_entrata`)))) left join `bilancio_uscite` on(((`bilancio_etichette2operazioni`.`tabella_operazione` = 'bilancio_uscite') and (`bilancio_etichette2operazioni`.`id_tabella` = `bilancio_uscite`.`id_uscita`)))) left join `movimenti_revolut` on(((`bilancio_etichette2operazioni`.`tabella_operazione` = 'movimenti_revolut') and (`bilancio_etichette2operazioni`.`id_tabella` = `movimenti_revolut`.`id_movimento_revolut`)))) left join `movimenti_hype` on(((`bilancio_etichette2operazioni`.`tabella_operazione` = 'movimenti_hype') and (`bilancio_etichette2operazioni`.`id_tabella` = `movimenti_hype`.`id_movimento_hype`)))) left join `bilancio_utenti2operazioni_etichettate` on((`bilancio_utenti2operazioni_etichettate`.`id_e2o` = `bilancio_etichette2operazioni`.`id_e2o`))) GROUP BY `bilancio_etichette2operazioni`.`id_e2o` ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_bilancio_etichette2operazioni_a_testa`
--
DROP TABLE IF EXISTS `v_bilancio_etichette2operazioni_a_testa`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_bilancio_etichette2operazioni_a_testa`  AS SELECT ifnull(`bilancio_uscite`.`id_utente`,ifnull(`bilancio_entrate`.`id_utente`,(case when (`bilancio_etichette2operazioni`.`tabella_operazione` = 'movimenti_hype') then 1 when (`bilancio_etichette2operazioni`.`tabella_operazione` = 'movimenti_revolut') then 1 else NULL end))) AS `id_utente_operazione`, ifnull(`bilancio_uscite`.`descrizione_operazione`,ifnull(`bilancio_entrate`.`descrizione_operazione`,convert(convert(ifnull(`movimenti_hype`.`descrizione`,`movimenti_revolut`.`description`) using utf8) using utf8mb4))) AS `descrizione_operazione`, ifnull(`bilancio_uscite`.`data_operazione`,ifnull(`bilancio_entrate`.`data_operazione`,ifnull(`movimenti_hype`.`data_operazione`,`movimenti_revolut`.`started_date`))) AS `data_operazione`, `bilancio_etichette2operazioni`.`id_e2o` AS `id_e2o`, `bilancio_etichette2operazioni`.`id_etichetta` AS `id_etichetta`, ifnull(`bilancio_etichette2operazioni`.`descrizione_extra`,ifnull(`bilancio_uscite`.`descrizione_extra`,`bilancio_entrate`.`descrizione_extra`)) AS `descrizione_extra`, `bilancio_etichette2operazioni`.`allegato` AS `allegato`, `bilancio_etichette`.`descrizione` AS `descrizione`, ifnull(`bilancio_uscite`.`importo`,ifnull(`bilancio_entrate`.`importo`,ifnull(`movimenti_hype`.`importo`,`movimenti_revolut`.`amount`))) AS `importo_totale_operazione`, `bilancio_etichette2operazioni`.`importo` AS `importo_etichetta`, ((case when (ifnull(`bilancio_etichette2operazioni`.`importo`,0) <> 0) then `bilancio_etichette2operazioni`.`importo` else ifnull(`bilancio_uscite`.`importo`,ifnull(`bilancio_entrate`.`importo`,ifnull(`movimenti_hype`.`importo`,`movimenti_revolut`.`amount`))) end) / sum(ifnull(`bilancio_utenti2operazioni_etichettate`.`quote`,1))) AS `importo` FROM ((((((`bilancio_etichette2operazioni` join `bilancio_etichette` on((`bilancio_etichette`.`id_etichetta` = `bilancio_etichette2operazioni`.`id_etichetta`))) left join `bilancio_entrate` on(((`bilancio_etichette2operazioni`.`tabella_operazione` = 'bilancio_entrate') and (`bilancio_etichette2operazioni`.`id_tabella` = `bilancio_entrate`.`id_entrata`)))) left join `bilancio_uscite` on(((`bilancio_etichette2operazioni`.`tabella_operazione` = 'bilancio_uscite') and (`bilancio_etichette2operazioni`.`id_tabella` = `bilancio_uscite`.`id_uscita`)))) left join `bilancio_utenti2operazioni_etichettate` on((`bilancio_utenti2operazioni_etichettate`.`id_e2o` = `bilancio_etichette2operazioni`.`id_e2o`))) left join `movimenti_hype` on(((`bilancio_etichette2operazioni`.`tabella_operazione` = 'movimenti_hype') and (`bilancio_etichette2operazioni`.`id_tabella` = `movimenti_hype`.`id_movimento_hype`)))) left join `movimenti_revolut` on(((`bilancio_etichette2operazioni`.`tabella_operazione` = 'movimenti_revolut') and (`bilancio_etichette2operazioni`.`id_tabella` = `movimenti_revolut`.`id_movimento_revolut`)))) WHERE (`bilancio_etichette`.`attivo` = 1) GROUP BY `bilancio_etichette2operazioni`.`id_e2o` ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_bilancio_etichette2operazioni_a_testa_OLD20250414`
--
DROP TABLE IF EXISTS `v_bilancio_etichette2operazioni_a_testa_OLD20250414`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_bilancio_etichette2operazioni_a_testa_OLD20250414`  AS SELECT ifnull(`bilancio_uscite`.`id_utente`,`bilancio_entrate`.`id_utente`) AS `id_utente_operazione`, ifnull(`bilancio_uscite`.`descrizione_operazione`,`bilancio_entrate`.`descrizione_operazione`) AS `descrizione_operazione`, ifnull(`bilancio_uscite`.`data_operazione`,`bilancio_entrate`.`data_operazione`) AS `data_operazione`, `bilancio_etichette2operazioni`.`id_e2o` AS `id_e2o`, `bilancio_etichette2operazioni`.`id_etichetta` AS `id_etichetta`, ifnull(`bilancio_etichette2operazioni`.`descrizione_extra`,ifnull(`bilancio_uscite`.`descrizione_extra`,`bilancio_entrate`.`descrizione_extra`)) AS `descrizione_extra`, `bilancio_etichette2operazioni`.`allegato` AS `allegato`, `bilancio_etichette`.`descrizione` AS `descrizione`, ifnull(`bilancio_uscite`.`importo`,`bilancio_entrate`.`importo`) AS `importo_totale_operazione`, `bilancio_etichette2operazioni`.`importo` AS `importo_etichetta`, ((case when (ifnull(`bilancio_etichette2operazioni`.`importo`,0) <> 0) then `bilancio_etichette2operazioni`.`importo` else ifnull(`bilancio_uscite`.`importo`,`bilancio_entrate`.`importo`) end) / sum(ifnull(`bilancio_utenti2operazioni_etichettate`.`quote`,1))) AS `importo` FROM ((((`bilancio_etichette2operazioni` join `bilancio_etichette` on((`bilancio_etichette`.`id_etichetta` = `bilancio_etichette2operazioni`.`id_etichetta`))) left join `bilancio_entrate` on(((`bilancio_etichette2operazioni`.`tabella_operazione` = 'bilancio_entrate') and (`bilancio_etichette2operazioni`.`id_tabella` = `bilancio_entrate`.`id_entrata`)))) left join `bilancio_uscite` on(((`bilancio_etichette2operazioni`.`tabella_operazione` = 'bilancio_uscite') and (`bilancio_etichette2operazioni`.`id_tabella` = `bilancio_uscite`.`id_uscita`)))) left join `bilancio_utenti2operazioni_etichettate` on((`bilancio_utenti2operazioni_etichettate`.`id_e2o` = `bilancio_etichette2operazioni`.`id_e2o`))) WHERE (`bilancio_etichette`.`attivo` = 1) GROUP BY `bilancio_etichette2operazioni`.`id_e2o` ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_bilancio_etichette2operazioni_revolut`
--
DROP TABLE IF EXISTS `v_bilancio_etichette2operazioni_revolut`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_bilancio_etichette2operazioni_revolut`  AS SELECT `bilancio_etichette2operazioni`.`id_e2o` AS `id_e2o`, `bilancio_etichette2operazioni`.`id_etichetta` AS `id_etichetta`, `bilancio_etichette2operazioni`.`tabella_operazione` AS `tabella_operazione`, `bilancio_etichette2operazioni`.`id_tabella` AS `id_tabella`, `bilancio_etichette2operazioni`.`descrizione_extra` AS `descrizione_extra`, `bilancio_etichette2operazioni`.`importo` AS `importo`, `bilancio_etichette2operazioni`.`dividere_rimborsare` AS `dividere_rimborsare`, `bilancio_etichette2operazioni`.`allegato` AS `allegato` FROM `bilancio_etichette2operazioni` ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_bilancio_etichette2operazioni_uscite`
--
DROP TABLE IF EXISTS `v_bilancio_etichette2operazioni_uscite`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_bilancio_etichette2operazioni_uscite`  AS SELECT `bilancio_etichette2operazioni`.`id_e2o` AS `id_e2o`, `bilancio_etichette2operazioni`.`id_etichetta` AS `id_etichetta`, `bilancio_etichette2operazioni`.`id_tabella` AS `id_tabella`, `bilancio_etichette2operazioni`.`descrizione_extra` AS `descrizione_extra`, `bilancio_etichette2operazioni`.`importo` AS `importo`, `bilancio_etichette2operazioni`.`dividere_rimborsare` AS `dividere_rimborsare`, `bilancio_etichette2operazioni`.`allegato` AS `allegato` FROM `bilancio_etichette2operazioni` WHERE (`bilancio_etichette2operazioni`.`tabella_operazione` = 'bilancio_uscite') ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_bilancio_etichette_da_dividere`
--
DROP TABLE IF EXISTS `v_bilancio_etichette_da_dividere`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_bilancio_etichette_da_dividere`  AS SELECT DISTINCT `bilancio_etichette`.`descrizione` AS `descrizione`, `bilancio_etichette`.`id_etichetta` AS `id_etichetta` FROM ((`bilancio_etichette` join `bilancio_etichette2operazioni` on((`bilancio_etichette2operazioni`.`id_etichetta` = `bilancio_etichette`.`id_etichetta`))) left join `bilancio_utenti2operazioni_etichettate` on((`bilancio_etichette2operazioni`.`id_e2o` = `bilancio_utenti2operazioni_etichettate`.`id_e2o`))) WHERE ((`bilancio_etichette2operazioni`.`tabella_operazione` <> 'movimenti_revolut') AND (`bilancio_etichette`.`attivo` = 1) AND (`bilancio_etichette`.`da_dividere` = 1)) GROUP BY `bilancio_etichette2operazioni`.`id_e2o` HAVING (count(`bilancio_utenti2operazioni_etichettate`.`id_u2o`) = 0) ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_bilancio_gruppo_mese`
--
DROP TABLE IF EXISTS `v_bilancio_gruppo_mese`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_bilancio_gruppo_mese`  AS SELECT `bilancio_uscite`.`id_utente` AS `id_utente`, `bilancio_gruppi_transazione`.`descrizione` AS `descrizione`, `bilancio_gruppi_transazione`.`attivo` AS `attivo`, `bilancio_gruppi_transazione`.`id_gruppo_transazione` AS `id_gruppo_transazione`, sum(`bilancio_uscite`.`importo`) AS `totale_speso`, 0 AS `totale_entrato`, max(`bilancio_uscite`.`data_operazione`) AS `ultima_operazione`, year(`bilancio_uscite`.`data_operazione`) AS `anno`, month(`bilancio_uscite`.`data_operazione`) AS `mese` FROM (`bilancio_uscite` left join `bilancio_gruppi_transazione` on((`bilancio_gruppi_transazione`.`id_gruppo_transazione` = `bilancio_uscite`.`id_gruppo_transazione`))) GROUP BY `bilancio_uscite`.`id_utente`, year(`bilancio_uscite`.`data_operazione`), month(`bilancio_uscite`.`data_operazione`), `bilancio_uscite`.`id_gruppo_transazione`union select `bilancio_entrate`.`id_utente` AS `id_utente`,`bilancio_gruppi_transazione`.`descrizione` AS `descrizione`,`bilancio_gruppi_transazione`.`attivo` AS `attivo`,`bilancio_gruppi_transazione`.`id_gruppo_transazione` AS `id_gruppo_transazione`,0 AS `totale_speso`,sum(ifnull(`bilancio_entrate`.`importo`,0)) AS `totale_entrato`,max(`bilancio_entrate`.`data_operazione`) AS `ultima_operazione`,year(`bilancio_entrate`.`data_operazione`) AS `anno`,month(`bilancio_entrate`.`data_operazione`) AS `mese` from (`bilancio_entrate` left join `bilancio_gruppi_transazione` on((`bilancio_gruppi_transazione`.`id_gruppo_transazione` = `bilancio_entrate`.`id_gruppo_transazione`))) group by `bilancio_entrate`.`id_utente`,year(`bilancio_entrate`.`data_operazione`),month(`bilancio_entrate`.`data_operazione`),`bilancio_entrate`.`id_gruppo_transazione`  ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_bilancio_ricorsivi_data`
--
DROP TABLE IF EXISTS `v_bilancio_ricorsivi_data`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_bilancio_ricorsivi_data`  AS SELECT `bilancio_ricorsivi`.`id_ricorsivo` AS `id_ricorsivo`, `bilancio_ricorsivi`.`descrizione` AS `descrizione`, `bilancio_ricorsivi`.`ogni` AS `ogni`, `bilancio_ricorsivi`.`quanto` AS `quanto`, `bilancio_ricorsivi`.`importo` AS `importo`, `bilancio_ricorsivi`.`attivo` AS `attivo`, `bilancio_ricorsivi`.`id_utente` AS `id_utente`, `bilancio_ricorsivi`.`descrizione_operazione` AS `descrizione_operazione`, `bilancio_ricorsivi`.`id_gruppo_transazione` AS `id_gruppo_transazione`, `bilancio_ricorsivi`.`condivise` AS `condivise`, `time_dimension`.`db_date` AS `db_date`, `time_dimension`.`year` AS `year`, `time_dimension`.`month` AS `month` FROM (`bilancio_ricorsivi` left join `time_dimension` on((case when (`bilancio_ricorsivi`.`ogni` = 'data_precisa') then (`bilancio_ricorsivi`.`quanto` = `time_dimension`.`db_date`) when (`bilancio_ricorsivi`.`ogni` = 'anno') then ((dayofmonth(`bilancio_ricorsivi`.`quanto`) = `time_dimension`.`day`) and (month(`bilancio_ricorsivi`.`quanto`) = `time_dimension`.`month`)) when (`bilancio_ricorsivi`.`ogni` = 'mese') then (dayofmonth(`bilancio_ricorsivi`.`quanto`) = `time_dimension`.`day`) end))) WHERE ((case when (`bilancio_ricorsivi`.`ogni` = 'data_precisa') then `bilancio_ricorsivi`.`quanto` else '2022-01-02' end) > '2022-01-01') ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_bilancio_ricorsivi_mese`
--
DROP TABLE IF EXISTS `v_bilancio_ricorsivi_mese`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_bilancio_ricorsivi_mese`  AS SELECT `v_bilancio_ricorsivi_data`.`id_ricorsivo` AS `id_ricorsivo`, `v_bilancio_ricorsivi_data`.`id_utente` AS `id_utente`, `v_bilancio_ricorsivi_data`.`descrizione` AS `descrizione`, `v_bilancio_ricorsivi_data`.`ogni` AS `ogni`, `v_bilancio_ricorsivi_data`.`quanto` AS `quanto`, `v_bilancio_ricorsivi_data`.`importo` AS `importo`, `v_bilancio_ricorsivi_data`.`descrizione_operazione` AS `descrizione_operazione`, `v_bilancio_ricorsivi_data`.`id_gruppo_transazione` AS `id_gruppo_transazione`, 1 AS `da_mostrare_nel_mese_corrente`, `v_bilancio_ricorsivi_data`.`month` AS `month`, `v_bilancio_ricorsivi_data`.`year` AS `year`, `v_bilancio_ricorsivi_data`.`condivise` AS `condivise`, `v_bilancio_ricorsivi_data`.`db_date` AS `db_date`, (case when (`v_bilancio_ricorsivi_data`.`importo` > 0) then `v_bilancio_ricorsivi_data`.`importo` else 0 end) AS `entrate_mese_previste`, (case when (`v_bilancio_ricorsivi_data`.`importo` < 0) then `v_bilancio_ricorsivi_data`.`importo` else 0 end) AS `uscite_mese_previste` FROM `v_bilancio_ricorsivi_data` WHERE (`v_bilancio_ricorsivi_data`.`attivo` = 1) ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_bilancio_ricorsivi_ultima_operazione`
--
DROP TABLE IF EXISTS `v_bilancio_ricorsivi_ultima_operazione`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_bilancio_ricorsivi_ultima_operazione`  AS SELECT `bilancio_ricorsivi`.`id_ricorsivo` AS `id_ricorsivo`, `bilancio_ricorsivi`.`id_utente` AS `id_utente`, `bilancio_ricorsivi`.`descrizione` AS `descrizione`, `bilancio_ricorsivi`.`attivo` AS `attivo`, `bilancio_ricorsivi`.`ogni` AS `ogni`, `bilancio_ricorsivi`.`quanto` AS `quanto`, `bilancio_ricorsivi`.`importo` AS `importo`, `bilancio_ricorsivi`.`descrizione_operazione` AS `descrizione_operazione`, `bilancio_ricorsivi`.`id_gruppo_transazione` AS `id_gruppo_transazione`, ifnull(max(`bilancio_uscite_gruppo`.`data_operazione`),max(`ultima_uscita_descrizione`.`data_operazione`)) AS `data_operazione` FROM ((`bilancio_ricorsivi` left join `bilancio_uscite` `ultima_uscita_descrizione` on(((`ultima_uscita_descrizione`.`id_utente` = `bilancio_ricorsivi`.`id_utente`) and (`ultima_uscita_descrizione`.`descrizione_operazione` like concat('%',`bilancio_ricorsivi`.`descrizione_operazione`,'%'))))) left join `bilancio_uscite` `bilancio_uscite_gruppo` on(((`bilancio_ricorsivi`.`id_gruppo_transazione` = `bilancio_uscite_gruppo`.`id_gruppo_transazione`) and (`bilancio_ricorsivi`.`id_utente` = `bilancio_uscite_gruppo`.`id_utente`)))) WHERE (`bilancio_ricorsivi`.`attivo` = 1) GROUP BY `bilancio_ricorsivi`.`id_ricorsivo` ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_bilancio_utenti2operazioni_etichettate`
--
DROP TABLE IF EXISTS `v_bilancio_utenti2operazioni_etichettate`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_bilancio_utenti2operazioni_etichettate`  AS SELECT `bilancio_utenti2operazioni_etichettate`.`id_u2o` AS `id_u2o`, `bilancio_utenti2operazioni_etichettate`.`id_utente` AS `id_utente`, `bilancio_utenti2operazioni_etichettate`.`id_e2o` AS `id_e2o`, `bilancio_utenti2operazioni_etichettate`.`saldata` AS `saldata`, `v_bilancio_etichette2operazioni_a_testa`.`id_etichetta` AS `id_etichetta`, `v_bilancio_etichette2operazioni_a_testa`.`descrizione_operazione` AS `descrizione_operazione`, `v_bilancio_etichette2operazioni_a_testa`.`data_operazione` AS `data_operazione`, (case when (ifnull(`bilancio_utenti2operazioni_etichettate`.`importo_utente`,0) <> 0) then `bilancio_utenti2operazioni_etichettate`.`importo_utente` else (`v_bilancio_etichette2operazioni_a_testa`.`importo` * ifnull(`bilancio_utenti2operazioni_etichettate`.`quote`,1)) end) AS `importo` FROM (`bilancio_utenti2operazioni_etichettate` join `v_bilancio_etichette2operazioni_a_testa` on((`bilancio_utenti2operazioni_etichettate`.`id_e2o` = `v_bilancio_etichette2operazioni_a_testa`.`id_e2o`))) ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_bilancio_utenti2operazioni_etichettate_utente`
--
DROP TABLE IF EXISTS `v_bilancio_utenti2operazioni_etichettate_utente`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_bilancio_utenti2operazioni_etichettate_utente`  AS SELECT `bilancio_utenti2operazioni_etichettate`.`id_u2o` AS `id_u2o`, `bilancio_utenti2operazioni_etichettate`.`id_utente` AS `id_utente`, `bilancio_utenti2operazioni_etichettate`.`id_e2o` AS `id_e2o`, `bilancio_utenti2operazioni_etichettate`.`saldata` AS `saldata`, str_to_date(date_format(`bilancio_utenti2operazioni_etichettate`.`data_saldo`,'%Y-%m-%d'),'%Y-%m-%d') AS `data_saldo`, ifnull(`v_bilancio_etichette2operazioni_a_testa`.`allegato`,'') AS `allegato`, `v_bilancio_etichette2operazioni_a_testa`.`id_etichetta` AS `id_etichetta`, `v_bilancio_etichette2operazioni_a_testa`.`descrizione` AS `descrizione`, `v_bilancio_etichette2operazioni_a_testa`.`id_utente_operazione` AS `id_utente_operazione`, concat(`v_bilancio_etichette2operazioni_a_testa`.`descrizione_operazione`,' (',`v_bilancio_etichette2operazioni_a_testa`.`importo_totale_operazione`,')') AS `descrizione_operazione`, `v_bilancio_etichette2operazioni_a_testa`.`descrizione_extra` AS `descrizione_extra`, `v_bilancio_etichette2operazioni_a_testa`.`data_operazione` AS `data_operazione`, (case when (`v_bilancio_etichette2operazioni_a_testa`.`id_utente_operazione` = `bilancio_utenti2operazioni_etichettate`.`id_utente`) then (-(1) * (case when (ifnull(`bilancio_utenti2operazioni_etichettate`.`importo_utente`,0) <> 0) then `bilancio_utenti2operazioni_etichettate`.`importo_utente` when (`v_bilancio_etichette2operazioni_a_testa`.`importo_etichetta` <> 0) then (`v_bilancio_etichette2operazioni_a_testa`.`importo` * `bilancio_utenti2operazioni_etichettate`.`quote`) else (`v_bilancio_etichette2operazioni_a_testa`.`importo_totale_operazione` - (`v_bilancio_etichette2operazioni_a_testa`.`importo` * `bilancio_utenti2operazioni_etichettate`.`quote`)) end)) else (case when (ifnull(`bilancio_utenti2operazioni_etichettate`.`importo_utente`,0) <> 0) then `bilancio_utenti2operazioni_etichettate`.`importo_utente` else (`v_bilancio_etichette2operazioni_a_testa`.`importo` * `bilancio_utenti2operazioni_etichettate`.`quote`) end) end) AS `importo` FROM (`bilancio_utenti2operazioni_etichettate` join `v_bilancio_etichette2operazioni_a_testa` on((`bilancio_utenti2operazioni_etichettate`.`id_e2o` = `v_bilancio_etichette2operazioni_a_testa`.`id_e2o`))) ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_bilancio_utenti2operazioni_etichettate_utente_old20250414`
--
DROP TABLE IF EXISTS `v_bilancio_utenti2operazioni_etichettate_utente_old20250414`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_bilancio_utenti2operazioni_etichettate_utente_old20250414`  AS SELECT `bilancio_utenti2operazioni_etichettate`.`id_u2o` AS `id_u2o`, `bilancio_utenti2operazioni_etichettate`.`id_utente` AS `id_utente`, `bilancio_utenti2operazioni_etichettate`.`id_e2o` AS `id_e2o`, `bilancio_utenti2operazioni_etichettate`.`saldata` AS `saldata`, str_to_date(date_format(`bilancio_utenti2operazioni_etichettate`.`data_saldo`,'%Y-%m-%d'),'%Y-%m-%d') AS `data_saldo`, ifnull(`v_bilancio_etichette2operazioni_a_testa`.`allegato`,'') AS `allegato`, `v_bilancio_etichette2operazioni_a_testa`.`id_etichetta` AS `id_etichetta`, `v_bilancio_etichette2operazioni_a_testa`.`descrizione` AS `descrizione`, `v_bilancio_etichette2operazioni_a_testa`.`id_utente_operazione` AS `id_utente_operazione`, concat(`v_bilancio_etichette2operazioni_a_testa`.`descrizione_operazione`,' (',`v_bilancio_etichette2operazioni_a_testa`.`importo_totale_operazione`,')') AS `descrizione_operazione`, `v_bilancio_etichette2operazioni_a_testa`.`descrizione_extra` AS `descrizione_extra`, `v_bilancio_etichette2operazioni_a_testa`.`data_operazione` AS `data_operazione`, (case when (`v_bilancio_etichette2operazioni_a_testa`.`id_utente_operazione` = `bilancio_utenti2operazioni_etichettate`.`id_utente`) then (-(1) * (case when (ifnull(`bilancio_utenti2operazioni_etichettate`.`importo_utente`,0) <> 0) then `bilancio_utenti2operazioni_etichettate`.`importo_utente` when (`v_bilancio_etichette2operazioni_a_testa`.`importo_etichetta` <> 0) then (`v_bilancio_etichette2operazioni_a_testa`.`importo` * `bilancio_utenti2operazioni_etichettate`.`quote`) else (`v_bilancio_etichette2operazioni_a_testa`.`importo_totale_operazione` - (`v_bilancio_etichette2operazioni_a_testa`.`importo` * `bilancio_utenti2operazioni_etichettate`.`quote`)) end)) else (case when (ifnull(`bilancio_utenti2operazioni_etichettate`.`importo_utente`,0) <> 0) then `bilancio_utenti2operazioni_etichettate`.`importo_utente` else (`v_bilancio_etichette2operazioni_a_testa`.`importo` * `bilancio_utenti2operazioni_etichettate`.`quote`) end) end) AS `importo` FROM (`bilancio_utenti2operazioni_etichettate` join `v_bilancio_etichette2operazioni_a_testa` on((`bilancio_utenti2operazioni_etichettate`.`id_e2o` = `v_bilancio_etichette2operazioni_a_testa`.`id_e2o`))) ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_colonne_importa_movimenti`
--
DROP TABLE IF EXISTS `v_colonne_importa_movimenti`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_colonne_importa_movimenti`  AS SELECT `information_schema`.`columns`.`COLUMN_NAME` AS `colonna_db` FROM `information_schema`.`columns` WHERE ((`information_schema`.`columns`.`TABLE_SCHEMA` = 'Sql1203781_2') AND (`information_schema`.`columns`.`TABLE_NAME` = 'v_importa_movimenti')) ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_elenco_operazioni`
--
DROP TABLE IF EXISTS `v_elenco_operazioni`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_elenco_operazioni`  AS SELECT `bilancio_entrate`.`id_entrata` AS `id_entrata_uscita`, 'bilancio_entrate' AS `tabella_operazione`, `bilancio_entrate`.`id_utente` AS `id_utente`, `bilancio_entrate`.`mezzo` AS `mezzo`, `bilancio_entrate`.`id_tipologia` AS `id_tipologia`, `bilancio_entrate`.`id_gruppo_transazione` AS `id_gruppo_transazione`, (case when isnull(`bilancio_entrate`.`id_gruppo_transazione`) then 1 else 0 end) AS `da_inserire_gruppo`, `bilancio_entrate`.`id_metodo_pagamento` AS `id_metodo_pagamento`, `bilancio_entrate`.`descrizione_extra` AS `descrizione_extra`, `bilancio_entrate`.`descrizione_operazione` AS `descrizione_operazione`, `bilancio_entrate`.`importo` AS `importo`, `bilancio_entrate`.`data_operazione` AS `data_operazione`, count(`bilancio_etichette2operazioni`.`id_e2o`) AS `numero_etichette`, (case when ((`bilancio_entrate`.`id_gruppo_transazione` = 0) or (`bilancio_entrate`.`id_metodo_pagamento` = 0)) then 1 else 0 end) AS `da_sistemare`, 'colonna vuota per ignorare' AS `colonna_ignora`, 'colonna vuota per ignorare' AS `colonna_ignora2` FROM (`bilancio_entrate` left join `bilancio_etichette2operazioni` on(((`bilancio_etichette2operazioni`.`tabella_operazione` = 'bilancio_entrate') and (`bilancio_etichette2operazioni`.`id_tabella` = `bilancio_entrate`.`id_entrata`)))) GROUP BY `bilancio_entrate`.`id_entrata`union select `bilancio_uscite`.`id_uscita` AS `id_entrata_uscita`,'bilancio_uscite' AS `tabella_operazione`,`bilancio_uscite`.`id_utente` AS `id_utente`,`bilancio_uscite`.`mezzo` AS `mezzo`,`bilancio_uscite`.`id_tipologia` AS `id_tipologia`,`bilancio_uscite`.`id_gruppo_transazione` AS `id_gruppo_transazione`,(case when isnull(`bilancio_uscite`.`id_gruppo_transazione`) then 1 else 0 end) AS `da_inserire_gruppo`,`bilancio_uscite`.`id_metodo_pagamento` AS `id_metodo_pagamento`,`bilancio_uscite`.`descrizione_extra` AS `descrizione_extra`,`bilancio_uscite`.`descrizione_operazione` AS `descrizione_operazione`,`bilancio_uscite`.`importo` AS `importo`,`bilancio_uscite`.`data_operazione` AS `data_operazione`,count(`bilancio_etichette2operazioni`.`id_e2o`) AS `numero_etichette`,(case when ((`bilancio_uscite`.`id_gruppo_transazione` = 0) or (`bilancio_uscite`.`id_metodo_pagamento` = 0)) then 1 else 0 end) AS `da_sistemare`,'colonna vuota per ignorare' AS `colonna_ignora`,'colonna vuota per ignorare' AS `colonna_ignora2` from (`bilancio_uscite` left join `bilancio_etichette2operazioni` on(((`bilancio_etichette2operazioni`.`tabella_operazione` = 'bilancio_uscite') and (`bilancio_etichette2operazioni`.`id_tabella` = `bilancio_uscite`.`id_uscita`)))) group by `bilancio_uscite`.`id_uscita`  ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_etichette2operazioni`
--
DROP TABLE IF EXISTS `v_etichette2operazioni`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_etichette2operazioni`  AS SELECT `bilancio_etichette2operazioni`.`id_e2o` AS `id_e2o`, `bilancio_etichette`.`id_etichetta` AS `id_etichetta`, `bilancio_etichette`.`descrizione` AS `descrizione`, `bilancio_etichette2operazioni`.`tabella_operazione` AS `tabella_operazione`, `bilancio_etichette2operazioni`.`id_tabella` AS `id_tabella`, `bilancio_etichette2operazioni`.`importo` AS `importo`, `bilancio_etichette`.`attivo` AS `attivo` FROM (`bilancio_etichette` join `bilancio_etichette2operazioni` on((`bilancio_etichette`.`id_etichetta` = `bilancio_etichette2operazioni`.`id_etichetta`))) WHERE 1 ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_eventi`
--
DROP TABLE IF EXISTS `v_eventi`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_eventi`  AS SELECT `eventi`.`id` AS `id`, `eventi`.`titolo` AS `titolo`, `eventi`.`data_evento` AS `data_evento`, `eventi`.`ora_evento` AS `ora_evento`, `eventi`.`descrizione` AS `descrizione`, `eventi`.`id_tipo_evento` AS `id_tipo_evento`, `eventi`.`icon` AS `icon`, `eventi`.`note` AS `note`, sum((case when (`eventi_eventi2invitati`.`partecipa` = 1) then 1 else 0 end)) AS `partecipano`, sum((case when (`eventi_eventi2invitati`.`partecipa` = 0) then 1 else 0 end)) AS `non_partecipano`, sum((case when (`eventi_eventi2invitati`.`partecipa` = 2) then 1 else 0 end)) AS `in_forse` FROM (`eventi` left join `eventi_eventi2invitati` on((`eventi`.`id` = `eventi_eventi2invitati`.`id_evento`))) GROUP BY `eventi`.`id` ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_eventi_eventi2invitati`
--
DROP TABLE IF EXISTS `v_eventi_eventi2invitati`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_eventi_eventi2invitati`  AS SELECT `eventi_eventi2invitati`.`id_evento` AS `id_evento`, `eventi_eventi2invitati`.`id_invitato` AS `id_invitato`, `eventi_invitati`.`nome` AS `nome`, `eventi_invitati`.`cognome` AS `cognome`, `eventi_eventi2invitati`.`partecipa` AS `partecipa`, `eventi_invitati`.`id_utente` AS `id_utente`, concat(`eventi_invitati`.`nome`,' ',`eventi_invitati`.`cognome`) AS `fullname` FROM (`eventi_eventi2invitati` join `eventi_invitati` on((`eventi_eventi2invitati`.`id_invitato` = `eventi_invitati`.`id`))) ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_eventi_viaggi`
--
DROP TABLE IF EXISTS `v_eventi_viaggi`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_eventi_viaggi`  AS SELECT `v`.`titolo` AS `titolo`, `v`.`data_inizio` AS `data_inizio`, `v`.`data_fine` AS `data_fine` FROM `viaggi` AS `v` WHERE (`v`.`stato` in ('pianificato','prenotato')) ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_importa_movimenti`
--
DROP TABLE IF EXISTS `v_importa_movimenti`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_importa_movimenti`  AS SELECT `bilancio_entrate`.`id_entrata` AS `id_entrata`, `bilancio_entrate`.`id_utente` AS `id_utente`, `bilancio_entrate`.`mezzo` AS `mezzo`, `bilancio_entrate`.`id_tipologia` AS `id_tipologia`, `bilancio_entrate`.`id_gruppo_transazione` AS `id_gruppo_transazione`, `bilancio_entrate`.`id_metodo_pagamento` AS `id_metodo_pagamento`, `bilancio_entrate`.`descrizione_operazione` AS `descrizione_operazione`, `bilancio_entrate`.`importo` AS `importo`, `bilancio_entrate`.`data_operazione` AS `data_operazione`, 'colonna vuota per ignorare' AS `colonna_ignora`, 'colonna vuota per ignorare' AS `colonna_ignora2` FROM `bilancio_entrate` ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_mensili_entrate`
--
DROP TABLE IF EXISTS `v_mensili_entrate`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_mensili_entrate`  AS SELECT `bilancio_entrate`.`id_utente` AS `id_utente`, `bilancio_gruppi_transazione`.`descrizione` AS `descrizione`, `bilancio_gruppi_transazione`.`attivo` AS `attivo`, `bilancio_gruppi_transazione`.`id_gruppo_transazione` AS `id_gruppo_transazione`, 0 AS `totale_speso`, sum(ifnull(`bilancio_entrate`.`importo`,0)) AS `totale_entrato`, max(`bilancio_entrate`.`data_operazione`) AS `ultima_operazione`, year(`bilancio_entrate`.`data_operazione`) AS `anno`, month(`bilancio_entrate`.`data_operazione`) AS `mese` FROM (`bilancio_entrate` left join `bilancio_gruppi_transazione` on((`bilancio_gruppi_transazione`.`id_gruppo_transazione` = `bilancio_entrate`.`id_gruppo_transazione`))) GROUP BY `bilancio_entrate`.`id_utente`, year(`bilancio_entrate`.`data_operazione`), month(`bilancio_entrate`.`data_operazione`), `bilancio_entrate`.`id_gruppo_transazione` ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_mensili_riepilogo`
--
DROP TABLE IF EXISTS `v_mensili_riepilogo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_mensili_riepilogo`  AS SELECT `v_mensili_spese`.`id_utente` AS `id_utente`, `v_mensili_spese`.`descrizione` AS `descrizione`, `v_mensili_spese`.`attivo` AS `attivo`, `v_mensili_spese`.`id_gruppo_transazione` AS `id_gruppo_transazione`, `v_mensili_spese`.`totale_speso` AS `totale_speso`, `v_mensili_entrate`.`totale_entrato` AS `totale_entrato`, (ifnull(`v_mensili_entrate`.`totale_entrato`,0) + ifnull(`v_mensili_spese`.`totale_speso`,0)) AS `differenza`, `v_mensili_spese`.`ultima_operazione` AS `ultima_operazione`, `v_mensili_spese`.`anno` AS `anno`, `v_mensili_spese`.`mese` AS `mese`, str_to_date(concat(`v_mensili_spese`.`anno`,'-',`v_mensili_spese`.`mese`,'-01'),'%Y-%m-%d') AS `data_anno_mese` FROM (`v_mensili_spese` left join `v_mensili_entrate` on(((`v_mensili_spese`.`anno` = `v_mensili_entrate`.`anno`) and (`v_mensili_spese`.`mese` = `v_mensili_entrate`.`mese`) and (`v_mensili_spese`.`id_gruppo_transazione` = `v_mensili_entrate`.`id_gruppo_transazione`) and (`v_mensili_spese`.`id_utente` = `v_mensili_entrate`.`id_utente`)))) ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_mensili_spese`
--
DROP TABLE IF EXISTS `v_mensili_spese`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_mensili_spese`  AS SELECT `bilancio_uscite`.`id_utente` AS `id_utente`, `bilancio_gruppi_transazione`.`descrizione` AS `descrizione`, `bilancio_gruppi_transazione`.`attivo` AS `attivo`, `bilancio_gruppi_transazione`.`id_gruppo_transazione` AS `id_gruppo_transazione`, sum(`bilancio_uscite`.`importo`) AS `totale_speso`, 0 AS `totale_entrato`, max(`bilancio_uscite`.`data_operazione`) AS `ultima_operazione`, year(`bilancio_uscite`.`data_operazione`) AS `anno`, month(`bilancio_uscite`.`data_operazione`) AS `mese` FROM (`bilancio_uscite` left join `bilancio_gruppi_transazione` on((`bilancio_gruppi_transazione`.`id_gruppo_transazione` = `bilancio_uscite`.`id_gruppo_transazione`))) GROUP BY `bilancio_uscite`.`id_utente`, year(`bilancio_uscite`.`data_operazione`), month(`bilancio_uscite`.`data_operazione`), `bilancio_uscite`.`id_gruppo_transazione` ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_movimenti_generali`
--
DROP TABLE IF EXISTS `v_movimenti_generali`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_movimenti_generali`  AS SELECT 'entrata' AS `tipo`, `bilancio_entrate`.`id_gruppo_transazione` AS `id_gruppo_transazione`, `bilancio_entrate`.`mezzo` AS `mezzo`, `bilancio_entrate`.`descrizione_operazione` AS `descrizione_operazione`, `bilancio_entrate`.`descrizione_extra` AS `descrizione_extra`, `bilancio_entrate`.`importo` AS `importo`, `bilancio_entrate`.`data_operazione` AS `data_operazione` FROM `bilancio_entrate` WHERE (`bilancio_entrate`.`id_utente` = 1)union select 'uscita' AS `tipo`,`bilancio_uscite`.`id_gruppo_transazione` AS `id_gruppo_transazione`,`bilancio_uscite`.`mezzo` AS `mezzo`,`bilancio_uscite`.`descrizione_operazione` AS `descrizione_operazione`,`bilancio_uscite`.`descrizione_extra` AS `descrizione_extra`,`bilancio_uscite`.`importo` AS `importo`,`bilancio_uscite`.`data_operazione` AS `data_operazione` from `bilancio_uscite` where (`bilancio_uscite`.`id_utente` = 1) union select (case when (`movimenti_poste`.`addebito` > 0) then 'uscita' else 'entrata' end) AS `tipo`,`movimenti_poste`.`id_gruppo_transazione` AS `id_gruppo_transazione`,'poste' AS `mezzo`,`movimenti_poste`.`descrizione` AS `descrizione`,'' AS `descrizione_extra`,(case when (`movimenti_poste`.`addebito` > 0) then (-(1) * `movimenti_poste`.`addebito`) else `movimenti_poste`.`accredito` end) AS `Name_exp_20`,`movimenti_poste`.`data_valuta` AS `data_valuta` from `movimenti_poste` union select (case when (`movimenti_revolut`.`type` = 'CARD_PAYMENT') then 'uscita' when (`movimenti_revolut`.`type` = 'TOPUP') then 'entrata' else 'uscita' end) AS `tipo`,`movimenti_revolut`.`id_gruppo_transazione` AS `id_gruppo_transazione`,'revolut' AS `mezzo`,`movimenti_revolut`.`description` AS `description`,`movimenti_revolut`.`descrizione_extra` AS `descrizione_extra`,`movimenti_revolut`.`amount` AS `amount`,`movimenti_revolut`.`completed_date` AS `completed_date` from `movimenti_revolut` where ((not((`movimenti_revolut`.`description` like 'To EUR%'))) and (`movimenti_revolut`.`completed_date` is not null)) union select (case when (`movimenti_hype`.`importo` < 0) then 'uscita' else 'entrata' end) AS `tipo`,`movimenti_hype`.`id_gruppo_transazione` AS `id_gruppo_transazione`,'hype' AS `mezzo`,concat(`movimenti_hype`.`nome`,'<br>',`movimenti_hype`.`descrizione`) AS `descrizione`,'' AS `descrizione_extra`,`movimenti_hype`.`importo` AS `importo`,`movimenti_hype`.`data_operazione` AS `data_valuta` from `movimenti_hype`  ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_movimenti_generali_anno_mese`
--
DROP TABLE IF EXISTS `v_movimenti_generali_anno_mese`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_movimenti_generali_anno_mese`  AS SELECT 'entrata' AS `tipo`, `bilancio_entrate`.`id_gruppo_transazione` AS `id_gruppo_transazione`, `bg`.`descrizione` AS `gruppo`, `bilancio_entrate`.`mezzo` AS `mezzo`, `bilancio_entrate`.`descrizione_operazione` AS `descrizione_operazione`, `bilancio_entrate`.`descrizione_extra` AS `descrizione_extra`, `bilancio_entrate`.`importo` AS `importo`, year(`bilancio_entrate`.`data_operazione`) AS `anno`, month(`bilancio_entrate`.`data_operazione`) AS `mese`, `bilancio_entrate`.`data_operazione` AS `data_operazione` FROM (`bilancio_entrate` left join `bilancio_gruppi_transazione` `bg` on((`bg`.`id_gruppo_transazione` = `bilancio_entrate`.`id_gruppo_transazione`))) WHERE (`bilancio_entrate`.`id_utente` = 1)union select 'uscita' AS `tipo`,`bilancio_uscite`.`id_gruppo_transazione` AS `id_gruppo_transazione`,`bg`.`descrizione` AS `gruppo`,`bilancio_uscite`.`mezzo` AS `mezzo`,`bilancio_uscite`.`descrizione_operazione` AS `descrizione_operazione`,`bilancio_uscite`.`descrizione_extra` AS `descrizione_extra`,`bilancio_uscite`.`importo` AS `importo`,year(`bilancio_uscite`.`data_operazione`) AS `anno`,month(`bilancio_uscite`.`data_operazione`) AS `mese`,`bilancio_uscite`.`data_operazione` AS `data_operazione` from (`bilancio_uscite` left join `bilancio_gruppi_transazione` `bg` on((`bg`.`id_gruppo_transazione` = `bilancio_uscite`.`id_gruppo_transazione`))) where (`bilancio_uscite`.`id_utente` = 1) union select (case when (`movimenti_poste`.`addebito` > 0) then 'uscita' else 'entrata' end) AS `tipo`,`movimenti_poste`.`id_gruppo_transazione` AS `id_gruppo_transazione`,`bg`.`descrizione` AS `gruppo`,'poste' AS `mezzo`,`movimenti_poste`.`descrizione` AS `descrizione`,'' AS `descrizione_extra`,(case when (`movimenti_poste`.`addebito` > 0) then (-(1) * `movimenti_poste`.`addebito`) else `movimenti_poste`.`accredito` end) AS `Name_exp_20`,year(`movimenti_poste`.`data_valuta`) AS `anno`,month(`movimenti_poste`.`data_valuta`) AS `mese`,`movimenti_poste`.`data_valuta` AS `data_valuta` from (`movimenti_poste` left join `bilancio_gruppi_transazione` `bg` on((`bg`.`id_gruppo_transazione` = `movimenti_poste`.`id_gruppo_transazione`))) union select (case when (`movimenti_revolut`.`type` = 'CARD_PAYMENT') then 'uscita' when (`movimenti_revolut`.`type` = 'TOPUP') then 'entrata' else 'uscita' end) AS `tipo`,`movimenti_revolut`.`id_gruppo_transazione` AS `id_gruppo_transazione`,`bg`.`descrizione` AS `gruppo`,'revolut' AS `mezzo`,`movimenti_revolut`.`description` AS `description`,`movimenti_revolut`.`descrizione_extra` AS `descrizione_extra`,`movimenti_revolut`.`amount` AS `amount`,year(`movimenti_revolut`.`completed_date`) AS `anno`,month(`movimenti_revolut`.`completed_date`) AS `mese`,`movimenti_revolut`.`completed_date` AS `completed_date` from (`movimenti_revolut` left join `bilancio_gruppi_transazione` `bg` on((`bg`.`id_gruppo_transazione` = `movimenti_revolut`.`id_gruppo_transazione`))) where ((not((`movimenti_revolut`.`description` like 'To EUR%'))) and (`movimenti_revolut`.`completed_date` is not null)) union select (case when (`movimenti_hype`.`importo` < 0) then 'uscita' else 'entrata' end) AS `tipo`,`movimenti_hype`.`id_gruppo_transazione` AS `id_gruppo_transazione`,`bg`.`descrizione` AS `gruppo`,'hype' AS `mezzo`,concat(`movimenti_hype`.`nome`,'<br>',`movimenti_hype`.`descrizione`) AS `descrizione`,'' AS `descrizione_extra`,`movimenti_hype`.`importo` AS `importo`,year(`movimenti_hype`.`data_operazione`) AS `anno`,month(`movimenti_hype`.`data_operazione`) AS `mese`,`movimenti_hype`.`data_operazione` AS `data_valuta` from (`movimenti_hype` left join `bilancio_gruppi_transazione` `bg` on((`bg`.`id_gruppo_transazione` = `movimenti_hype`.`id_gruppo_transazione`)))  ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_movimenti_hype`
--
DROP TABLE IF EXISTS `v_movimenti_hype`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_movimenti_hype`  AS SELECT `movimenti_hype`.`id_movimento_hype` AS `id_movimento_hype`, `movimenti_hype`.`id_gruppo_transazione` AS `id_gruppo_transazione`, `bilancio_gruppi_transazione`.`descrizione` AS `descrizione_gruppo`, `movimenti_hype`.`data_operazione` AS `data_operazione`, `movimenti_hype`.`data_contabile` AS `data_contabile`, `movimenti_hype`.`tipologia` AS `tipologia`, `movimenti_hype`.`nome` AS `nome`, `movimenti_hype`.`descrizione` AS `descrizione`, `movimenti_hype`.`importo` AS `importo`, `movimenti_hype`.`id_etichetta` AS `id_etichetta`, group_concat(`bilancio_etichette`.`descrizione` separator ',') AS `etichette` FROM (((`movimenti_hype` left join `bilancio_etichette2operazioni` on(((`bilancio_etichette2operazioni`.`tabella_operazione` = `movimenti_hype`.`tabella_operazione`) and (`bilancio_etichette2operazioni`.`id_tabella` = `movimenti_hype`.`id_movimento_hype`)))) left join `bilancio_etichette` on((`bilancio_etichette2operazioni`.`id_etichetta` = `bilancio_etichette`.`id_etichetta`))) left join `bilancio_gruppi_transazione` on((`bilancio_gruppi_transazione`.`id_gruppo_transazione` = `movimenti_hype`.`id_gruppo_transazione`))) GROUP BY `movimenti_hype`.`id_movimento_hype` ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_movimenti_revolut`
--
DROP TABLE IF EXISTS `v_movimenti_revolut`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_movimenti_revolut`  AS SELECT `movimenti_revolut`.`id_movimento_revolut` AS `id_movimento_revolut`, `movimenti_revolut`.`id_caricamento` AS `id_caricamento`, `movimenti_revolut`.`id_gruppo_transazione` AS `id_gruppo_transazione`, `bilancio_gruppi_transazione`.`descrizione` AS `descrizione_gruppo`, `bilancio_gruppi_transazione`.`id_categoria` AS `id_categoria`, `movimenti_revolut`.`id_salvadanaio` AS `id_salvadanaio`, `movimenti_revolut`.`type` AS `type`, `movimenti_revolut`.`product` AS `product`, `movimenti_revolut`.`started_date` AS `started_date`, `movimenti_revolut`.`completed_date` AS `completed_date`, `movimenti_revolut`.`description` AS `description`, `movimenti_revolut`.`amount` AS `amount`, `movimenti_revolut`.`note` AS `note`, `movimenti_revolut`.`descrizione_extra` AS `descrizione_extra`, `movimenti_revolut`.`tabella_operazione` AS `tabella_operazione`, `movimenti_revolut`.`id_etichetta` AS `id_etichetta`, group_concat(`bilancio_etichette`.`descrizione` separator ',') AS `etichette` FROM (((`movimenti_revolut` left join `bilancio_etichette2operazioni` on(((`bilancio_etichette2operazioni`.`tabella_operazione` = `movimenti_revolut`.`tabella_operazione`) and (`bilancio_etichette2operazioni`.`id_tabella` = `movimenti_revolut`.`id_movimento_revolut`)))) left join `bilancio_etichette` on((`bilancio_etichette2operazioni`.`id_etichetta` = `bilancio_etichette`.`id_etichetta`))) left join `bilancio_gruppi_transazione` on((`bilancio_gruppi_transazione`.`id_gruppo_transazione` = `movimenti_revolut`.`id_gruppo_transazione`))) WHERE ((not((`movimenti_revolut`.`description` like 'To EUR%'))) AND (not((`movimenti_revolut`.`description` like 'Closing Transaction%'))) AND (not((`movimenti_revolut`.`description` like 'Interest earned%'))) AND (not((`movimenti_revolut`.`description` like 'Accredita EUR %'))) AND (not((`movimenti_revolut`.`description` like 'From Conto deposito senza vincoli'))) AND (not((`movimenti_revolut`.`description` like 'Prelievo da Pocket%'))) AND (`movimenti_revolut`.`completed_date` is not null)) GROUP BY `movimenti_revolut`.`id_movimento_revolut` ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_movimenti_revolut_filtrati`
--
DROP TABLE IF EXISTS `v_movimenti_revolut_filtrati`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_movimenti_revolut_filtrati`  AS SELECT `movimenti_revolut`.`id_movimento_revolut` AS `id_movimento_revolut`, `movimenti_revolut`.`id_gruppo_transazione` AS `id_gruppo_transazione`, `movimenti_revolut`.`id_salvadanaio` AS `id_salvadanaio`, `movimenti_revolut`.`type` AS `type`, `movimenti_revolut`.`product` AS `product`, `movimenti_revolut`.`started_date` AS `started_date`, `movimenti_revolut`.`completed_date` AS `completed_date`, `movimenti_revolut`.`description` AS `description`, `movimenti_revolut`.`amount` AS `amount`, `movimenti_revolut`.`note` AS `note`, `movimenti_revolut`.`descrizione_extra` AS `descrizione_extra`, `movimenti_revolut`.`tabella_operazione` AS `tabella_operazione`, `movimenti_revolut`.`id_etichetta` AS `id_etichetta` FROM `movimenti_revolut` WHERE ((not((`movimenti_revolut`.`description` like 'To EUR%'))) AND (not((`movimenti_revolut`.`description` like 'Closing Transaction%'))) AND (not((`movimenti_revolut`.`description` like 'Interest earned%'))) AND (not((`movimenti_revolut`.`description` like 'Accredita EUR %'))) AND (not((`movimenti_revolut`.`description` like 'From Conto deposito senza vincoli'))) AND (not((`movimenti_revolut`.`description` like 'Prelievo da Pocket%'))) AND (`movimenti_revolut`.`completed_date` is not null)) ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_ocr_caricamenti`
--
DROP TABLE IF EXISTS `v_ocr_caricamenti`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_ocr_caricamenti`  AS SELECT `ocr_caricamenti`.`id_caricamento` AS `id_caricamento`, `ocr_caricamenti`.`id_utente` AS `id_utente`, concat(`utenti`.`cognome`,' ',`utenti`.`nome`) AS `utente`, `ocr_caricamenti`.`id_supermercato` AS `id_supermercato`, `ocr_caricamenti`.`data_caricamento` AS `data_caricamento`, `ocr_caricamenti`.`nome_file` AS `nome_file`, `ocr_caricamenti`.`data_scontrino` AS `data_scontrino`, `ocr_caricamenti`.`totale_scontrino` AS `totale_scontrino`, `ocr_caricamenti`.`indirizzo_ip` AS `indirizzo_ip`, `ocr_caricamenti`.`JSON_linee` AS `JSON_linee`, `ocr_caricamenti`.`descrizione` AS `descrizione`, (case when (floor(((`ocr_caricamenti`.`totale_scontrino` + `bilancio_uscite`.`importo`) / 10)) < 0) then 0 else floor(((`ocr_caricamenti`.`totale_scontrino` + `bilancio_uscite`.`importo`) / 10)) end) AS `buoni_utilizzati`, (case when isnull(`bilancio_uscite`.`id_uscita`) then 0 else 1 end) AS `caricato` FROM ((`ocr_caricamenti` left join `utenti` on((`utenti`.`id` = `ocr_caricamenti`.`id_utente`))) left join `bilancio_uscite` on((`ocr_caricamenti`.`id_caricamento` = `bilancio_uscite`.`id_caricamento`))) ;

-- --------------------------------------------------------

--
-- Struttura per vista `v_ocr_caricamenti_ricerca`
--
DROP TABLE IF EXISTS `v_ocr_caricamenti_ricerca`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_ocr_caricamenti_ricerca`  AS SELECT `ocr_caricamenti`.`id_caricamento` AS `id_caricamento`, `ocr_caricamenti`.`id_utente` AS `id_utente`, concat(convert(date_format(`ocr_caricamenti`.`data_scontrino`,'%d/%m/%Y') using utf8mb4),(case when (ifnull(`ocr_caricamenti`.`descrizione`,'') <> '') then concat('-',`ocr_caricamenti`.`descrizione`) else '' end)) AS `fullname`, `ocr_caricamenti`.`data_caricamento` AS `data_caricamento`, `ocr_caricamenti`.`nome_file` AS `nome_file`, `ocr_caricamenti`.`descrizione` AS `descrizione`, (case when isnull(`bilancio_uscite`.`id_uscita`) then 0 else 1 end) AS `caricato` FROM (`ocr_caricamenti` left join `bilancio_uscite` on((`ocr_caricamenti`.`id_caricamento` = `bilancio_uscite`.`id_caricamento`))) ;

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
-- Struttura per vista `v_totali_alternative`
--
DROP TABLE IF EXISTS `v_totali_alternative`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Sql1203781`@`%` SQL SECURITY DEFINER VIEW `v_totali_alternative`  AS SELECT `vt`.`id_viaggio` AS `id_viaggio`, `alt`.`id_viaggio_alternativa` AS `id_viaggio_alternativa`, `alt`.`breve_descrizione` AS `breve_descrizione`, sum(((((((((coalesce(`vt`.`distanza_km`,0) * coalesce(`vt`.`consumo_litri_100km`,0)) / 100) * coalesce(`vt`.`prezzo_carburante_eur_litro`,0)) + coalesce(`vt`.`pedaggi_eur`,0)) + coalesce(`vt`.`costo_traghetto_eur`,0)) + coalesce(`vt`.`costo_volo_eur`,0)) + coalesce(`vt`.`costo_noleggio_eur`,0)) + coalesce(`vt`.`altri_costi_eur`,0))) AS `totale_trasporti`, (select coalesce(sum(((to_days(`va`.`data_checkout`) - to_days(`va`.`data_checkin`)) * coalesce(`va`.`costo_notte_eur`,0))),0) from `viaggi_alloggi` `va` where ((`va`.`id_viaggio` = `vt`.`id_viaggio`) and (`va`.`id_viaggio_alternativa` = `vt`.`id_viaggio_alternativa`))) AS `totale_alloggi`, (select coalesce(sum(`vp`.`costo_medio_eur`),0) from `viaggi_pasti` `vp` where ((`vp`.`id_viaggio` = `vt`.`id_viaggio`) and (`vp`.`id_viaggio_alternativa` = `vt`.`id_viaggio_alternativa`))) AS `totale_pasti`, (select coalesce(sum(`vac`.`importo_eur`),0) from `viaggi_altri_costi` `vac` where ((`vac`.`id_viaggio` = `vt`.`id_viaggio`) and (`vac`.`id_viaggio_alternativa` = `vt`.`id_viaggio_alternativa`))) AS `totale_altri_costi`, (((sum(((((((((coalesce(`vt`.`distanza_km`,0) * coalesce(`vt`.`consumo_litri_100km`,0)) / 100) * coalesce(`vt`.`prezzo_carburante_eur_litro`,0)) + coalesce(`vt`.`pedaggi_eur`,0)) + coalesce(`vt`.`costo_traghetto_eur`,0)) + coalesce(`vt`.`costo_volo_eur`,0)) + coalesce(`vt`.`costo_noleggio_eur`,0)) + coalesce(`vt`.`altri_costi_eur`,0))) + (select coalesce(sum(((to_days(`va`.`data_checkout`) - to_days(`va`.`data_checkin`)) * coalesce(`va`.`costo_notte_eur`,0))),0) from `viaggi_alloggi` `va` where ((`va`.`id_viaggio` = `vt`.`id_viaggio`) and (`va`.`id_viaggio_alternativa` = `vt`.`id_viaggio_alternativa`)))) + (select coalesce(sum(`vp`.`costo_medio_eur`),0) from `viaggi_pasti` `vp` where ((`vp`.`id_viaggio` = `vt`.`id_viaggio`) and (`vp`.`id_viaggio_alternativa` = `vt`.`id_viaggio_alternativa`)))) + (select coalesce(sum(`vac`.`importo_eur`),0) from `viaggi_altri_costi` `vac` where ((`vac`.`id_viaggio` = `vt`.`id_viaggio`) and (`vac`.`id_viaggio_alternativa` = `vt`.`id_viaggio_alternativa`)))) AS `totale_viaggio` FROM (`viaggi_tratte` `vt` join `viaggi_alternative` `alt` on((`vt`.`id_viaggio_alternativa` = `alt`.`id_viaggio_alternativa`))) GROUP BY `vt`.`id_viaggio`, `vt`.`id_viaggio_alternativa` ;

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
-- Limiti per la tabella `film`
--
ALTER TABLE `film`
  ADD CONSTRAINT `fk_film_gruppo` FOREIGN KEY (`id_gruppo`) REFERENCES `film_gruppi` (`id_gruppo`) ON DELETE SET NULL;

--
-- Limiti per la tabella `film2generi`
--
ALTER TABLE `film2generi`
  ADD CONSTRAINT `fk_film2generi_film` FOREIGN KEY (`id_film`) REFERENCES `film` (`id_film`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_film2generi_genere` FOREIGN KEY (`id_genere`) REFERENCES `film_generi` (`id_genere`) ON DELETE CASCADE;

--
-- Limiti per la tabella `film2liste`
--
ALTER TABLE `film2liste`
  ADD CONSTRAINT `fk_film2liste_film` FOREIGN KEY (`id_film`) REFERENCES `film` (`id_film`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_film2liste_lista` FOREIGN KEY (`id_lista`) REFERENCES `film_liste` (`id_lista`) ON DELETE CASCADE;

--
-- Limiti per la tabella `film_commenti`
--
ALTER TABLE `film_commenti`
  ADD CONSTRAINT `fk_film_commenti_film` FOREIGN KEY (`id_film`) REFERENCES `film` (`id_film`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_film_commenti_utente` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `film_liste`
--
ALTER TABLE `film_liste`
  ADD CONSTRAINT `fk_film_liste_utenti` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `film_utenti`
--
ALTER TABLE `film_utenti`
  ADD CONSTRAINT `fk_film_utenti_film` FOREIGN KEY (`id_film`) REFERENCES `film` (`id_film`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_film_utenti_utenti` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`) ON DELETE CASCADE;

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
-- Limiti per la tabella `mezzi_eventi`
--
ALTER TABLE `mezzi_eventi`
  ADD CONSTRAINT `fk_eventi_mezzo` FOREIGN KEY (`id_mezzo`) REFERENCES `mezzi` (`id_mezzo`),
  ADD CONSTRAINT `fk_eventi_tipo` FOREIGN KEY (`id_tipo_evento`) REFERENCES `mezzi_eventi_tipi` (`id_tipo_evento`);

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
  ADD CONSTRAINT `fk_revolut_caricamento` FOREIGN KEY (`id_caricamento`) REFERENCES `ocr_caricamenti` (`id_caricamento`) ON DELETE SET NULL,
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

--
-- Limiti per la tabella `viaggi`
--
ALTER TABLE `viaggi`
  ADD CONSTRAINT `viaggi_ibfk_1` FOREIGN KEY (`id_luogo`) REFERENCES `viaggi_luoghi` (`id_luogo`);

--
-- Limiti per la tabella `viaggi2caricamenti`
--
ALTER TABLE `viaggi2caricamenti`
  ADD CONSTRAINT `viaggi2caricamenti_ibfk_1` FOREIGN KEY (`id_viaggio`) REFERENCES `viaggi` (`id_viaggio`) ON DELETE CASCADE,
  ADD CONSTRAINT `viaggi2caricamenti_ibfk_2` FOREIGN KEY (`id_caricamento`) REFERENCES `ocr_caricamenti` (`id_caricamento`);

--
-- Limiti per la tabella `viaggi2etichette`
--
ALTER TABLE `viaggi2etichette`
  ADD CONSTRAINT `viaggi2etichette_ibfk_1` FOREIGN KEY (`id_viaggio`) REFERENCES `viaggi` (`id_viaggio`) ON DELETE CASCADE,
  ADD CONSTRAINT `viaggi2etichette_ibfk_2` FOREIGN KEY (`id_etichetta`) REFERENCES `viaggi_etichette` (`id_etichetta`) ON DELETE CASCADE;

--
-- Limiti per la tabella `viaggi_alloggi`
--
ALTER TABLE `viaggi_alloggi`
  ADD CONSTRAINT `fk_viaggi_alloggi_alternativa` FOREIGN KEY (`id_viaggio_alternativa`) REFERENCES `viaggi_alternative` (`id_viaggio_alternativa`),
  ADD CONSTRAINT `viaggi_alloggi_ibfk_1` FOREIGN KEY (`id_viaggio`) REFERENCES `viaggi` (`id_viaggio`);

--
-- Limiti per la tabella `viaggi_alloggi_alternative`
--
ALTER TABLE `viaggi_alloggi_alternative`
  ADD CONSTRAINT `fk_vaa_alloggio` FOREIGN KEY (`id_alloggio`) REFERENCES `viaggi_alloggi` (`id_alloggio`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vaa_alternativa` FOREIGN KEY (`id_viaggio_alternativa`) REFERENCES `viaggi_alternative` (`id_viaggio_alternativa`) ON DELETE CASCADE;

--
-- Limiti per la tabella `viaggi_alternative`
--
ALTER TABLE `viaggi_alternative`
  ADD CONSTRAINT `viaggi_alternative_ibfk_1` FOREIGN KEY (`id_viaggio`) REFERENCES `viaggi` (`id_viaggio`);

--
-- Limiti per la tabella `viaggi_altri_costi`
--
ALTER TABLE `viaggi_altri_costi`
  ADD CONSTRAINT `viaggi_altri_costi_ibfk_1` FOREIGN KEY (`id_viaggio`) REFERENCES `viaggi` (`id_viaggio`),
  ADD CONSTRAINT `viaggi_altri_costi_ibfk_2` FOREIGN KEY (`id_viaggio_alternativa`) REFERENCES `viaggi_alternative` (`id_viaggio_alternativa`);

--
-- Limiti per la tabella `viaggi_altri_costi_alternative`
--
ALTER TABLE `viaggi_altri_costi_alternative`
  ADD CONSTRAINT `fk_vaca_alternativa` FOREIGN KEY (`id_viaggio_alternativa`) REFERENCES `viaggi_alternative` (`id_viaggio_alternativa`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vaca_costo` FOREIGN KEY (`id_costo`) REFERENCES `viaggi_altri_costi` (`id_costo`) ON DELETE CASCADE;

--
-- Limiti per la tabella `viaggi_checklist`
--
ALTER TABLE `viaggi_checklist`
  ADD CONSTRAINT `viaggi_checklist_ibfk_1` FOREIGN KEY (`id_viaggio`) REFERENCES `viaggi` (`id_viaggio`) ON DELETE CASCADE,
  ADD CONSTRAINT `viaggi_checklist_ibfk_2` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`) ON DELETE SET NULL;

--
-- Limiti per la tabella `viaggi_checklist_messaggi`
--
ALTER TABLE `viaggi_checklist_messaggi`
  ADD CONSTRAINT `viaggi_checklist_messaggi_ibfk_1` FOREIGN KEY (`id_checklist`) REFERENCES `viaggi_checklist` (`id_checklist`) ON DELETE CASCADE,
  ADD CONSTRAINT `viaggi_checklist_messaggi_ibfk_2` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `viaggi_commenti`
--
ALTER TABLE `viaggi_commenti`
  ADD CONSTRAINT `fk_vcommenti_alternativa` FOREIGN KEY (`id_viaggio_alternativa`) REFERENCES `viaggi_alternative` (`id_viaggio_alternativa`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vcommenti_autore` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`),
  ADD CONSTRAINT `fk_vcommenti_viaggio` FOREIGN KEY (`id_viaggio`) REFERENCES `viaggi` (`id_viaggio`) ON DELETE CASCADE;

--
-- Limiti per la tabella `viaggi_condivisioni`
--
ALTER TABLE `viaggi_condivisioni`
  ADD CONSTRAINT `fk_vc_autore` FOREIGN KEY (`creato_da`) REFERENCES `utenti` (`id`),
  ADD CONSTRAINT `fk_vc_viaggio` FOREIGN KEY (`id_viaggio`) REFERENCES `viaggi` (`id_viaggio`) ON DELETE CASCADE;

--
-- Limiti per la tabella `viaggi_condivisioni_alternative`
--
ALTER TABLE `viaggi_condivisioni_alternative`
  ADD CONSTRAINT `fk_vca_alternativa` FOREIGN KEY (`id_viaggio_alternativa`) REFERENCES `viaggi_alternative` (`id_viaggio_alternativa`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vca_condivisione` FOREIGN KEY (`id_condivisione`) REFERENCES `viaggi_condivisioni` (`id_condivisione`) ON DELETE CASCADE;

--
-- Limiti per la tabella `viaggi_feedback`
--
ALTER TABLE `viaggi_feedback`
  ADD CONSTRAINT `viaggi_feedback_ibfk_1` FOREIGN KEY (`id_viaggio`) REFERENCES `viaggi` (`id_viaggio`) ON DELETE CASCADE,
  ADD CONSTRAINT `viaggi_feedback_ibfk_2` FOREIGN KEY (`id_utente`) REFERENCES `utenti` (`id`);

--
-- Limiti per la tabella `viaggi_luogo_foto`
--
ALTER TABLE `viaggi_luogo_foto`
  ADD CONSTRAINT `fk_vlf_luogo` FOREIGN KEY (`id_luogo`) REFERENCES `viaggi_luoghi` (`id_luogo`) ON DELETE CASCADE;

--
-- Limiti per la tabella `viaggi_pasti`
--
ALTER TABLE `viaggi_pasti`
  ADD CONSTRAINT `viaggi_pasti_ibfk_1` FOREIGN KEY (`id_viaggio`) REFERENCES `viaggi` (`id_viaggio`),
  ADD CONSTRAINT `viaggi_pasti_ibfk_2` FOREIGN KEY (`id_viaggio_alternativa`) REFERENCES `viaggi_alternative` (`id_viaggio_alternativa`);

--
-- Limiti per la tabella `viaggi_pasti_alternative`
--
ALTER TABLE `viaggi_pasti_alternative`
  ADD CONSTRAINT `fk_vpa_alternativa` FOREIGN KEY (`id_viaggio_alternativa`) REFERENCES `viaggi_alternative` (`id_viaggio_alternativa`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vpa_pasto` FOREIGN KEY (`id_pasto`) REFERENCES `viaggi_pasti` (`id_pasto`) ON DELETE CASCADE;

--
-- Limiti per la tabella `viaggi_tratte`
--
ALTER TABLE `viaggi_tratte`
  ADD CONSTRAINT `fk_viaggi_tratte_alternativa` FOREIGN KEY (`id_viaggio_alternativa`) REFERENCES `viaggi_alternative` (`id_viaggio_alternativa`),
  ADD CONSTRAINT `viaggi_tratte_ibfk_1` FOREIGN KEY (`id_viaggio`) REFERENCES `viaggi` (`id_viaggio`);

--
-- Limiti per la tabella `viaggi_tratte_alternative`
--
ALTER TABLE `viaggi_tratte_alternative`
  ADD CONSTRAINT `fk_vta_alternativa` FOREIGN KEY (`id_viaggio_alternativa`) REFERENCES `viaggi_alternative` (`id_viaggio_alternativa`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vta_tratta` FOREIGN KEY (`id_tratta`) REFERENCES `viaggi_tratte` (`id_tratta`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
