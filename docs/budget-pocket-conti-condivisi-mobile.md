# Budget Pocket e conti condivisi, versione mobile

## Obiettivo
Separare l'accantonamento effettivo su Pocket Revolut dalla previsione dei budget e attribuire quote ai movimenti bancari già etichettati e registrare compensazioni tra amici senza creare movimenti bancari fittizi.

## Deploy (ordine necessario)
1. Effettuare il backup delle tabelle `salvadanai` e `bilancio_etichette`.
2. Se la migrazione non è mai stata eseguita, eseguire **una volta sola** `sql/migrations/20261118_mobile_pocket_conti_condivisi.sql` su MySQL 5.6. Se invece la prima versione della migrazione è stata già eseguita (prima dell'aggiunta del collegamento ai movimenti), eseguire soltanto `sql/migrations/20261118_shared_ledger_link_if_prior_migrated.sql` dopo aver verificato che la tabella esista e non contenga già `id_e2o`. Le istruzioni `ALTER TABLE` non sono idempotenti: NON ripetere la migrazione.
3. Pubblicare i file PHP e JS aggiornati dalla branch/commit del merge.
4. Verificare in `budget_dashboard.php` la presenza delle schede Pocket su schermo stretto e largo.
5. Aprire `salvadanaio_dettaglio.php?id=75` (Inverno), impostare manualmente la modalità **Fisso** e 100 €/mese, solo dopo aver confermato con l'utente che il Pocket corrisponda ancora a tale importo.
6. In `budget_importi_attuali.php`, cambiare un solo saldo lasciando gli altri campi vuoti: devono restare invariati.
7. In `etichetta.php?id_etichetta=164` (Disney+), aprire "Conto condiviso". Impostare la modalità "Conto tra persone" se desiderata. Provare quota, compensazione, saldo e cancellazione su dati di test. I movimenti esistenti sono proposti nell'elenco, ma per ciascuno occorre attribuire manualmente la quota della persona: non trasferire automaticamente l'intero importo. La tabella del conto salva il collegamento `id_e2o` e impedisce che la stessa operazione venga attribuita due volte alla medesima persona.

## Regole
- La quota da 13esima (`budget.da_13esima`) viene sottratta dalla rata mensile come in precedenza, ma resta presente nel fabbisogno totale e compare esplicitamente nella dashboard. La previsione considera l'accredito della 13esima **solo nel prossimo dicembre e solo se precedente alla scadenza**, mai come denaro già versato. La quota da 14esima viene mostrata ma esclusa dalla proiezione fino a quando non è noto il mese di erogazione.
- `salvadanai.accantonamento_modalita = automatico` di default: mantiene i vecchi budget. `fisso` usa `accantonamento_fisso` per il Pocket mensile senza modificare le righe `budget`.
- Saldo verificato manualmente e ultimo aggiornamento rimangono in `salvadanai`.
- Nella dashboard la proiezione è **indicativa** e basata sulle scadenze dei budget, non sottrae pagamenti intermedi. Non presentarla come saldo garantito.
- `etichette_conti_condivisi.importo > 0`: la controparte deve all'utente; `importo < 0`: riduzione del credito o debito dell'utente. Non includere queste registrazioni nei totali bancari o nei movimenti Revolut. Le quote possono essere collegate al movimento originale tramite `id_e2o` senza alterarne importo, descrizione o etichette.
- `bilancio_etichette.tipo_utilizzo` cambia la visualizzazione/classificazione, NON migra le vecchie quote e non altera le etichette dei movimenti.
- Conti condivisi limitati alla famiglia legacy 1, coerentemente con il perimetro dei movimenti Revolut; CSRF obbligatorio in scrittura.

## Controlli su smartphone
- A 360–390 px le schede devono occupare tutta la larghezza, senza scroll orizzontale.
- Pulsanti e campi devono essere tappabili; il salvataggio saldi è visibile a fondo schermata.
- La tabella annuale è resa a schede CSS per preservare le azioni esistenti.
- Rileggere dati dopo ogni modifica; verificare che il saldo Pocket non venga modificato dalla sola modifica dell'accantonamento.

## Verifica previsione Inverno e collegamento dei movimenti
- Regressione automatica `php tests/budget_pocket_previsione_test.php`: contributo 13ª di dicembre, esclusione quando già passato, scadenze intermedie e quota 14ª non datata.
- La dashboard continua a mostrare il piano mensile originario (netto delle quote 13ª/14ª), ma suggerisce quanto serve *da oggi* usando saldo verificato, scadenze e tredicesima futura di dicembre.
- La proiezione è un **saldo dopo le uscite programmate**, non la somma dei soli accantonamenti.
- Nel conto Disney+ si possono selezionare i movimenti già etichettati e assegnare manualmente la quota di Bocci: lo stesso movimento non può essere assegnato due volte alla stessa persona. Compensazioni senza transazione bancaria restano distinte.
- Non ripartire automaticamente le vecchie spese, neppure quelle chiamate cinema o Wiener: occorre verificare quali riguardano la quota dell'altra persona.
