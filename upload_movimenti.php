<?php
include 'includes/session_check.php';
include 'includes/header.php';

if (is_uploaded_file($_FILES['fileToUpload']['tmp_name'])) {
    $file = $_FILES['fileToUpload']['tmp_name'];
    $handle = fopen($file, "r");

    $firstLine = fgets($handle);
    rewind($handle);

    if (stripos($firstLine, 'Type') !== false || stripos($firstLine, 'Started Date') !== false) {
        // Importazione movimenti Revolut
        // Leggere e ignorare l'intestazione
        fgetcsv($handle, 1000, ",");

        $tabella = "movimenti_revolut";
        $query_testata =
        "INSERT INTO
            `movimenti_revolut`
        (
            `id_gruppo_transazione`,
            `id_salvadanaio`,
            `type`,
            `product`,

            `started_date`,
            `completed_date`,
            `description`,

            `amount`,
            `note`)
        VALUE ";

        $nuove_descrizioni_inserite = 0;
        $inserita                   = 0;

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $id_etichetta       = 0;
            $id_gruppo          = null;
            $id_salvadanaio     = NULL;
            $type               = $data[0];
            $descrizione        = $data[4];

            if ($type == "TRANSFER") {
                $descrizione_importazione = str_replace("To EUR", "", $descrizione);
                $descrizione_importazione = trim($descrizione_importazione);

                $SQL =
                "SELECT
                    `id_mov2salv`,
                    `id_salvadanaio`,
                    `descrizione_importazione`,
                    `inserita_il`,
                    `aggiornata_il`
                FROM
                    `movimenti_revolut2salvadanaio_importazione`
                WHERE
                    `descrizione_importazione` = ".QuotedValue($descrizione_importazione, DATATYPE_STRING);

                $ar_salvadanai = ExecuteRow($SQL);

                if ($ar_salvadanai) {
                    $id_salvadanaio = $ar_salvadanai['id_salvadanaio'];
                } else {
                    $SQL =
                    "INSERT INTO
                        `salvadanai`
                    (
                        `nome_salvadanaio`)
                    VALUE (
                        ".QuotedValue($descrizione_importazione, DATATYPE_STRING)." );";

                    Execute($SQL);

                    $conn = GetConnection();
                    $id_salvadanaio = $conn->Insert_ID();

                    $SQL =
                    "INSERT INTO
                        `movimenti_revolut2salvadanaio_importazione`
                    (
                        `id_salvadanaio`,
                        `descrizione_importazione`)
                    VALUE (
                        ".QuotedValue($id_salvadanaio, DATATYPE_STRING).",
                        ".QuotedValue($descrizione_importazione, DATATYPE_STRING)." );";

                    Execute($SQL);
                    $nuove_descrizioni_inserite++;
                }
            }

            if ($data[3] != "") {
                $data_completed = $data[3];
            } else {
                $data_completed = null;
            }

            $SQL =
            "SELECT
                `id_d2id`,
                `id_utente`,
                `id_gruppo_transazione`,
                `id_metodo_pagamento`,
                `id_etichetta`
            FROM
                `bilancio_descrizione2id`
            WHERE
                conto = 'revolut' AND
                (
                ".QuotedValue($descrizione, DATATYPE_STRING)." LIKE CONCAT('%', descrizione, '%') OR
                ".QuotedValue($nome, DATATYPE_STRING)." LIKE CONCAT('%', descrizione, '%')
            )";

            $dati_gruppo = ExecuteRow($SQL);

            if ($dati_gruppo) {
                $id_gruppo      = $dati_gruppo['id_gruppo_transazione'];
                $id_etichetta   = $dati_gruppo['id_etichetta'];
            }

            $query =
            $query_testata.
            "(
            ".QuotedValue($id_gruppo, DATATYPE_NUMBER).",
            ".QuotedValue($id_salvadanaio, DATATYPE_NUMBER).",
            ".QuotedValue($type, DATATYPE_STRING).",
            ".QuotedValue($data[1], DATATYPE_STRING).",

            ".QuotedValue($data[2], DATATYPE_DATE).",
            ".QuotedValue($data_completed, DATATYPE_DATE).",
            ".QuotedValue($descrizione, DATATYPE_STRING).",

            ".QuotedValue($data[5], DATATYPE_NUMBER).",
            NULL);";

            Execute($query);
            $conn = GetConnection();
            $id_tabella = $conn->Insert_ID();

            if ($id_etichetta > 0) {
                dividi_operazione_per_etichetta($id_etichetta, $tabella, $id_tabella);
            }

            $inserita++;
        }

        fclose($handle);

        echo
        "<div class='alert alert-success'>Inserite ".$inserita." righe</div><br>".
        "<a class='btn btn-primary btn-sm ml-2' href='movimenti_revolutlist.php'>Torna alla lista</a>";

        if ($nuove_descrizioni_inserite > 0) {
            echo
            "<div class='alert alert-warning mt-4'>Sono state inserite ".$nuove_descrizioni_inserite." Nuove descrizioni.</div><br>".
            "<a class='btn btn-primary btn-sm ml-2' href='movimenti_revolut2salvadanaio_importazionelist.php'>Clicca qui per associarle ai salvadanai</a>";
        }
    } else {
        // Importazione bilancio entrate/uscite
        // Leggere e ignorare l'intestazione
        fgetcsv($handle, 1000, ",");

        $tot_righe  = 0;
        $inserita   = 0;

        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {

            $id_tipologia           = NULL;
            $id_gruppo_transazione  = NULL;
            $id_metodo_pagamento    = NULL;
            $id_etichetta           = 0;

            $data_operazione    = $data[0];
            $data_valuta        = $data[1];
            $causale            = $data[2];
            $descrizione        = $data[3];
            $importo            = $data[4];


            $SQLtipologia =
            "SELECT
                id_tipologia
            FROM
                bilancio_tipologie
            WHERE
                nome_tipologia = ".QuotedValue($causale, DATATYPE_STRING);

            $id_tipologia = ExecuteScalar($SQLtipologia);

            if (!$id_tipologia) {
                $SQltipo =
                "INSERT INTO
                  `bilancio_tipologie`
                (
                  `nome_tipologia`)
                VALUE (
                  ".QuotedValue($causale, DATATYPE_STRING)." );";

                $conn = GetConnection();

                Execute($SQltipo);

                $id_tipologia = $conn->Insert_ID();
            }

            list($giorno, $mese, $anno) = explode('/', $data_operazione);
            $data_operazione_db = $anno . '-' . $mese . '-' . $giorno;

            $importo = str_replace(".", "", $importo);
            $importo = str_replace(",", ".", $importo);
            $importo = trim($importo, "'");

            if ($importo < 0) {
                $tabella = "bilancio_uscite";
            } else {
                $tabella = "bilancio_entrate";
            }

            $SQLdescrizione =
            "SELECT
                    *
            FROM
                    `bilancio_descrizione2id`
            WHERE
                id_utente = ".QuotedValue(CurrentUserID(), DATATYPE_NUMBER)." AND
                ".QuotedValue($descrizione, DATATYPE_STRING)." LIKE CONCAT('%', descrizione, '%')
            ORDER BY
                id_d2id DESC";

            $descrizione_gruppo_metodo = ExecuteRow($SQLdescrizione);

            if ($descrizione_gruppo_metodo) {
              $id_gruppo_transazione    = $descrizione_gruppo_metodo['id_gruppo_transazione'];
              $id_metodo_pagamento      = $descrizione_gruppo_metodo['id_metodo_pagamento'];
              $id_etichetta             = $descrizione_gruppo_metodo['id_etichetta'];
            } else {
                echo
                "<div class='alert alert-warning' >Senza gruppo o metodo:<br>".$descrizione."</div>";
            }

            $SQL =
            "INSERT INTO
              `".$tabella."`
            (
              `id_utente`,
              `id_tipologia`,
              `id_gruppo_transazione`,
              `id_metodo_pagamento`,
              `descrizione_operazione`,
              `importo`,
              `data_operazione`)
            VALUE (
              ".CurrentUserID().",
              ".QuotedValue($id_tipologia, DATATYPE_NUMBER).",
              ".QuotedValue($id_gruppo_transazione, DATATYPE_NUMBER).",
              ".QuotedValue($id_metodo_pagamento, DATATYPE_NUMBER).",
              ".QuotedValue($descrizione, DATATYPE_STRING).",
              ".QuotedValue($importo, DATATYPE_NUMBER).",
              ".QuotedValue($data_operazione_db, DATATYPE_DATE)." );";

            Execute($SQL);

            $conn = GetConnection();

            $id_tabella = $conn->Insert_ID();

            if ($id_tabella > 0) {
                $inserita++;
                if ($id_etichetta > 0) {
                    dividi_operazione_per_etichetta($id_etichetta, $tabella, $id_tabella);
                }
            } else {
                echo "<div class='alert alert-danger'>Riga non inserita. Errore:<br>".nl2br($SQL)."</div>";
            }

            $tot_righe++;
        }

        fclose($handle);

        echo
        "<div class='alert alert-success'>Inserite ".$inserita." righe su ".$tot_righe."</div><br>".
        "<a class='btn btn-primary btn-sm ml-2' href='v_elenco_operazionilist.php'>Torna alla lista</a>";
    }
} else {
?>
<div class="container text-white">
  <h4 class="mb-4">Carica movimenti</h4>
  <form method="post" enctype="multipart/form-data">
    <div class="mb-3">
      <input type="file" name="fileToUpload" class="form-control bg-dark text-white">
    </div>
    <button type="submit" class="btn btn-primary">Carica</button>
  </form>
</div>
<?php
}

include 'includes/footer.php';
