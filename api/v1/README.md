# API v1 Gestione Famiglia (prima versione)

Queste API PHP sono separate dalle pagine esistenti. Non richiedono MCP e non usano la sessione del browser.

## Prima di attivarle

Sono **disabilitate** finché non sono impostate queste variabili d'ambiente sul server:

- `GF_API_TOKEN_SHA256`: SHA-256 esadecimale di un token casuale (almeno 32 caratteri).
- `GF_API_FAMILY_ID`: ID numerico della famiglia autorizzata per la lista spesa.
- `GF_API_ALLOW_VACATIONS=1`: abilita esplicitamente le API vacanze.

**Attenzione:** attualmente le tabelle `viaggi` non hanno `id_famiglia`. Attivare le vacanze SOLO se il catalogo viaggi è condiviso da tutti gli utenti autorizzati. Altrimenti serve prima una migrazione dei dati per associare ogni vacanza a una famiglia.

Non salvare il token in GitHub, JavaScript, URL, chat o file pubblici. Per creare un token: `openssl rand -hex 32`; per ottenere l'hash: `printf '%s' 'TOKEN' | sha256sum`. Configurare l'hash e custodire il token in un gestore di segreti. Su hosting condivisi verificare se è possibile impostare variabili d'ambiente a livello PHP: se non possibile, integrare una configurazione privata NON accessibile dal web, prima del deploy.

Le API richiedono HTTPS e header `Authorization: Bearer <token>`. Non rispondono a chiamate anonime né consentono CORS cross-origin.

## Endpoints implementati

| Metodo | URL | Operazione |
| --- | --- | --- |
| GET | `/api/v1/spesa.php` | Legge la lista della famiglia configurata |
| POST | `/api/v1/spesa.php` | Aggiunge più elementi |
| GET | `/api/v1/vacanze.php` | Ultime 100 vacanze |
| GET | `/api/v1/vacanze.php?id=123` | Vacanza con scenari, tratte, alloggi e altri costi |
| POST | `/api/v1/vacanze.php` | Crea una vacanza con più scenari |

POST accetta JSON con `Content-Type: application/json`; inserire `"dry_run": true` per validare senza scrivere. La creazione usa una transazione per evitare salvataggi parziali.

### Esempio lista spesa

```json
{
  "dry_run": true,
  "items": [
    {"nome": "Pomodori", "quantita": "500 g", "note": "Pasta, lunedì"},
    {"nome": "Latte", "quantita": "1 litro"}
  ]
}
```

### Esempio vacanza

```json
{
  "dry_run": true,
  "titolo": "Creta",
  "data_inizio": "2027-05-08",
  "data_fine": "2027-05-11",
  "persone": 4,
  "breve_descrizione": "Visita a Cnosso",
  "scenari": [
    {
      "nome": "Volo da Bergamo",
      "trasporti": [
        {"tipo": "aereo", "origine": "Bergamo", "destinazione": "Creta", "costo_volo_eur": 367}
      ],
      "alloggi": [{"nome": "Casa in centro", "checkin": "2027-05-08", "checkout": "2027-05-11"}],
      "altri_costi": [{"descrizione": "Museo e altri costi", "importo_eur": 100}]
    }
  ]
}
```

Gli esempi sono illustrativi, non dati da importare automaticamente. Non sommare totali generici alle singole spese se ciò porta a doppio conteggio.

## Limiti di questa prima versione

- La configurazione hosting e il database di produzione **non sono stati modificati**.
- Nessun token è stato creato né pubblicato.
- Nessuna integrazione ChatGPT/Gemini è già collegata.
- Le API POST **aggiungono** record, non fanno ancora upsert e non eliminano duplicati. Evitare retry automatici su timeout.
- Non sono ancora disponibili modifica vacanze, paginazione, idempotency key, rate limiting, gestione dei permessi per singolo utente o revoca centralizzata dei token.
- I costi alloggio supportano il costo per notte; prima dell'uso completo occorre verificare la compatibilità del calcolo totale nella versione del database distribuita.
- Prima di un utilizzo reale servono test su un database di prova e verifiche di sicurezza, comprese le autorizzazioni di tutte le operazioni.
