# Conti condivisi, crediti e Disney+ 2026

## Stato
Modifiche PHP sul branch della PR #468. **Nessun dato reale modificato.**
Non fare merge o deploy prima delle migrazioni e della verifica di regressione.

## Modifiche applicative
- Home: la scorciatoia principale "Aggiungi entrata" ora apre "Aggiungi compensazione". La vecchia pagina `aggiungi_entrata.php` resta disponibile per incassi reali.
- `aggiungi_compensazione.php`: pagina separata per quota, compensazione e saldo, con selezione di etichetta/persona e, quando necessario, di un movimento già etichettato. Controlli autorizzazioni, CSRF e duplicazioni delle attribuzioni precedenti.
- `etichetta_conto.php`: niente modulo integrato; link alla nuova pagina. Il saldo per persona riusa il calcolo di `credito_utente.php` con entrambe le fonti.
- `includes/etichette_utils.php`: le registrazioni del conto condiviso, collegate all'ID persona, sono considerate nei crediti/debiti. Convenzione: segno del conto condiviso opposto al segno legacy della pagina crediti.
- `credito_utente.php`: lista movimenti, aggregati per etichetta, filtro operazioni saldate e possibilità di saldo cumulativo.
- `ajax/update_conti_saldata_bulk.php`: unica transazione per quote preesistenti e conti condivisi.
- `etichetta.php`: lista delle compensazioni extracontabili separata dai movimenti reali.

## Prima del deploy
1. Verificare struttura attuale della tabella `etichette_conti_condivisi` e la presenza di `id_e2o`. Se manca perché è stata eseguita la versione precedente della migrazione originaria, usare SOLO lo script incrementale `20261118_shared_ledger_link_if_prior_migrated.sql`.
2. Eseguire una volta sola `sql/migrations/20261118_conti_compensazioni_crediti.sql`, dopo verifica degli attuali campi per evitare colonne duplicate.
3. Testare accesso, persona selezionata, etichetta selezionata, quote storiche e nuova compensazione in ambiente di test.
4. Assicurarsi che `credito_utente.php`, `etichetta.php` ed `etichetta_conto.php` aprano senza errori SQL.
5. Verificare che una compensazione non crei righe in `bilancio_entrate`, `bilancio_uscite` o `movimenti_revolut`.
6. Verificare che una quota con movimento etichettato già assegnato a quella persona venga rifiutata (evitare doppi saldi).
7. Verificare che saldare insieme una quota tradizionale e una compensazione aggiorni entrambe atomicamente, e che la data sia consultabile in `credito_utente.php`.

## Conversione Disney+ 2026
Solo dopo il deploy/test del nuovo calcolo:
- Script preflight già eseguito: 9 entrate in contanti, 127,50 euro, collegate solo all'etichetta 164. Nove quote non saldate dell'utente 7.
- Prima del cutover salvare il saldo di Bocci in `credito_utente.php` e il saldo di Disney+ in `etichetta_conto.php`.
- Lo script `20261118_disney_compensazioni_cutover_NON_ESEGUIRE.sql` crea tre tabelle di backup, archivia la fonte, registra 9 compensazioni con `id_utente=7` e `id_entrata_origine`, poi rimuove le 9 vecchie entrate e le relative quote, in transazione.
- Non eseguire finché non è stato collaudato su una copia del database, con verifica di idempotenza e ripristino.
- Post-cutover: 0 movimenti con i 9 ID in `bilancio_entrate`; 9 nuove compensazioni per -127,50; saldo di Bocci identico a prima; dati Revolut invariati.
- Script di rollback separato `20261118_disney_compensazioni_rollback_NON_ESEGUIRE.sql`, richiede le tre tabelle di backup e l'assenza di ulteriori modifiche post-cutover.

## Limiti e verifiche aperte
- I nuovi campi del database non sono ancora stati applicati nell'ambiente reale.
- La conversione Disney+ non è stata eseguita.
- Non è stato effettuato un test di integrazione su MySQL reale; la validazione PHP/SQL in ambiente di test è obbligatoria prima del merge.
- Verificare eventuali registrazioni manuali già presenti in `etichette_conti_condivisi` senza `id_utente`: non vengono associate automaticamente a una persona e vanno riconciliate senza assumere nomi.
