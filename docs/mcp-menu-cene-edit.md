# Modifica agentica del menu cene (settimana e master)

## Endpoint e strumenti
- REST: POST /api/v1/menu_cene_modifica.php con dry_run=true per anteprima, false dopo conferma.
- REST lettura: GET /api/v1/menu_cene.php?week_start=YYYY-MM-DD, GET /api/v1/menu_cene_master.php.
- MCP: leggi_menu_settimanale (esistente), leggi_menu_master_cene, anteprima_modifica_menu_cene e modifica_menu_cene.
- L'API associa la famiglia esclusivamente al contesto autenticato del server: familyId, mai id_famiglia dal payload.

## Due ambiti obbligatori
- ambito=settimana e week_start=YYYY-MM-DD (lunedì): modifica unicamente menu_cene_settimanale per la famiglia e la settimana indicate. I cinque giorni sono salvati (inclusi quelli invariati e vuoti) così una visita successiva all'interfaccia non rigenera i giorni mancanti dal master.
- ambito=master, stagione=Estivo o Invernale e settimana_ciclo=1..4: modifica la configurazione JSON privata in config/menu_cene/{famiglia}/master.json tramite food_config_save, senza modificare le settimane già salvate. Resta il ciclo di quattro settimane. Il master richiede una cena non vuota in ciascun giorno.

Non occorrono nuove tabelle, colonne o migrazioni; si usa la chiave univoca già esistente su menu_cene_settimanale.

## Formato operazioni
L'elenco contiene 1-30 operazioni:
- sostituisci: giorno, parte=cena|contorno (default cena), piatto (testo)
- aggiungi: stessi campi, accoda il piatto alla parte scelta usando " + ", senza duplicare un valore già presente identico
- elimina: giorno, parte opzionale, piatto opzionale; con piatto rimuove solo quel componente separato da " + "; senza piatto svuota la parte scelta. Il master rifiuta cene vuote.
- scambia: giorno, altro_giorno; senza parte scambia cena E contorno fra i due giorni; con parte scambia solo cena oppure contorno.

Conservare sempre il formato dei piatti separati da newline tra cena e contorno, perché la UI e la verifica menu-spesa lo usano.

### Esempio: anteprima singola settimana
~~~json
{
  "ambito":"settimana",
  "week_start":"2026-11-16",
  "operazioni":[
    {"azione":"sostituisci","giorno":"Lunedì","parte":"cena","piatto":"Pasta al pesto"},
    {"azione":"scambia","giorno":"Martedì","altro_giorno":"Giovedì"}
  ]
}
~~~

Gli strumenti MCP impostano dry_run automaticamente in base a preview o scrittura. L'anteprima restituisce modifiche per giorno (prima/dopo), origine, riferimento del ciclo, expected_hash e avvertenze.

### Esempio: anteprima master
~~~json
{
  "ambito":"master",
  "stagione":"Invernale",
  "settimana_ciclo":2,
  "operazioni":[{"azione":"sostituisci","giorno":"Venerdì","parte":"contorno","piatto":"Insalata mista"}]
}
~~~

Il master si ripete ogni quattro settimane per la stagione richiesta; questa modifica è PERMANENTE per le previsioni future ma non cambia settimane già salvate.

## Conferma e sicurezza
Leggere, preparare anteprima, mostrare cambiamenti prima/dopo e distinguere chiaramente l'ambito; chiedere conferma esplicita. Per il salvataggio ritrasmettere le stesse operazioni con expected_hash, confirm=true e request_id nuovo 16-64 caratteri. Senza hash o con dati cambiati dal momento dell'anteprima, la scrittura è rifiutata. L'ID richiesta impedisce riapplicazioni. Le scritture della singola settimana sono transazionali; il master usa il salvataggio atomico e backup di food_config_save e un lock applicativo MySQL; file JSON e claim idempotente non costituiscono una transazione distribuita unica.

## Lista della spesa
Questa estensione NON elimina né riassegna automaticamente ingredienti già nella lista della spesa. Le associazioni storiche sono memorizzate nelle note nel formato "Piatto, Giorno", e potrebbero non corrispondere più al menu modificato. Dopo una modifica segnalare all'utente di verificare la lista, rieseguire la verifica menu e, se opportuno, usare gli strumenti di anteprima/aggiornamento delle associazioni già disponibili. Il master non aggiorna automaticamente né la spesa né le settimane materializzate.

## Deploy
Caricare su Aruba insieme api/mcp.php, api/v1/menu_cene_modifica.php, api/v1/menu_cene_master.php e api/v1/menu_cene_edit_helper.php, dopo backup. Riavviare/aggiornare discovery MCP.
Prima del merge/deploy verificare lint PHP 8.3, test unitari, famiglia errata, settimana non lunedì, stato hash obsoleto, request_id duplicato, master JSON non scrivibile, concorrenza e l'assenza di modifiche alla lista spesa. Non usare dati di produzione per test distruttivi.
