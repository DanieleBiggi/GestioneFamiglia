# Collegamenti finanziari: vacanze, salvadanai, budget, etichette ed eventi

Le tabelle preesistenti sono:
- `viaggi_pianificazione.id_salvadanaio` per la vacanza
- `budget.id_salvadanaio` per le voci Budget
- `eventi_eventi2salvadanai_etichette` per le associazioni etichetta-salvadanaio ed etichetta-evento
- `bilancio_etichette2operazioni` per collegare le etichette ai movimenti (gestito separatamente da `modifica_movimento`).

## Nuovi strumenti MCP
- `elenca_finanze_collegabili`: elenco ID visibili (massimo 200 per categoria).
- `leggi_finanze_collegate`: `{tipo:"vacanza"|"salvadanaio"|"etichetta"|"evento",id:7}`. La lettura della vacanza include il salvadanaio, budget, etichette ed eventi associati.
- `anteprima_collegamento_finanze`: `{payload:{tipo:"etichetta_salvadanaio",azione:"collega",id_etichetta:10,id_salvadanaio:20}}`. **Solo anteprima**, riceve `expected_hash`.
- `modifica_collegamento_finanze`: stessa struttura e `payload.expected_hash`, `confirm:true`, `request_id` univoco. **Solo dopo consenso esplicito dell'utente**.

Tipi di relazione supportati:
- `etichetta_salvadanaio`: `id_etichetta`, `id_salvadanaio`.
- `etichetta_evento`: `id_etichetta`, `id_evento`.
- `budget_salvadanaio`: `id_budget`, `id_salvadanaio`. Modifica solo l'associazione, non `budget.importo`.
- `vacanza_salvadanaio`: `id_viaggio`, `id_salvadanaio`. Richiede scenario già confermato, come nella pagina web. Per scollegare la vacanza, `id_salvadanaio` è facoltativo.

## Sicurezza
Solo famiglia legacy 1, utente proprietario configurato in `movimenti_user_id`, appartenenza alla famiglia e scope OAuth `family:read` / `family:write`; budget filtrati per famiglia, vacanze tramite `viaggi_api_famiglie`, eventi tramite `eventi_eventi2famiglie`. I salvadanai non hanno `id_famiglia`, per cui sono esposti soltanto quando già associati a un budget o a una vacanza della famiglia. Etichette legacy non hanno una colonna per la famiglia: questa versione non le espone ad altre famiglie.

Tutte le modifiche richiedono anteprima e hash, `X-GF-Request-Id`, transazione SQL. Nessuna migrazione DB o modifica ai preventivi; non cancella movimenti. Scollegamenti di relazioni triple ambigue sono rifiutati: usare la pagina web.

## Attenzione sui consuntivi
`leggi_finanze_collegate` restituisce per ogni etichetta i movimenti, inclusi importi parziali ed esclusioni. Non viene calcolato automaticamente un totale delle etichette del salvadanaio: lo stesso movimento potrebbe comparire sotto più etichette. Prima di confrontare spesa effettiva e preventivo vanno deduplicati i movimenti e considerate eventuali quote parziali.

## Test da eseguire dopo il deploy
1. I due strumenti di lettura devono essere non modificanti.
2. Eseguire anteprima su una associazione già esistente e una da creare; nessuna scrittura in anteprima.
3. Provare ID esterni a famiglia e a utente.
4. Verificare doppio invio con stesso request_id e conflitto expected_hash.
5. Verificare che una modifica associativa non alteri importi dei budget, movimenti e saldi.
6. Non eseguire scritture reali senza consenso utente in chat.
