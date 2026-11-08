# Aggiornamento scenari esistenti: API e MCP, senza migrazioni

## Strumenti
- REST: POST /api/v1/vacanze_scenari.php
- MCP: anteprima_aggiornamento_scenario, aggiorna_scenario, anteprima_aggiornamento_scenari, aggiorna_scenari.
- Le anteprime inviano dry_run=true e non scrivono. Gli aggiornamenti richiedono confirm=true, request_id univoco e expected_hash della precedente anteprima.
- La famiglia viene verificata tramite viaggi_api_famiglie. Si modifica solo uno scenario appartenente alla vacanza autorizzata.
- Operazioni multiple atomiche: o tutte le modifiche vengono salvate oppure nessuna.

## Esempio payload per anteprima
I prezzi seguenti sono SOLO ESEMPI, non offerte reali.

~~~json
{
  "dry_run": true,
  "id_viaggio": 16,
  "scenari": [
    {
      "id_scenario": 30,
      "voci": [
        {"categoria":"trasporti","campi":{"tipo_tratta":"aereo","descrizione":"Volo A/R (esempio)","costo_volo_eur":300,"note":"Non verificato, 4 persone"}},
        {"categoria":"alloggi","campi":{"nome_alloggio":"Appartamento (esempio)","data_checkin":"2026-11-27","data_checkout":"2026-11-30","costo_totale_eur":240,"note":"Non verificato"}},
        {"categoria":"altri_costi","campi":{"descrizione":"Pasti (esempio)","importo_eur":100,"note":"Stima non verificata"}}
      ]
    }
  ]
}
~~~

La risposta include prima, dopo, differenze_numeriche, expected_hash per ciascuno scenario. Il client deve mostrare l'anteprima e ottenere il consenso, poi ritrasmettere lo stesso payload con dry_run=false e expected_hash all'interno dello scenario; aggiungere X-GF-Request-Id di 16-64 caratteri.

## Categorie
- trasporti: tipo_tratta, descrizione, origine_testo, destinazione_testo, costo_volo_eur, costo_traghetto_eur, costo_noleggio_eur, pedaggi_eur, altri_costi_eur, note.
- alloggi: nome_alloggio, indirizzo, data_checkin, data_checkout, costo_notte_eur, costo_totale_eur, note.
- pasti: nome_locale, tipo_pasto, tipologia, giorno_indice, costo_medio_eur, note.
- altri_costi: descrizione, importo_eur, data, note.

Per aggiornare una voce esistente usare il suo ID. Se l'ID manca, l'API usa una descrizione esatta e unica per identificare un eventuale record omonimo nello stesso scenario. Non sono consentite cancellazioni, cambi di pagato o aggiornamenti di spese già pagate o condivise tra più scenari.

## Limiti e calcolo
- I costi totali sono la somma delle singole voci. Non registrare un preventivo globale nelle altre spese se sono poi inseriti voli, alloggi, pasti e trasferimenti: provocherebbe doppio conteggio.
- Per un alloggio, costo_totale_eur prevale sul costo per notte; la colonna è già presente nel gestionale e non richiede una nuova migrazione.
- Qualità del prezzo (stimato, verificato), fonte e data possono essere annotate nel campo note, ma non sono strutturate né filtrabili senza una futura modifica di modello. Il flag pagato resta autonomo.
- La scheda Confronta scenari ordina i costi censiti dal più basso al più alto; 0 euro significa nessun costo censito, non vacanza gratuita.

## Deploy e test
1. Nessuna migrazione SQL; verificare comunque che il server disponga delle tabelle e delle viste delle Vacanze V2.
2. Pubblicare assieme api/mcp.php, api/v1/vacanze_scenari.php e vacanze_workspace.php dopo backup.
3. Aggiornare la discovery degli strumenti MCP.
4. Eseguire anteprime sulla vacanza 16, NON scritture reali durante sviluppo.
5. Testare le scritture solo su un database di test / vacanza appositamente autorizzata.
6. Verificare accesso cross-family, scenario estraneo, aggiornamento voce condivisa o pagata, hash obsoleto, request_id duplicato e salvataggio multiplo transazionale.
