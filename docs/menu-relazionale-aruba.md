# PR 1 · Menù relazionale su Aruba

## Preparazione

1. Prova la release su un database e un sito di staging con PHP 8.3, estensioni `mysqli`, `mbstring` e `curl`, e MySQL 5.6. La suite `tests/menu_relazionale/run.sh` usa MySQL 5.6.51 e verifica i flussi su dati sintetici nella GitHub Action **Menù relazionale PHP 8.3 e MySQL 5.6**.
2. Prima della finestra di manutenzione, esporta lo schema e i dati MySQL, copia la release attiva e salva separatamente `config/menu_cene/`, inclusi i JSON di ciascuna famiglia. Conserva le configurazioni e le credenziali fuori dall’archivio pubblico. Verifica il ripristino delle copie in staging.
3. In phpMyAdmin seleziona il database dell’applicazione. Controlla che `famiglie`, `menu_cene_settimanale` e `lista_spesa` usino InnoDB e chiavi primarie `INT` compatibili con le nuove chiavi esterne. Controlla che le otto tabelle elencate nella migrazione non esistano. Se esistono già tabelle, in particolare dopo un’installazione parziale, confronta schema e dati prima di proseguire.

## Installazione e attivazione

1. Durante la manutenzione, importa una sola volta `sql/migrations/20261124_menu_relazionale.sql` in phpMyAdmin. Applica lo schema **prima** di distribuire il nuovo PHP. MySQL 5.6 esegue commit impliciti per il DDL. Se un’istruzione fallisce, conserva l’errore e verifica quali tabelle sono state create. Ripristina lo schema precedente o completa le sole istruzioni mancanti dopo aver confrontato le definizioni.
2. Distribuisci la nuova release in `/Gestionale25/`. Mantieni i file di configurazione esistenti. Verifica che Aruba impedisca l’accesso HTTP diretto ai file privati, inclusi i JSON, e che API e MCP usino HTTPS. Per MCP, l’URL REST predefinito resta quello della produzione. In staging puoi impostare `mcp_rest_base_url` nel file privato `includes/api_v1_config.php`, con un URL HTTPS di staging e un certificato valido.
3. Per ogni famiglia, selezionala come amministratore, apri **Configurazione → Menù cene → Migrazione iniziale** e controlla ciclo, piatti, occorrenze e ricette. Conferma la migrazione. Il master resta inattivo e le letture continuano a usare la sorgente precedente. Se manca il JSON del master viene usato il master predefinito. Un secondo trasferimento per la stessa famiglia viene rifiutato.
4. Apri **Verifica e attiva il master** e conferma. Il server confronta ciclo, testo originale, riferimenti dei piatti e ricette con la sorgente. Dopo l’attivazione, master e ricette si modificano dai normali editor. Per verificare il risultato, controlla una settimana futura e una già salvata. Le previsioni future usano il nuovo master e le settimane salvate conservano i propri piatti. Verifica in staging anche letture e modifiche REST e MCP con la credenziale della famiglia prevista.
5. Conserva i JSON originali come riferimento per il rollback. La migrazione iniziale è l’unico percorso per trasferire questi file.

## Rollback per famiglia

Metti in manutenzione le modifiche del menù. In phpMyAdmin identifica il master attivo della famiglia e conserva il suo ID e un export delle tabelle relazionali. Sostituisci entrambi i segnaposto con ID verificati e esegui il blocco come singola query in phpMyAdmin. Controlla che `righe_modificate` valga 1. Un risultato di 0 lascia i dati invariati e richiede di ricontrollare gli ID.

```sql
START TRANSACTION;
SELECT id_famiglia FROM famiglie WHERE id_famiglia = <ID_FAMIGLIA> FOR UPDATE;
UPDATE menu_cene_master
SET attivo = 0
WHERE id_famiglia = <ID_FAMIGLIA> AND id_master = <ID_MASTER> AND attivo = 1;
SELECT ROW_COUNT() AS righe_modificate;
COMMIT;
```

Le letture tornano al JSON originale o al master predefinito, e il salvataggio del master e delle ricette resta sospeso finché il master relazionale è inattivo. Le modifiche relazionali rimangono nel database. Le settimane salvate e la lista della spesa restano disponibili. Verifica le letture web, REST e MCP per quella famiglia e controlla che le altre famiglie continuino a usare la loro configurazione.

Per riattivare la versione conservata, usa la stessa transazione e lo stesso lock sulla famiglia. Verifica che nessun altro master sia attivo per la famiglia e imposta `attivo = 1` solo per l’ID conservato. Controlla di nuovo master, ricette e settimana esistente.

Per un rollback dell’intera release, rimetti online il pacchetto PHP/JS precedente e mantieni la configurazione privata originale. Conserva le nuove tabelle per eventuale ripristino. Il codice precedente legge il JSON e può riprendere il suo comportamento storico di pulizia delle settimane. Se devi garantire la conservazione di quelle settimane, usa il rollback per famiglia oppure mantieni in manutenzione la generazione fino a quando è disponibile una release correttiva.
