# Budget Pocket e conti condivisi, versione mobile

## Obiettivo
Separare l'accantonamento effettivo su Pocket Revolut dalla previsione dei budget e registrare quote e compensazioni tra amici senza creare movimenti bancari fittizi.

## Deploy (ordine necessario)
1. Effettuare il backup delle tabelle `salvadanai` e `bilancio_etichette`.
2. Eseguire **una volta sola** la migrazione `sql/migrations/20261118_mobile_pocket_conti_condivisi.sql` sul database MySQL 5.6. Le istruzioni `ALTER TABLE` non sono idempotenti: NON ripetere la migrazione.
3. Pubblicare i file PHP e JS aggiornati dalla branch/commit del merge.
4. Verificare in `budget_dashboard.php` la presenza delle schede Pocket su schermo stretto e largo.
5. Aprire `salvadanaio_dettaglio.php?id=75` (Inverno), impostare manualmente la modalità **Fisso** e 100 €/mese, solo dopo aver confermato con l'utente che il Pocket corrisponda ancora a tale importo.
6. In `budget_importi_attuali.php`, cambiare un solo saldo lasciando gli altri campi vuoti: devono restare invariati.
7. In `etichetta.php?id_etichetta=164` (Disney+), aprire "Conto condiviso". Impostare la modalità "Conto tra persone" se desiderata. Provare quota, compensazione, saldo e cancellazione su dati di test. Non importare automaticamente movimenti esistenti: va chiarito prima come ripartire le quote.

## Regole
- `salvadanai.accantonamento_modalita = automatico` di default: mantiene i vecchi budget. `fisso` usa `accantonamento_fisso` per il Pocket mensile senza modificare le righe `budget`.
- Saldo verificato manualmente e ultimo aggiornamento rimangono in `salvadanai`.
- Nella dashboard la proiezione è **indicativa** e basata sulle scadenze dei budget, non sottrae pagamenti intermedi. Non presentarla come saldo garantito.
- `etichette_conti_condivisi.importo > 0`: la controparte deve all'utente; `importo < 0`: riduzione del credito o debito dell'utente. Non includere queste registrazioni nei totali bancari o nei movimenti Revolut.
- `bilancio_etichette.tipo_utilizzo` cambia la visualizzazione/classificazione, NON migra le vecchie quote e non altera le etichette dei movimenti.
- Conti condivisi limitati alla famiglia legacy 1, coerentemente con il perimetro dei movimenti Revolut; CSRF obbligatorio in scrittura.

## Controlli su smartphone
- A 360–390 px le schede devono occupare tutta la larghezza, senza scroll orizzontale.
- Pulsanti e campi devono essere tappabili; il salvataggio saldi è visibile a fondo schermata.
- La tabella annuale è resa a schede CSS per preservare le azioni esistenti.
- Rileggere dati dopo ogni modifica; verificare che il saldo Pocket non venga modificato dalla sola modifica dell'accantonamento.
