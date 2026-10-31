# Configurazione Menù cene e ricette

La pagina **Configurazione > Menù cene** permette di gestire, per ciascuna famiglia, due JSON separati.

- `master.json`: ciclo di 4 settimane per le stagioni `Estivo` e `Invernale`, da lunedì a venerdì.
- `ricette.json`: preferenze di preparazione associate ai nomi dei piatti e agli alias.

I file vengono salvati sul server in `config/menu_cene/<id_famiglia>/`, esclusi da Git. La regola `config/.htaccess` ne impedisce l'accesso diretto su Apache, **da verificare su Aruba**. Il server deve poter scrivere nella directory. Il backup immediatamente precedente viene salvato come `.json.bak`.

## Master: formato JSON

```json
{
  "version": 1,
  "anno_scolastico": "2026-2027",
  "ciclo": {
    "settimane": 4,
    "data_riferimento": "2026-09-14",
    "settimana_riferimento": 2
  },
  "stagioni": {
    "Estivo": {
      "1": {
        "Lunedì": { "cena": "Pizza", "contorno": "Insalata" }
      }
    },
    "Invernale": {}
  }
}
```

Questo esempio è **incompleto**: per importarlo occorrono tutti i 5 giorni di tutte le 4 settimane per entrambe le stagioni. Si consiglia di scaricare dall'app il JSON attuale come modello e chiedere a ChatGPT di aggiornare i dati dal PDF della mensa, **senza cambiare i nomi delle chiavi**, restituendo soltanto JSON valido. Verificare manualmente il risultato prima di importarlo.

La data di riferimento deve essere un lunedì. La stagione segue ancora le regole PHP esistenti: primo lunedì di novembre per l'inizio invernale, settimana successiva al secondo venerdì di aprile per il ritorno estivo. Queste regole **non sono ancora modificabili dal JSON**.

I menù settimanali già presenti in `menu_cene_settimanale` non vengono sovrascritti dal cambio del master. Solo settimane mai generate o giorni mancanti usano il nuovo master.

## Ricette: formato JSON

```json
{
  "version": 1,
  "ricette": [
    {
      "id": "pizza",
      "nome": "Pizza",
      "alias": [],
      "istruzioni": "Prepariamo la pizza in casa: non acquistare impasto pronto. Prevedi farina e lievito."
    },
    {
      "id": "polpette",
      "nome": "Polpette di carne",
      "alias": ["Polpette al sugo"],
      "istruzioni": "Usiamo macinato e salsiccia. Non prevedere mortadella."
    }
  ]
}
```

Questi esempi **non sono inseriti automaticamente** nel ricettario. Possono essere aggiunti dall'interfaccia. Le corrispondenze avvengono per nome esatto (ignorando maiuscole e spazi multipli), non per somiglianza: un piatto non riconosciuto viene segnalato nell'export. Le istruzioni vengono incluse nel testo copiato da **Esporta menù** per il plugin Lista della spesa; il plugin resta responsabile di generare le quantità e gli ingredienti.

## Permessi e verifica

La pagina usa le risorse `page:menu_cene_config.php` con azioni `view` e `update`. Il sistema di permessi crea le risorse mancanti senza concedere automaticamente accesso. L'amministratore deve configurare i permessi per gli utenti desiderati.

Verificare in staging: lettura fallback master; generazione nuova settimana; nessuna modifica a settimane esistenti; import JSON incompleto respinto; backup; cambio famiglia; esportazione con e senza ricette; protezione HTTP di `config/`; scrittura su Aruba.
