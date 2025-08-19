<?php
include 'includes/session_check.php';
include 'includes/db.php';
include 'includes/header.php';

if ($_FILES && is_uploaded_file($_FILES['fileToUpload']['tmp_name'])) {
    $file = $_FILES['fileToUpload']['tmp_name'];
    $handle = fopen($file, "r");

    $firstLine = fgets($handle);
    rewind($handle);

    if (stripos($firstLine, 'Type') !== false || stripos($firstLine, 'Started Date') !== false) {
        // Importazione movimenti Revolut
        // Leggere e ignorare l'intestazione
        fgetcsv($handle, 1000, ",");

        $tabella = 'movimenti_revolut';
        $nuove_descrizioni_inserite = 0;
        $inserita = 0;

        $stmtInsert = $conn->prepare(
            "INSERT INTO movimenti_revolut (
                id_gruppo_transazione,
                id_salvadanaio,
                type,
                product,
                started_date,
                completed_date,
                description,
                amount,
                note
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $id_etichetta   = 0;
            $id_gruppo      = null;
            $id_salvadanaio = null;
            $type           = $data[0];
            $product        = $data[1];
            $started_date   = $data[2];
            $descrizione    = $data[4];
            $amount         = (float)$data[5];
            $note           = null;
            $nome           = null;

            if ($type == 'TRANSFER') {
                $descrizione_importazione = str_replace('To EUR', '', $descrizione);
                $descrizione_importazione = trim($descrizione_importazione);

                $stmt = $conn->prepare(
                    'SELECT id_mov2salv, id_salvadanaio, descrizione_importazione, inserita_il, aggiornata_il
                     FROM movimenti_revolut2salvadanaio_importazione
                     WHERE descrizione_importazione = ?'
                );
                $stmt->bind_param('s', $descrizione_importazione);
                $stmt->execute();
                $ar_salvadanai = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($ar_salvadanai) {
                    $id_salvadanaio = $ar_salvadanai['id_salvadanaio'];
                } else {
                    $stmtIns = $conn->prepare(
                        'INSERT INTO salvadanai (nome_salvadanaio) VALUES (?)'
                    );
                    $stmtIns->bind_param('s', $descrizione_importazione);
                    $stmtIns->execute();
                    $id_salvadanaio = $conn->insert_id;
                    $stmtIns->close();

                    $stmtIns = $conn->prepare(
                        'INSERT INTO movimenti_revolut2salvadanaio_importazione (id_salvadanaio, descrizione_importazione) VALUES (?, ?)'
                    );
                    $stmtIns->bind_param('is', $id_salvadanaio, $descrizione_importazione);
                    $stmtIns->execute();
                    $stmtIns->close();
                    $nuove_descrizioni_inserite++;
                }
            }

            $data_completed = $data[3] !== '' ? $data[3] : null;

            $stmt = $conn->prepare(
                "SELECT id_d2id, id_utente, id_gruppo_transazione, id_metodo_pagamento, id_etichetta
                 FROM bilancio_descrizione2id
                 WHERE conto = 'revolut' AND (? LIKE CONCAT('%', descrizione, '%') OR ? LIKE CONCAT('%', descrizione, '%'))"
            );
            $stmt->bind_param('ss', $descrizione, $nome);
            $stmt->execute();
            $dati_gruppo = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($dati_gruppo) {
                $id_gruppo    = $dati_gruppo['id_gruppo_transazione'];
                $id_etichetta = $dati_gruppo['id_etichetta'];
            }

            $stmtInsert->bind_param(
                'iisssssds',
                $id_gruppo,
                $id_salvadanaio,
                $type,
                $product,
                $started_date,
                $data_completed,
                $descrizione,
                $amount,
                $note
            );
            $stmtInsert->execute();
            $id_tabella = $conn->insert_id;

            if ($id_salvadanaio) {
                $data_operazione = $data_completed ?? $started_date;
                $stmtCheck = $conn->prepare('SELECT data_aggiornamento_manuale FROM salvadanai WHERE id_salvadanaio = ?');
                $stmtCheck->bind_param('i', $id_salvadanaio);
                $stmtCheck->execute();
                $salv = $stmtCheck->get_result()->fetch_assoc();
                $stmtCheck->close();

                if (!$salv || !$salv['data_aggiornamento_manuale'] || $salv['data_aggiornamento_manuale'] <= $data_operazione) {
                    $importo_da_aggiungere = -1 * $amount;
                    $stmtUpd = $conn->prepare('UPDATE salvadanai SET importo_attuale = importo_attuale + ? WHERE id_salvadanaio = ?');
                    $stmtUpd->bind_param('di', $importo_da_aggiungere, $id_salvadanaio);
                    $stmtUpd->execute();
                    $stmtUpd->close();
                }
            }

            if ($id_etichetta > 0) {
                dividi_operazione_per_etichetta($id_etichetta, $tabella, $id_tabella);
            }

            $inserita++;
        }

        $stmtInsert->close();
        fclose($handle);

        echo "<div class='alert alert-success'>Inserite " . $inserita . " righe</div><br>" .
            "<a class='btn btn-primary btn-sm ml-2' href='index.php'>Torna alla lista</a>";

        if ($nuove_descrizioni_inserite > 0) {
            echo "<div class='alert alert-warning mt-4'>Sono state inserite " . $nuove_descrizioni_inserite . " Nuove descrizioni.</div>";
        }
    } else {
        // Importazione bilancio entrate/uscite
        // Leggere e ignorare l'intestazione
        fgetcsv($handle, 1000, ",");

        $tot_righe  = 0;
        $inserita   = 0;

        $stmtInsertUscite = $conn->prepare(
            'INSERT INTO bilancio_uscite (id_utente, id_tipologia, id_gruppo_transazione, id_metodo_pagamento, descrizione_operazione, importo, data_operazione) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmtInsertEntrate = $conn->prepare(
            'INSERT INTO bilancio_entrate (id_utente, id_tipologia, id_gruppo_transazione, id_metodo_pagamento, descrizione_operazione, importo, data_operazione) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        while (($data = fgetcsv($handle, 1000, ";")) !== false) {

            $id_tipologia           = null;
            $id_gruppo_transazione  = null;
            $id_metodo_pagamento    = null;
            $id_etichetta           = 0;

            $data_operazione    = $data[0];
            $data_valuta        = $data[1];
            $causale            = $data[2];
            $descrizione        = $data[3];
            $importo            = $data[4];

            $stmt = $conn->prepare('SELECT id_tipologia FROM bilancio_tipologie WHERE nome_tipologia = ?');
            $stmt->bind_param('s', $causale);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $id_tipologia = $row['id_tipologia'] ?? null;

            if (!$id_tipologia) {
                $stmt = $conn->prepare('INSERT INTO bilancio_tipologie (nome_tipologia) VALUES (?)');
                $stmt->bind_param('s', $causale);
                $stmt->execute();
                $id_tipologia = $conn->insert_id;
                $stmt->close();
            }

            list($giorno, $mese, $anno) = explode('/', $data_operazione);
            $data_operazione_db = $anno . '-' . $mese . '-' . $giorno;

            $importo = str_replace('.', '', $importo);
            $importo = str_replace(',', '.', $importo);
            $importo = trim($importo, "'");

            if ($importo < 0) {
                $tabella = 'bilancio_uscite';
            } else {
                $tabella = 'bilancio_entrate';
            }

            $stmt = $conn->prepare(
                'SELECT * FROM bilancio_descrizione2id WHERE id_utente = ? AND ? LIKE CONCAT("%", descrizione, "%") ORDER BY id_d2id DESC'
            );
            $idUtenteSession = $_SESSION['utente_id'];
            $stmt->bind_param('is', $idUtenteSession, $descrizione);
            $stmt->execute();
            $descrizione_gruppo_metodo = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($descrizione_gruppo_metodo) {
              $id_gruppo_transazione    = $descrizione_gruppo_metodo['id_gruppo_transazione'];
              $id_metodo_pagamento      = $descrizione_gruppo_metodo['id_metodo_pagamento'];
              $id_etichetta             = $descrizione_gruppo_metodo['id_etichetta'];
            } else {
                echo "<div class='alert alert-warning' >Senza gruppo o metodo:<br>" . $descrizione . "</div>";
            }

            if ($tabella === 'bilancio_uscite') {
                $stmtIns = $stmtInsertUscite;
            } else {
                $stmtIns = $stmtInsertEntrate;
            }

            $stmtIns->bind_param(
                'iiiisds',
                $idUtenteSession,
                $id_tipologia,
                $id_gruppo_transazione,
                $id_metodo_pagamento,
                $descrizione,
                $importo,
                $data_operazione_db
            );
            $stmtIns->execute();

            $id_tabella = $conn->insert_id;

            if ($id_tabella > 0) {
                $inserita++;
                if ($id_etichetta > 0) {
                    dividi_operazione_per_etichetta($id_etichetta, $tabella, $id_tabella);
                }
            } else {
                echo "<div class='alert alert-danger'>Riga non inserita. Errore:<br>" . $stmtIns->error . "</div>";
            }

            $tot_righe++;
        }

        $stmtInsertUscite->close();
        $stmtInsertEntrate->close();
        fclose($handle);

        echo "<div class='alert alert-success'>Inserite " . $inserita . " righe su " . $tot_righe . "</div><br>" .
            "<a class='btn btn-primary btn-sm ml-2' href='index.php'>Torna alla lista</a>";
    }
} else {
    $idUtenteSession = $_SESSION['utente_id'];

    $stmt = $conn->prepare(
        "SELECT MAX(data_operazione) AS max_data FROM (
            SELECT data_operazione FROM bilancio_entrate WHERE id_utente = ? AND mezzo = 'banca'
            UNION ALL
            SELECT data_operazione FROM bilancio_uscite WHERE id_utente = ? AND mezzo = 'banca'
        ) AS t"
    );
    $stmt->bind_param('ii', $idUtenteSession, $idUtenteSession);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $max_data_banca = $row['max_data'];
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT MAX(m.started_date) AS max_date
         FROM movimenti_revolut m
         JOIN bilancio_gruppi_transazione g ON m.id_gruppo_transazione = g.id_gruppo_transazione
         WHERE g.id_utente = ?"
    );
    $stmt->bind_param('i', $idUtenteSession);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $max_started_revolut = $row['max_date'];
    $stmt->close();

    $max_data_banca_fmt = $max_data_banca ? date('d/m/Y', strtotime($max_data_banca)) : '-';
    $max_started_revolut_fmt = $max_started_revolut ? date('d/m/Y', strtotime($max_started_revolut)) : '-';
?>
<div class="container text-white">
  <h4 class="mb-4">Carica movimenti</h4>
  <div class="alert alert-info">
    Ultima operazione Credit Agricole: <?= $max_data_banca_fmt ?><br>
    Ultimo movimento Revolut: <?= $max_started_revolut_fmt ?>
  </div>
  <form method="post" enctype="multipart/form-data">
    <div class="mb-3">
      <input type="file" name="fileToUpload" class="form-control bg-dark text-white">
    </div>
    <button type="submit" class="btn btn-outline-light w-100">Carica</button>
  </form>
</div>
<?php
}


function dividi_operazione_per_etichetta($id_etichetta, $tabella, $id_tabella)
{
    global $conn;

    $stmt = $conn->prepare(
        'INSERT INTO bilancio_etichette2operazioni (id_etichetta, tabella_operazione, id_tabella) VALUES (?, ?, ?)'
    );
    $stmt->bind_param('isi', $id_etichetta, $tabella, $id_tabella);
    $stmt->execute();
    $id_e2o = $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT utenti_tra_cui_dividere FROM bilancio_etichette WHERE ifnull(utenti_tra_cui_dividere,'')!='' AND id_etichetta = ?"
    );
    $stmt->bind_param('i', $id_etichetta);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $utenti_dividere = $row['utenti_tra_cui_dividere'] ?? null;

    if ($utenti_dividere) {
        $ar_utenti = explode(',', $utenti_dividere);
        $stmtIns = $conn->prepare(
            'INSERT INTO bilancio_utenti2operazioni_etichettate (id_utente, id_e2o) VALUES (?, ?)'
        );
        foreach ($ar_utenti as $id_utente) {
            $stmtIns->bind_param('ii', $id_utente, $id_e2o);
            $stmtIns->execute();
        }
        $stmtIns->close();
    }
}

include 'includes/footer.php';

