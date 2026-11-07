# Menù settimanale → lista della spesa tramite ChatGPT

## Nuovo strumento di sola lettura

`leggi_menu_settimanale` richiama `GET /Gestionale25/api/v1/menu_cene.php` con parametro facoltativo `week_start=YYYY-MM-DD` (un lunedì).

- Se omesso, viene scelto il lunedì successivo, in orario Europe/Rome.
- Restituisce **solo** i cinque giorni lunedì-venerdì, con indicazione `origine=salvato` oppure `origine=previsto_master`.
- Se la settimana è già stata modificata/importata in Gestione Famiglia, prevalgono i giorni salvati, senza sovrascriverli.
- Se un giorno non è ancora salvato, restituisce una previsione dal master (o dal fallback di codice), **senza scriverla nel database**.
- Include le istruzioni delle ricette abbinate per nome e segnala piatti senza ricetta. Il ricettario **non contiene ingredienti e quantità strutturati**.
- Legge esclusivamente la famiglia abilitata dall'API già configurata su Aruba. Nessuna nuova tabella SQL necessaria.

## Flusso conversazionale

1. Leggi il menù della settimana richiesta con `leggi_menu_settimanale`.
2. Leggi la spesa attuale con `leggi_lista_spesa`; non considerare automaticamente i prodotti già spuntati come disponibili in dispensa, né i non spuntati come insufficienti.
3. Proponi gli ingredienti mancanti per i piatti indicati. Distingui le quantità stimate dalle preferenze familiari presenti nelle istruzioni delle ricette; chiedi chiarimenti solo se indispensabili (porzioni, dispensa).
4. Mostra quali voci sono già nella lista e quali aggiungeresti. Le corrispondenze per nome devono essere conservative; evita duplicati e chiedi conferma se quantità/formati differiscono.
5. Valida il payload proposto con `anteprima_spesa` (dry-run).
6. **Solo dopo conferma esplicita dell'utente** chiama `crea_spesa`, con `confirm:true` e un `request_id` univoco e stabile per la stessa operazione.

Il tool non legge un inventario di dispensa, non calcola automaticamente grammature e non modifica il menù. Le quantità vanno stimate in modo trasparente e riviste dall'utente.

## Deploy/collaudo

Pubblicare **solo** `api/v1/menu_cene.php` e `api/mcp.php` nella struttura esistente di Aruba. La connessione OAuth e le API spesa non cambiano. Il merge sul repository non pubblica automaticamente i file sul server.

Verifiche:
- `leggi_menu_settimanale` restituisce giorni, origine, ricette e non_associati per la settimana prossima;
- per una settimana già modificata, prevale `salvato`;
- per una settimana mai generata, la API restituisce `previsto_master`, e una seconda lettura non scrive righe;
- la stessa chiave API non deve poter scegliere un'altra famiglia via query;
- menù e lista spesa letti, anteprima e inserimento (solo con conferma) tramite il plugin ChatGPT.

**Non fare merge prima dei test in Aruba.**
