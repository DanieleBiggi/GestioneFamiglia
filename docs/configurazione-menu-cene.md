# Configurazione Menù cene e ricette

Dopo l’attivazione per la famiglia selezionata, **Configurazione > Menù cene > Master** permette di modificare il ciclo di quattro settimane per ciascuna stagione, da lunedì a venerdì. Cena e contorno sono campi di testo libero. La data di riferimento deve essere un lunedì. Il periodo invernale inizia il primo lunedì di novembre. Il ciclo estivo riprende la settimana successiva al secondo venerdì di aprile.

Una modifica al master si applica alle settimane future ancora da generare e ai giorni mancanti. I piatti già salvati per una settimana conservano il valore esistente. Se un altro utente ha cambiato la configurazione nel frattempo, ricarica l’editor prima di salvare.

**Configurazione > Menù cene > Ricette** permette di associare istruzioni di preparazione ai piatti del master. L’abbinamento usa il nome ignorando maiuscole e spazi multipli. Piatti con nomi diversi, come «Polpette di carne» e «Polpette al sugo», richiedono ricette distinte. L’export del menù indica i piatti privi di ricetta e include le istruzioni delle ricette abbinate.

Il pulsante **Scarica JSON** esporta una copia. I file storici in `config/menu_cene/<id_famiglia>/` sono usati per la migrazione iniziale e il ritorno alla sorgente precedente. Per la procedura di installazione, migrazione e rollback consulta [Menù relazionale su Aruba](menu-relazionale-aruba.md).

La pagina richiede i permessi `view` e `update` della risorsa `page:menu_cene_config.php` per leggere e modificare i dati. La migrazione iniziale e la sua attivazione richiedono un amministratore.
