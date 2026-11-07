# Collegamento OAuth ChatGPT ↔ Gestione Famiglia (PR #444)

Questa PR aggiunge OAuth al server MCP già funzionante. OAuth è disabilitato di default; le REST API con `X-GF-Api-Key` continuano a funzionare. L'autenticazione e la lettura della spesa via ChatGPT sono state collaudate su Aruba.

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

## Header Authorization su Aruba

Il passaggio dell'header Bearer da Apache a PHP e il flusso OAuth sono stati verificati su Aruba con ChatGPT. Le direttive necessarie sono presenti nel file `.htaccess` di `/Gestionale25/`.

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

## Verifiche dopo il deploy

1. Login normale di Gestione Famiglia e API esistenti devono continuare a funzionare.
2. Discovery OAuth e 401 con `WWW-Authenticate: Bearer resource_metadata=...`.
3. Autorizzazione interattiva ChatGPT → pagina consenso → code → token PKCE.
4. Chiamata `leggi_lista_spesa` tramite ChatGPT OAuth.
5. Refresh token e secondo accesso dopo scadenza.
6. Tentativo di scrittura solo **dopo una conferma esplicita** nell'interfaccia, con permessi plugin impostati su `ask before writes`.

**Attenzione:** `confirm:true` nei parametri MCP è solo una protezione applicativa, non prova crittografica della conferma umana; il prompt di autorizzazione ChatGPT rimane necessario.

In caso di 500, 401 continui o problemi di login, ripristinare il backup dei file modificati e mantenere `mcp_oauth_enabled=false`. Non esporre password, token, hash o sessioni nei log condivisi.


**Pulizia dopo il collaudo:** eliminare manualmente su Aruba `api/oauth/check_aruba.php` e `api/oauth/check_opcache.php`, se ancora presenti. Non sono più nel repository. La tabella `gf_oauth_diagnostics` già creata su Aruba non è usata dal codice finale: può essere rimossa separatamente dopo averne valutato la conservazione, senza farne dipendere il deploy.
