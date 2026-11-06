# Collegamento diretto Gestione Famiglia - ChatGPT

La cartella include il manifest di un plugin privato con server **MCP Streamable HTTP**, ospitato su Aruba, senza servizi aggiuntivi.

## Fasi di attivazione

1. Pubblicare prima `api/mcp.php` sul sito e verificare che il sito usi PHP 8.3 e che l'estensione cURL sia disponibile.
2. **Non inserire il token in alcun file su GitHub o nello schema MCP**. Il token va fornito solo nell'interfaccia sicura di connessione dell'app/plugin.
3. Prima di collegare ChatGPT, effettuare i test MCP (initialize, tools/list, tool preview) da PowerShell con l'header `X-GF-Api-Key`; non testare strumenti di scrittura prima che il sistema sia verificato.
4. Se l'account offre **Plugin > Aggiungi server MCP personalizzato**, configurare URL:
   `https://www.gestionefamiglia.it/Gestionale25/api/mcp.php`
   e autenticazione API-key su `X-GF-Api-Key`. In alternativa usare la procedura di installazione del plugin privato con i file `plugin.json` e `mcp.json`, **se supportata dall'account**.
5. Impostare i permessi dell'app per richiedere sempre conferma prima di scrivere. Gli strumenti `crea_spesa` e `crea_vacanza` richiedono inoltre `confirm:true`, da impostare solo dopo la conferma esplicita dell'utente.

### Test di connettività con PowerShell

```powershell
$token = Read-Host "Token API privato"
$headers = @{ "X-GF-Api-Key"=$token; "Accept"="application/json, text/event-stream"; "Content-Type"="application/json" }
$body = '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"PowerShell","version":"1.0"}}}'
Invoke-RestMethod -Uri "https://www.gestionefamiglia.it/Gestionale25/api/mcp.php" -Method Post -Headers $headers -Body $body
$body = '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'
Invoke-RestMethod -Uri "https://www.gestionefamiglia.it/Gestionale25/api/mcp.php" -Method Post -Headers $headers -Body $body
```

**Limiti iniziali:** il protocollo è stateless e restituisce JSON, non è stato verificato su Aruba. L'adattatore effettua richieste HTTPS verso gli endpoint REST dello stesso sito usando l'estensione PHP cURL: verificare che il provider consenta richieste in uscita e loopback. Non eseguire il merge prima dei test.

Le API esistenti rimangono disponibili; nessun dato viene scritto durante initialize, tools/list e anteprima.
