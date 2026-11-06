# Verifica Aruba, senza database

File temporaneo: `api/v1/diagnostica_aruba.php`.

1. Copiare **solo questo file** sul server Aruba in `/Gestionale25/api/v1/diagnostica_aruba.php`. Non serve fare il merge della PR e **non caricare le altre API**.
2. Aprire `https://www.gestionefamiglia.it/Gestionale25/api/v1/diagnostica_aruba.php`: la risposta JSON deve indicare `ok: true`, `mysqli: true`, `json: true`, `hash_equals: true`.
3. Per testare `Authorization` usare PowerShell sul proprio PC (il valore è intenzionalmente fittizio e non è un token):
   
   ```powershell
   Invoke-RestMethod -Uri 'https://www.gestionefamiglia.it/Gestionale25/api/v1/diagnostica_aruba.php' -Headers @{ Authorization = 'Bearer test-header-only' }
   ```
   
   Il JSON deve riportare `authorization_header_received: true`. La diagnostica **non** verifica né mostra il token.
4. Rimuovere **il file dal server Aruba** al termine del test. Rimuoverlo dal branch prima del merge. Non pubblicare credenziali o dettagli privati nei test.

La risposta PHP non prova il supporto delle variabili d'ambiente personalizzate: per l'API di produzione preferire una configurazione privata non accessibile via HTTP, verificando prima il relativo blocco di accesso.

Se PHP restituisce un errore 500 o una pagina anziché JSON, non procedere al deploy delle API prima di chiarire il problema.
