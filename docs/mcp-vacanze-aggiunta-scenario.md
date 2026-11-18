# Aggiungere uno scenario a una vacanza esistente

REST: POST `/api/v1/vacanze_scenario_crea.php`

MCP: `anteprima_aggiunta_scenario` (sola lettura) e `aggiungi_scenario` (scrittura).

1. Leggere la vacanza con `leggi_vacanza`.
2. Anteprima: `{"payload":{"id_viaggio":16,"nome":"Nuova destinazione"}}`.
3. Mostrare all'utente la destinazione e precisare che lo scenario nasce vuoto.
4. Solo dopo conferma esplicita: `aggiungi_scenario` con `confirm:true`, `request_id` univoco e `expected_hash` ottenuto dall'anteprima.
5. Per aggiungere trasporti, alloggi, pasti e altri costi usare gli strumenti esistenti `anteprima_aggiornamento_scenario` e `aggiorna_scenario` con una seconda anteprima/conferma.

## Sicurezza
- Autorizzazione per famiglia tramite `viaggi_api_famiglie` e scope OAuth già gestiti da bootstrap/MCP.
- Validazione nome (non vuoto, massimo 100 caratteri), controllo duplicati senza distinzione maiuscole, massimo 20 scenari.
- Lock transazionale sulla vacanza e `expected_hash` degli scenari esistenti per evitare salvataggi su anteprime obsolete.
- Scrittura idempotente con `X-GF-Request-Id`; nessuna migrazione database.
- Anteprima `dry_run:true` non scrive e non crea lo scenario.
- Non altera scenari esistenti, costi, pagamenti, date o prenotazioni.

## Verifiche consigliate
- Anteprima e creazione su vacanza propria.
- ID vacanza non autorizzato -> 404.
- Nome duplicato -> 409.
- `expected_hash` mancante -> 422, obsoleto -> 409.
- Ripetizione del medesimo `request_id` -> nessun doppio inserimento.
- Inserimento costi nel nuovo scenario tramite API già esistente.
- Test di compatibilità PHP 8.3 / MySQL 5.6 in ambiente di test.
