# Collegamento OAuth ChatGPT ↔ Gestione Famiglia (PR #444)

Questa PR aggiunge OAuth al server MCP già funzionante. **Non eseguire il merge prima del test end-to-end su Aruba.** OAuth è disabilitato di default; le REST API con `X-GF-Api-Key` continuano a funzionare.

## Percorso dei file

Il progetto risiede nel percorso pubblico `/Gestionale25/`, ma il dominio è `https://www.gestionefamiglia.it`.

- Caricare `api/oauth/*`, `api/mcp.php` e `api/v1/bootstrap.php` nella relativa cartella **`/Gestionale25/`**.
- Caricare il nuovo `.htaccess` di `/Gestionale25/` **con backup preliminare**, senza perdere le altre regole presenti su Aruba.
- Il file `deploy/domain-root/.well-known/.htaccess` **non** va sotto `/Gestionale25/`: copiare/integrare le due RewriteRule nella cartella `/.well-known/` della **radice del dominio**, creando la cartella se necessario. Non sovrascrivere le eventuali altre regole presenti lì.
- Il file privato `/Gestionale25/includes/api_v1_config.php` esiste solo sul server e non va mai su GitHub.
- `sql/mcp_oauth.sql` crea tre tabelle per codici OAuth, access token e refresh token. Eseguirlo tramite phpMyAdmin **dopo il backup**.

## Discovery: controlli senza credenziali

Le due URL pubbliche devono rispondere con JSON:

- `https://www.gestionefamiglia.it/.well-known/oauth-authorization-server`
- `https://www.gestionefamiglia.it/.well-known/oauth-protected-resource`

La prima deve indicare `issuer: https://www.gestionefamiglia.it`, `code_challenge_methods_supported: ["S256"]`, `client_id_metadata_document_supported: true`, e i corretti endpoint authorize/token.

La seconda deve indicare `resource: https://www.gestionefamiglia.it/Gestionale25/api/mcp.php` e `authorization_servers` con l'issuer di cui sopra.

## Test del passaggio dell'header Authorization

**Il precedente test Aruba aveva rilevato che l'header Authorization NON veniva reso disponibile a PHP.** La nuova versione del file `.htaccess` include direttive di inoltro, ma **va verificato che funzionino sull'hosting reale**. In caso contrario ChatGPT non riuscirà a usare OAuth, anche se le API con `X-GF-Api-Key` funzionano.

Non attivare OAuth prima di aver verificato questo punto e la stabilità dell'app dopo la pubblicazione del nuovo `.htaccess`.

Il branch include un file di diagnostica **temporaneo**, `api/oauth/check_aruba.php`. Finché non viene rimosso, per testare l'header senza mostrarne il valore puoi eseguire in PowerShell (nella finestra con il tuo token API in `$token`):

```powershell
Invoke-RestMethod -Uri "https://www.gestionefamiglia.it/Gestionale25/api/oauth/check_aruba.php" -Method Get -Headers @{"X-GF-Api-Key"=$token; "Authorization"="Bearer test-header-only"}
```

Il risultato atteso è `success=True`, `authorization_header_received=True`. Se è `False`, il passaggio del bearer da Apache a PHP è ancora bloccato e **non bisogna attivare OAuth**. Il test non crea né modifica dati e non restituisce alcun valore di header. **Eliminare `check_aruba.php` dal branch e dal server una volta verificato**, prima di fare il merge.


## Configurazione privata: solo dopo i test di discovery e database

Nel file server `includes/api_v1_config.php` aggiungere:

```php
'mcp_oauth_enabled' => false,
'mcp_internal_key' => 'CHIAVE_CASUALE_DI_64_CARATTERI_ESADECIMALI',
'oauth_allowed_user_id' => 0, // consigliato l'ID utente preciso autorizzato al collegamento
```

Generare la chiave interna **diversa dal token API**, 32 byte crittografici, in PowerShell:

```powershell
$bytes = New-Object byte[] 32
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$rng.GetBytes($bytes)
$rng.Dispose()
[BitConverter]::ToString($bytes).Replace('-', '').ToLowerInvariant()
```

Conservare la chiave interna esclusivamente nel file PHP protetto su Aruba; non inviarla via chat o commit. **Non sostituisce l'hash SHA-256 del token API**, che deve rimanere invariato.

Il campo `oauth_allowed_user_id` permette di limitare l'autorizzazione a un solo utente. Non inventare l'ID: verificarlo nella tabella `utenti` o dalla sessione dell'utente. Il valore 0 non pone questa restrizione aggiuntiva, ma rimane comunque necessario il login nella famiglia configurata.

Quando sono pronti i test, cambiare `mcp_oauth_enabled` a `true`. Il server mantiene le REST con `X-GF-Api-Key`. Il MCP accetta bearer OAuth per le chiamate ChatGPT e usa una firma HMAC separata per le chiamate server-to-server, senza inoltrare il bearer OAuth al backend.

## Connessione in ChatGPT

In ChatGPT aprire **Plugins → + → Aggiungi server MCP personalizzato**:

- Nome: `Gestione Famiglia`
- URL: `https://www.gestionefamiglia.it/Gestionale25/api/mcp.php`
- Autenticazione: **OAuth**
- Impostare le autorizzazioni del plugin in modo da **chiedere sempre conferma prima delle scritture**.

ChatGPT dovrebbe scoprire i documenti OAuth e usare il Client ID Metadata Document (CIMD). Il server accetta soltanto client metadata HTTPS sul dominio `chatgpt.com` con redirect URI validi pubblicati in tale documento.

Al primo collegamento, la pagina authorize deve riconoscere una sessione già autenticata in Gestione Famiglia. Se sei disconnesso, apri `login.php` in una nuova scheda, completa password/2FA/passcode, torna alla pagina di autorizzazione e aggiornala. **Solo dopo aver letto la schermata di consenso**, premi `Autorizza ChatGPT`.

Il token OAuth restituito a ChatGPT ha scadenza 1 ora; il refresh token (ruotato a ogni utilizzo) ha scadenza 30 giorni. Soltanto gli hash dei token sono conservati nel database. Per revocare subito i collegamenti è possibile impostare `mcp_oauth_enabled=false`; per annullare i token già emessi aggiornare `revoked_at` nelle tabelle `gf_oauth_access_tokens` e `gf_oauth_refresh_tokens` tramite operazione amministrativa autorizzata.

## Controlli funzionali obbligatori

1. Login normale di Gestione Famiglia e API esistenti devono continuare a funzionare.
2. Discovery OAuth e 401 con `WWW-Authenticate: Bearer resource_metadata=...`.
3. Autorizzazione interattiva ChatGPT → pagina consenso → code → token PKCE.
4. Chiamata `leggi_lista_spesa` tramite ChatGPT OAuth.
5. Refresh token e secondo accesso dopo scadenza.
6. Tentativo di scrittura solo **dopo una conferma esplicita** nell'interfaccia, con permessi plugin impostati su `ask before writes`.

**Attenzione:** `confirm:true` nei parametri MCP è solo una protezione applicativa, non prova crittografica della conferma umana; il prompt di autorizzazione ChatGPT rimane necessario.

In caso di 500, 401 continui o problemi di login, ripristinare il backup dei file modificati e mantenere `mcp_oauth_enabled=false`. Non esporre password, token, hash o sessioni nei log condivisi.

## Diagnostica flusso di collegamento (solo durante il collaudo)

Se il consenso genera il codice ma ChatGPT rimane con "Connetti" in caricamento e `gf_oauth_codes.used_at` rimane NULL, **non ripetere il clic sullo stesso consenso**.

Questa PR include la tabella `gf_oauth_diagnostics`, con sole fasi e orari, senza dati privati. Dopo averla creata (script SQL aggiornato) e aver pubblicato `api/oauth/authorize.php` e `api/oauth/token.php`, avvia **una sola nuova connessione** da ChatGPT.

In phpMyAdmin:

```sql
SELECT recorded_at, endpoint, stage
FROM gf_oauth_diagnostics
ORDER BY id DESC LIMIT 15;
```

Interpretazione:
- `authorize / redirect_issued`, senza `token / request_received`: consenso e redirect dal gestionale avvenuti, ma richiesta al token endpoint non osservata. Controllare in ChatGPT il callback o la connessione, senza ripetere il consenso.
- `token / request_received`, senza `token / token_issued`: ChatGPT ha raggiunto il token endpoint, ma è stata rifiutata o è fallita la richiesta. Esaminare il solo status HTTP e il tipo di errore (non parametri, codici o token).
- `token / token_issued`: token rilasciato, il problema è nell'uso successivo da ChatGPT.

Non eliminare prematuramente la diagnostica prima di aver concluso i test; successivamente si può rimuovere il codice di tracing e la tabella in una PR di pulizia.
