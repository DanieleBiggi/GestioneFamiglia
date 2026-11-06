# Gestione Famiglia: API REST v1 (PHP 8.3, Aruba Basic Linux)

**Non fare merge/deploy prima dei test.** Nessun file configura o attiva automaticamente le API sul server.

## Setup manuale dopo backup

1. Pubblicare la cartella `api/v1` e la configurazione `includes/.htaccess` (senza il vecchio file di diagnostica).
2. Eseguire sul database, dopo backup, `sql/api_v1_famiglie.sql`, che crea due tabelle: `viaggi_api_famiglie` e `api_v1_richieste`. Non modifica i viaggi già presenti.
3. Verificare su Aruba che `https://www.gestionefamiglia.it/Gestionale25/includes/api_v1_config.php` non sia accessibile pubblicamente (403/404). Non inserire credenziali fino a quando il blocco non è verificato.
4. Sul solo server Aruba copiare `includes/api_v1_config.example.php` in `includes/api_v1_config.php`. Questo file è ignorato da Git: non inserirlo nel repository.
5. Generare un token casuale con `openssl rand -hex 32` (oppure un generatore crittografico affidabile); calcolarne l'hash SHA-256 con `printf '%s' 'TOKEN' | sha256sum`. Conservare il token in un gestore di password; nel PHP salvare **solo l'hash**.
6. Configurare `family_id` con l'ID famiglia corretto, `enabled=>true` e, solo quando la migrazione è completata, `allow_vacations=>true`. Non inserire l'ID famiglia ricevuto da una chiamata: lo decide il server.

Il token va fornito SOLO nell'header HTTPS `X-GF-Api-Key`, mai in query string o JSON, log, screenshot o chat. Aruba è stata verificata con questo header. Non sono previsti header CORS permissivi.

## Endpoint

| Metodo | URL | Funzione |
|---|---|---|
| GET | `/api/v1/spesa.php` | Legge la spesa della famiglia autorizzata |
| POST | `/api/v1/spesa.php` | Aggiunge più elementi alla spesa |
| GET | `/api/v1/vacanze.php` | Legge le vacanze **associate alla famiglia nella tabella API** |
| GET | `/api/v1/vacanze.php?id=123` | Legge scenari, tratte, alloggi e altri costi |
| POST | `/api/v1/vacanze.php` | Crea una vacanza con scenari e costi |

Le vacanze esistenti **non vengono rese visibili automaticamente** tramite API. Per associare un viaggio precedente serve una procedura di migrazione con verifica del proprietario.

## Test sicuri: prima dry_run, poi scrittura

Le richieste POST accettano JSON e `"dry_run": true` per controllare il contenuto senza salvare niente. Per i POST reali serve l'header `X-GF-Request-Id`, un identificativo casuale di 16-64 caratteri nuovo per ogni salvataggio. Ripetendo lo stesso ID la richiesta viene respinta con HTTP 409 anziché creare duplicati.

Esempio PowerShell (sostituire il token SOLO sul proprio PC):

```powershell
$headers = @{ 'X-GF-Api-Key' = '<TOKEN_PRIVATO>'; 'X-GF-Request-Id' = [guid]::NewGuid().ToString('N') }
$body = @{ dry_run = $true; items = @(@{ nome = 'Latte'; quantita = '1 litro' }) } | ConvertTo-Json -Depth 6
Invoke-RestMethod -Method Post -Uri 'https://www.gestionefamiglia.it/Gestionale25/api/v1/spesa.php' -Headers $headers -ContentType 'application/json' -Body $body
```

Esempio payload vacanza:

```json
{
  "dry_run": true,
  "titolo": "Creta",
  "data_inizio": "2027-05-08",
  "data_fine": "2027-05-11",
  "persone": 4,
  "scenari": [{
    "nome": "Bergamo",
    "trasporti": [{"tipo":"aereo","origine":"Bergamo","destinazione":"Creta","costo_volo_eur":367}],
    "alloggi": [{"nome":"Casa in centro","checkin":"2027-05-08","checkout":"2027-05-11","costo_notte_eur":100}],
    "altri_costi": [{"descrizione":"Museo","importo_eur":30}]
  }]
}
```

Gli importi sono esempi. L'API non inventa dati mancanti e crea il viaggio con stato iniziale `idea`. I costi per notte vengono calcolati dal gestionale secondo le regole disponibili nel database.

## Limiti e verifiche necessarie

- Non è ancora un plugin ChatGPT/Gemini e non abilita le chat a chiamare direttamente il sito.
- Si possono **creare** vacanze e leggere quelle importate dall'API, non ancora modificare viaggi già esistenti.
- Il token è unico per la famiglia configurata; per più famiglie o utenti indipendenti servirà una gestione di token e permessi separata.
- I due endpoint non hanno rate limiting per IP: usare un token lungo, HTTPS e ulteriori protezioni prima di una distribuzione ampia.
- Prima di abilitare la scrittura occorre collaudare SQL, totalizzazione alloggi, autorizzazioni, transazioni, errori e scenari su un DB di test.
- Eliminare `api/v1/diagnostica_aruba.php` dal branch **prima del merge** e non pubblicarlo nuovamente.
