# Gestione Famiglia

## Configurazione del database

L'applicazione non utilizza più credenziali hardcoded per la connessione MySQL. I parametri vengono letti dalle variabili d'ambiente:

- `DB_HOST`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`
- `DB_PORT` (opzionale, predefinito `3306`)

Imposta queste variabili nell'ambiente in cui gira l'applicazione oppure crea un file `includes/db_config.php` **non versionato** che ritorni un array con gli stessi parametri:

```php
<?php
return [
    'host' => 'localhost',
    'port' => '3306',
    'user' => 'utente',
    'pass' => 'password',
    'name' => 'database',
];
?>
```

Il file `includes/db_config.php` è già incluso in `.gitignore` per evitare che le credenziali finiscano nel repository.

## Dipendenze

L'applicazione include una copia leggera di [PHPMailer](https://github.com/PHPMailer/PHPMailer) per l'invio di email. Le componenti relative a DSN, OAuth e POP3 sono state rimosse perché non utilizzate.

## Sincronizzazione Google Calendar

Per abilitare il pulsante **GOOGLE** nella pagina dei turni è necessario configurare le credenziali per le API di Google Calendar.

1. Installare la libreria client:
   ```bash
   composer require google/apiclient:^2.0
   ```
2. Creare su [Google Cloud Console](https://console.cloud.google.com/) delle credenziali OAuth 2.0 o un account di servizio e scaricare il file JSON.
3. Salvare il file delle credenziali in `config/google_credentials.json` e il token di accesso in `config/google_token.json` (entrambi **non versionati**).
4. Il token deve includere l'ambito `https://www.googleapis.com/auth/calendar`. Se il token scade verrà aggiornato automaticamente.
5. (Opzionale) impostare la variabile d'ambiente `GOOGLE_CALENDAR_ID` con l'ID del calendario da utilizzare; in assenza viene usato il calendario `primary`.

Una volta completata la configurazione, il pulsante **GOOGLE** su `turni.php` sincronizzerà sul calendario gli eventi e i turni del mese visualizzato.


## FAQ

### Devo reinstallare l'app per usare l'accesso con impronta digitale?
No: l'impronta digitale continua a funzionare dopo aver reinstallato o aggiornato l'app. Se viene richiesto utente e password, basta effettuare un accesso una sola volta per rigenerare il dispositivo riconosciuto e tornare a usare il fingerprint senza inserire le credenziali.
