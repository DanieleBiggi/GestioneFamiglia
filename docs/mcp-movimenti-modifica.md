# Modifica movimenti tramite API / MCP

Endpoint `POST /api/v1/movimenti_modifica.php`: stessa autorizzazione personale di `movimenti.php`.

Strumenti:
- `elenca_gruppi_movimenti` e `elenca_etichette_movimenti`: sola lettura degli ID disponibili.
- `anteprima_modifica_movimento`: richiede `payload` con `source` (bilancio_uscite, bilancio_entrate o movimenti_revolut), `id` e almeno uno fra `descrizione_extra`, `note`, `id_gruppo_transazione`, `etichette`. Non salva, restituisce prima/dopo ed `expected_hash`.
- `modifica_movimento`: gli stessi dati, `expected_hash`, `confirm: true` dopo consenso esplicito e `request_id` univoco di 16-64 caratteri. Salva in transazione e rifiuta dati cambiati dopo l'anteprima.

Le proprietà omesse restano invariate. Il campo `note` (massimo 5000 caratteri) è distinto da `descrizione_extra` (massimo 250 caratteri), può essere letto nel dettaglio e cercato nell'elenco movimenti. Entrambi entrano nell'hash dell'anteprima per impedire sovrascritture concorrenti. `descrizione_extra: null` cancella la descrizione extra; `note: null` cancella le note; `id_gruppo_transazione: null` cancella il gruppo; `etichette: []` scollega tutte le etichette. **L'array etichette è la lista completa desiderata**, non un elenco di aggiunte. I collegamenti che rimangono non sono cancellati o ricreati: mantengono importi parziali, quote, allegati ed esclusioni. I collegamenti eliminati possono perdere tali dati: verificare attentamente l'anteprima.

Esempio di anteprima:
```json
{"payload":{"source":"movimenti_revolut","id":6236,"descrizione_extra":"Corsi sci ESF Sofia e Francesco","note":"Monginevro 2027, Super 6, ordine EP-6107661, pagato il 04/11/2026."}}
```
L'esempio è illustrativo: non utilizzare ID gruppo/etichetta senza leggerli da catalogo e non eseguire modifiche senza autorizzazione.

**Verifiche prima del deploy:** audit PHP 8.3, scope OAuth family:read e family:write, proprietario movimenti, ID di etichette e gruppi esistenti, anteprima non scrivente, idempotenza request_id, conflitto expected_hash, mantenimento dei metadati sui collegamenti non modificati, e transazioni rollback.

**Limitazioni:** i gruppi sono limitati a `id_utente` configurato. Etichette e vista Revolut usano il vecchio perimetro familiare 1: per altre famiglie va realizzato uno scope dati esplicito prima di abilitarne la modifica. Nessuna migrazione DB inclusa: `note` esiste gia' su tutte e tre le tabelle di origine. Dopo il deploy aggiornare gli strumenti MCP, poi creare anteprima ed eseguire modifica solo dopo approvazione esplicita.
