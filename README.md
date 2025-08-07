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

