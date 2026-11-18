# Lettura movimenti bancari da MCP

## Prerequisiti
- Pubblicare insieme `api/mcp.php` e `api/v1/movimenti.php`.
- Nel file privato `includes/api_v1_config.php` configurare `'movimenti_user_id' => ID_UTENTE` (numero intero), **non** nel repository. Lasciando 0 l'accesso risponde 503.
- L'utente configurato deve essere attivo e appartenere alla famiglia delle API (`utenti2famiglie`).
- Con OAuth, l'identità del token deve coincidere con l'utente proprietario configurato. Nessun parametro esterno permette di selezionare l'utente.
- La sorgente legacy Revolut è condivisa nel gestionale e viene resa disponibile solo per `family_id=1`, in coerenza con `tutti_movimenti.php`. Prima dell'uso condiviso/multifamiglia verificare un modello di proprietà esplicita per Revolut.

## API e strumenti
- `GET /api/v1/movimenti.php?from=2025-09-01&to=2026-02-10&q=Poplidays&limit=50&offset=0`
- `GET /api/v1/movimenti.php?mode=detail&source=bilancio_uscite&id=123`
- MCP `cerca_movimenti` con `from`, `to`, facoltativi `q`, `source`, `limit`, `offset`.
- MCP `leggi_movimento` con `source`, `id`.

I risultati di uscita sono sempre negativi (`-ABS(importo)`), le entrate sempre positive (`ABS(importo)`), anche quando il file importato conteneva gia importi negativi. La fonte Revolut conserva il segno originale, incluse le operazioni di storno. Nessuna scrittura, nessun cambiamento al DB, niente accesso con sessione browser. Le query filtrano per proprietario su `bilancio_entrate` e `bilancio_uscite`, e applicano il perimetro legacy a Revolut. Intervallo massimo 366 giorni per richiesta, massimo 100 risultati/pagina. `has_more` indica solo che la pagina è piena: continuare a paginare finché non restituisce meno di `limit` elementi.

## Verifiche prima del deploy
1. Con `movimenti_user_id=0`, risposta 503 senza dati.
2. Con utente inesistente, inattivo o di altra famiglia, risposta 403.
3. Token OAuth di altro utente: accesso rifiutato.
4. Verificare ricerca per data, descrizione, paginazione, sorgente e dettaglio.
5. Testare una riga `bilancio_uscite.importo=-251.34` (risultato API `-251.34`) e una con `importo=251.34` (ancora `-251.34`); testare analogamente `bilancio_entrate` (sempre positivo). Verificare che le importazioni non creino doppioni.\n6. Trasferimenti fra conti, bonifici verso sé stessi, rimborsi e movimenti di investimento non devono essere trattati come spese vacanza, anche quando il segno e negativo. La ricerca REST non effettua classificazioni automatiche.
6. Non registrare in log risposte, descrizioni o importi dei movimenti.
7. Ricontrollare la privacy del dataset Revolut prima di offrire lo strumento a più utenti.

La riconciliazione automatica email-movimenti non fa parte di questa modifica: i movimenti possono essere letti e confrontati manualmente con le email, mantenendo le fonti separate.
