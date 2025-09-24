<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include 'includes/session_check.php';
include 'includes/db.php';
include 'includes/header.php';
$__debug_queries = [];

function prepare_debug(mysqli $conn, string $sql): mysqli_stmt {
    global $__debug_queries;
    $stmt = $conn->prepare($sql);
    $__debug_queries[spl_object_id($stmt)] = $sql;
    return $stmt;
}

function execute_debug(mysqli_stmt $stmt): void {
    global $__debug_queries;
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        $query = $__debug_queries[spl_object_id($stmt)] ?? 'Query non disponibile';
        echo "<pre>Errore nella query:\n$query\n{$e->getMessage()}</pre>";
        throw $e;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'update_movimenti') {
    $ids = $_POST['selected'] ?? [];
    $idGruppo = $_POST['id_gruppo_transazione'] !== '' ? $_POST['id_gruppo_transazione'] : null;
    $descrizioneExtra = $_POST['descrizione_extra'] ?? null;

    foreach ($ids as $token) {
        list($tipo, $id) = explode('-', $token);
        if ($tipo === 'entrate') {
            $tabella = 'bilancio_entrate';
            $colId  = 'id_entrata';
            $stmtUpd = prepare_debug($conn, "UPDATE $tabella SET id_gruppo_transazione = ?, descrizione_extra = ? WHERE $colId = ? AND id_utente = ?");
            $stmtUpd->bind_param('isii', $idGruppo, $descrizioneExtra, $id, $_SESSION['utente_id']);
        } elseif ($tipo === 'uscite') {
            $tabella = 'bilancio_uscite';
            $colId  = 'id_uscita';
            $stmtUpd = prepare_debug($conn, "UPDATE $tabella SET id_gruppo_transazione = ?, descrizione_extra = ? WHERE $colId = ? AND id_utente = ?");
            $stmtUpd->bind_param('isii', $idGruppo, $descrizioneExtra, $id, $_SESSION['utente_id']);
        } elseif ($tipo === 'revolut') {
            $tabella = 'movimenti_revolut';
            $colId  = 'id_movimento_revolut';
            $stmtUpd = prepare_debug($conn, "UPDATE $tabella SET id_gruppo_transazione = ?, descrizione_extra = ? WHERE $colId = ?");
            $stmtUpd->bind_param('isi', $idGruppo, $descrizioneExtra, $id);
        } else {
            continue;
        }
        execute_debug($stmtUpd);
        $stmtUpd->close();
    }

    echo "<div class='alert alert-success'>Movimenti aggiornati</div>";
} elseif (isset($_POST['action']) && $_POST['action'] === 'add_descrizione') {
    $descrizione = $_POST['descrizione'] ?? '';
    $idGruppo    = $_POST['new_id_gruppo_transazione'] !== '' ? $_POST['new_id_gruppo_transazione'] : null;
    $idMetodo    = $_POST['id_metodo_pagamento'] !== '' ? $_POST['id_metodo_pagamento'] : null;
    $idEtichetta = $_POST['id_etichetta'] !== '' ? $_POST['id_etichetta'] : null;
    $descrExtra  = $_POST['descrizione_extra'] ?? null;
    $conto       = $_POST['conto'] ?? 'credit';

    $stmtIns = prepare_debug($conn, "INSERT INTO bilancio_descrizione2id (id_utente, descrizione, id_gruppo_transazione, id_metodo_pagamento, id_etichetta, descrizione_extra, conto) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtIns->bind_param('isiiiss', $_SESSION['utente_id'], $descrizione, $idGruppo, $idMetodo, $idEtichetta, $descrExtra, $conto);
    execute_debug($stmtIns);
    $stmtIns->close();

    echo "<div class='alert alert-success'>Descrizione salvata</div>";
} elseif ($_FILES && is_uploaded_file($_FILES['fileToUpload']['tmp_name'])) {
    $file = $_FILES['fileToUpload']['tmp_name'];
    $handle = fopen($file, "r");

    $firstLine = fgets($handle);
    rewind($handle);

    if (stripos($firstLine, 'Tipo') !== false || stripos($firstLine, 'Data di inizio') !== false) {
        // Importazione movimenti Revolut
        // Leggere e ignorare l'intestazione
        fgetcsv($handle, 1000, ",");

        $tabella = 'movimenti_revolut';
        $nuove_descrizioni_inserite = 0;
        $inserita = 0;
        $idUtenteSession = $_SESSION['utente_id'];
        $assoc_auto  = [];
        $assoc_multi = [];

        // Precarica le descrizioni note per cercare corrispondenze fuzzy
        $stmtMap = prepare_debug($conn,
            "SELECT descrizione, id_gruppo_transazione, id_metodo_pagamento, id_etichetta, descrizione_extra
               FROM bilancio_descrizione2id
              WHERE conto = 'revolut' AND id_utente = ?"
        );
        $stmtMap->bind_param('i', $_SESSION['utente_id']);
        execute_debug($stmtMap);
        $descrizioni_mappate = $stmtMap->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtMap->close();

        $stmtInsert = prepare_debug($conn, 
            "INSERT INTO movimenti_revolut (
                id_gruppo_transazione,
                id_salvadanaio,
                type,
                product,
                started_date,
                completed_date,
                description,
                descrizione_extra,
                amount,
                note
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $id_etichetta   = 0;
            $id_gruppo      = null;
            $id_salvadanaio = null;
            $type           = $data[0];
            $product        = $data[1];
            $started_date   = $data[2];
            $descrizione    = $data[4];
            $descrizione_orig = $descrizione;
            $amount         = (float)$data[5];
            $note           = null;
            $nome           = null;

            if ($type == 'TRANSFER') {
                $descrizione_importazione = str_replace('To EUR', '', $descrizione);
                $descrizione_importazione = trim($descrizione_importazione);

                $stmt = prepare_debug($conn, 
                    'SELECT id_mov2salv, id_salvadanaio, descrizione_importazione, inserita_il, aggiornata_il
                     FROM movimenti_revolut2salvadanaio_importazione
                     WHERE descrizione_importazione = ?'
                );
                $stmt->bind_param('s', $descrizione_importazione);
                execute_debug($stmt);
                $ar_salvadanai = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($ar_salvadanai) {
                    $id_salvadanaio = $ar_salvadanai['id_salvadanaio'];
                } else {
                    $stmtIns = prepare_debug($conn, 
                        'INSERT INTO salvadanai (nome_salvadanaio) VALUES (?)'
                    );
                    $stmtIns->bind_param('s', $descrizione_importazione);
                    execute_debug($stmtIns);
                    $id_salvadanaio = $conn->insert_id;
                    $stmtIns->close();

                    $stmtIns = prepare_debug($conn, 
                        'INSERT INTO movimenti_revolut2salvadanaio_importazione (id_salvadanaio, descrizione_importazione) VALUES (?, ?)'
                    );
                    $stmtIns->bind_param('is', $id_salvadanaio, $descrizione_importazione);
                    execute_debug($stmtIns);
                    $stmtIns->close();
                    $nuove_descrizioni_inserite++;
                }
            }

            $data_completed = $data[3] !== '' ? $data[3] : null;

            // Ricerca miglior corrispondenza con descrizioni note
            $dati_gruppo = trova_descrizione_approssimata($descrizione_orig, $descrizioni_mappate);
            $descrizione_extra = $descrizione_orig;
            if ($dati_gruppo) {
                $id_gruppo    = $dati_gruppo['id_gruppo_transazione'];
                $id_etichetta = $dati_gruppo['id_etichetta'];
                $descrizione  = $dati_gruppo['descrizione'];
                if (isset($dati_gruppo['descrizione_extra']) && trim($dati_gruppo['descrizione_extra']) !== '') {
                    $descrizione_extra = $dati_gruppo['descrizione_extra'];
                }
            }

            $stmtInsert->bind_param(
                'iissssssds',
                $id_gruppo,
                $id_salvadanaio,
                $type,
                $product,
                $started_date,
                $data_completed,
                $descrizione,
                $descrizione_extra,
                $amount,
                $note
            );
            execute_debug($stmtInsert);
            $id_tabella = $conn->insert_id;

            $data_mov = $data_completed ?: $started_date;
            verifica_associazione_scontrino($tabella, $id_tabella, $descrizione_orig, $data_mov, $amount, $assoc_auto, $assoc_multi);

            if ($id_salvadanaio) {
                $data_operazione = $data_completed ?? $started_date;
                $stmtCheck = prepare_debug($conn, 'SELECT data_aggiornamento_manuale FROM salvadanai WHERE id_salvadanaio = ?');
                $stmtCheck->bind_param('i', $id_salvadanaio);
                execute_debug($stmtCheck);
                $salv = $stmtCheck->get_result()->fetch_assoc();
                $stmtCheck->close();

                if (!$salv || !$salv['data_aggiornamento_manuale'] || $salv['data_aggiornamento_manuale'] <= $data_operazione) {
                    $importo_da_aggiungere = -1 * $amount;
                    $stmtUpd = prepare_debug($conn, 'UPDATE salvadanai SET importo_attuale = importo_attuale + ? WHERE id_salvadanaio = ?');
                    $stmtUpd->bind_param('di', $importo_da_aggiungere, $id_salvadanaio);
                    execute_debug($stmtUpd);
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

        if ($assoc_auto || $assoc_multi) {
            echo "<div class='mt-4'><h5>Abbinamento scontrini</h5>";
            echo "<p class='small'>Regole di matching: vengono confrontati la data del movimento e l'importo assoluto con gli scontrini non associati.</p>";
            if ($assoc_auto) {
                echo "<h6>Abbinamenti automatici</h6><ul>";
                foreach ($assoc_auto as $a) {
                    echo "<li>" . htmlspecialchars($a['descrizione']) . " → " . htmlspecialchars($a['caricamento']['nome_file']) . " (" . $a['caricamento']['data_scontrino'] . " €" . $a['caricamento']['totale_scontrino'] . ")</li>";
                }
                echo "</ul>";
            }
            if ($assoc_multi) {
                echo "<h6>Scontrini possibili da confermare</h6>";
                foreach ($assoc_multi as $m) {
                    echo "<div class='mb-3'><div><strong>" . htmlspecialchars($m['descrizione']) . "</strong> (" . $m['data'] . " €" . $m['importo'] . ")</div><ul>";
                    foreach ($m['caricamenti'] as $c) {
                        echo "<li>" . htmlspecialchars($c['nome_file']) . " (" . $c['data_scontrino'] . " €" . $c['totale_scontrino'] . ")</li>";
                    }
                    echo "</ul></div>";
                }
            }
            echo "</div>";
        }

        if ($nuove_descrizioni_inserite > 0) {
            echo "<div class='alert alert-warning mt-4'>Sono state inserite " . $nuove_descrizioni_inserite . " Nuove descrizioni.</div>";
        }
    } else {
        // Importazione bilancio entrate/uscite
        // Leggere e ignorare l'intestazione
        fgetcsv($handle, 1000, ",");

        $tot_righe  = 0;
        $inserita   = 0;
        $idUtenteSession = $_SESSION['utente_id'];
        $assoc_auto  = [];
        $assoc_multi = [];

        // Precarica le descrizioni note per il conto principale
        $stmtMap = prepare_debug($conn,
            "SELECT descrizione, id_gruppo_transazione, id_metodo_pagamento, id_etichetta, descrizione_extra
               FROM bilancio_descrizione2id
              WHERE id_utente = ? AND conto = 'credit'"
        );
        $stmtMap->bind_param('i', $idUtenteSession);
        execute_debug($stmtMap);
        $descrizioni_mappate = $stmtMap->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtMap->close();

        $stmtInsertUscite = prepare_debug($conn, 
            'INSERT INTO bilancio_uscite (id_utente, id_tipologia, id_gruppo_transazione, id_metodo_pagamento, descrizione_operazione, descrizione_extra, importo, data_operazione) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmtInsertEntrate = prepare_debug($conn, 
            'INSERT INTO bilancio_entrate (id_utente, id_tipologia, id_gruppo_transazione, id_metodo_pagamento, descrizione_operazione, descrizione_extra, importo, data_operazione) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        while (($data = fgetcsv($handle, 1000, ";")) !== false) {

            $id_tipologia           = null;
            $id_gruppo_transazione  = null;
            $id_metodo_pagamento    = null;
            $id_etichetta           = 0;

            $data_operazione    = $data[0];
            $data_valuta        = @$data[1];
            $causale            = @$data[2];
            $descrizione        = @$data[3];
            $descrizione_orig   = $descrizione;
            $importo            = @$data[4];

            $stmt = prepare_debug($conn, 'SELECT id_tipologia FROM bilancio_tipologie WHERE nome_tipologia = ?');
            $stmt->bind_param('s', $causale);
            execute_debug($stmt);
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $id_tipologia = $row['id_tipologia'] ?? null;

            if (!$id_tipologia) {
                $stmt = prepare_debug($conn, 'INSERT INTO bilancio_tipologie (nome_tipologia) VALUES (?)');
                $stmt->bind_param('s', $causale);
                execute_debug($stmt);
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

            // Ricerca della miglior corrispondenza nelle descrizioni note
            $descrizione_gruppo_metodo = trova_descrizione_approssimata($descrizione_orig, $descrizioni_mappate);
            $descrizione_extra = $descrizione_orig;
            if ($descrizione_gruppo_metodo) {
              $id_gruppo_transazione    = $descrizione_gruppo_metodo['id_gruppo_transazione'];
              $id_metodo_pagamento      = $descrizione_gruppo_metodo['id_metodo_pagamento'];
              $id_etichetta             = $descrizione_gruppo_metodo['id_etichetta'];
              $descrizione              = $descrizione_gruppo_metodo['descrizione'];
              if (isset($descrizione_gruppo_metodo['descrizione_extra']) && trim($descrizione_gruppo_metodo['descrizione_extra']) !== '') {
                  $descrizione_extra = $descrizione_gruppo_metodo['descrizione_extra'];
              }
            } else {
                echo "<div class='alert alert-warning' >Senza gruppo o metodo:<br>" . $descrizione_orig . "</div>";
            }

            if ($tabella === 'bilancio_uscite') {
                $stmtIns = $stmtInsertUscite;
            } else {
                $stmtIns = $stmtInsertEntrate;
            }

            $stmtIns->bind_param(
                'iiiissds',
                $idUtenteSession,
                $id_tipologia,
                $id_gruppo_transazione,
                $id_metodo_pagamento,
                $descrizione,
                $descrizione_extra,
                $importo,
                $data_operazione_db
            );
            execute_debug($stmtIns);

            $id_tabella = $conn->insert_id;

            verifica_associazione_scontrino($tabella, $id_tabella, $descrizione_orig, $data_operazione_db, $importo, $assoc_auto, $assoc_multi);

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

        if ($assoc_auto || $assoc_multi) {
            echo "<div class='mt-4'><h5>Abbinamento scontrini</h5>";
            echo "<p class='small'>Regole di matching: vengono confrontati la data del movimento e l'importo assoluto con gli scontrini non associati.</p>";
            if ($assoc_auto) {
                echo "<h6>Abbinamenti automatici</h6><ul>";
                foreach ($assoc_auto as $a) {
                    echo "<li>" . htmlspecialchars($a['descrizione']) . " → " . htmlspecialchars($a['caricamento']['nome_file']) . " (" . $a['caricamento']['data_scontrino'] . " €" . $a['caricamento']['totale_scontrino'] . ")</li>";
                }
                echo "</ul>";
            }
            if ($assoc_multi) {
                echo "<h6>Scontrini possibili da confermare</h6>";
                foreach ($assoc_multi as $m) {
                    echo "<div class='mb-3'><div><strong>" . htmlspecialchars($m['descrizione']) . "</strong> (" . $m['data'] . " €" . $m['importo'] . ")</div><ul>";
                    foreach ($m['caricamenti'] as $c) {
                        echo "<li>" . htmlspecialchars($c['nome_file']) . " (" . $c['data_scontrino'] . " €" . $c['totale_scontrino'] . ")</li>";
                    }
                    echo "</ul></div>";
                }
            }
            echo "</div>";
        }
    }
} else {
    $idUtenteSession = $_SESSION['utente_id'];

    $stmt = prepare_debug($conn, 
        "SELECT MAX(data_operazione) AS max_data FROM (
            SELECT data_operazione FROM bilancio_entrate WHERE id_utente = ? AND mezzo = 'banca'
            UNION ALL
            SELECT data_operazione FROM bilancio_uscite WHERE id_utente = ? AND mezzo = 'banca'
        ) AS t"
    );
    $stmt->bind_param('ii', $idUtenteSession, $idUtenteSession);
    execute_debug($stmt);
    $row = $stmt->get_result()->fetch_assoc();
    $max_data_banca = $row['max_data'];
    $stmt->close();

    $stmt = prepare_debug($conn, 
        "SELECT MAX(m.started_date) AS max_date
         FROM movimenti_revolut m
         JOIN bilancio_gruppi_transazione g ON m.id_gruppo_transazione = g.id_gruppo_transazione
         WHERE g.id_utente = ?"
    );
    $stmt->bind_param('i', $idUtenteSession);
    execute_debug($stmt);
    $row = $stmt->get_result()->fetch_assoc();
    $max_started_revolut = $row['max_date'];
    $stmt->close();

    // Precarica gruppi e metodi di pagamento
    $stmt = prepare_debug($conn, "SELECT id_gruppo_transazione, descrizione FROM bilancio_gruppi_transazione WHERE id_utente = ? ORDER BY descrizione");
    $stmt->bind_param('i', $idUtenteSession);
    execute_debug($stmt);
    $gruppi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    // Mappa [id_gruppo_transazione => descrizione] per uso rapido nella tabella
    $gruppiMap = [];
    foreach ($gruppi as $g) {
        $gruppiMap[$g['id_gruppo_transazione']] = $g['descrizione'];
    }

    $stmt = prepare_debug($conn, "SELECT id_metodo_pagamento, descrizione_metodo_pagamento FROM bilancio_metodo_pagamento WHERE attivo = 1 ORDER BY descrizione_metodo_pagamento");
    execute_debug($stmt);
    $metodi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = prepare_debug($conn, "SELECT id_supermercato, descrizione_supermercato FROM ocr_supermercati ORDER BY descrizione_supermercato");
    execute_debug($stmt);
    $supermercati = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Movimenti recenti
    $movimenti = [];
    if ($max_started_revolut && (!$max_data_banca || strtotime($max_started_revolut) > strtotime($max_data_banca))) {
        // Mostra solo i movimenti Revolut, prelevati dalla vista dedicata
        $stmt = prepare_debug($conn,
            "SELECT id_movimento_revolut AS id, 'revolut' AS tipo, description AS descrizione, id_gruppo_transazione, descrizione_extra, id_caricamento\n"
            . " FROM v_movimenti_revolut\n"
            . " ORDER BY started_date DESC LIMIT 50"
        );
        execute_debug($stmt);
        $movimenti = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $stmt = prepare_debug($conn, "SELECT id_entrata AS id, 'entrate' AS tipo, descrizione_operazione AS descrizione, id_gruppo_transazione, descrizione_extra, id_caricamento FROM bilancio_entrate WHERE id_utente = ? ORDER BY data_operazione DESC LIMIT 50");
        $stmt->bind_param('i', $idUtenteSession);
        execute_debug($stmt);
        $movimenti = array_merge($movimenti, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        $stmt->close();

        $stmt = prepare_debug($conn, "SELECT id_uscita AS id, 'uscite' AS tipo, descrizione_operazione AS descrizione, id_gruppo_transazione, descrizione_extra, id_caricamento FROM bilancio_uscite WHERE id_utente = ? ORDER BY data_operazione DESC LIMIT 50");
        $stmt->bind_param('i', $idUtenteSession);
        execute_debug($stmt);
        $movimenti = array_merge($movimenti, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        $stmt->close();
    }

    $max_data_banca_fmt = $max_data_banca ? date('d/m/Y', strtotime($max_data_banca)) : '-';
    $max_started_revolut_fmt = $max_started_revolut ? date('d/m/Y', strtotime($max_started_revolut)) : '-';
?>
<div class="container text-white">
  <h4 class="mb-4">Carica movimenti</h4>
  <div class="alert alert-info">
    Ultima operazione Credit Agricole: <?= $max_data_banca_fmt ?><br>
    Ultimo movimento Revolut: <?= $max_started_revolut_fmt ?>
  </div>
  <form method="post" enctype="multipart/form-data" class="mb-5">
    <div class="mb-3">
      <input type="file" name="fileToUpload" class="form-control bg-dark text-white">
    </div>
    <button type="submit" class="btn btn-outline-light w-100">Carica</button>
  </form>

  <h5 class="mb-3">Modifica movimenti</h5>
  <form method="post" class="mb-5">
    <input type="hidden" name="action" value="update_movimenti">
    <div class="row g-2 mb-3">
      <div class="col-md-4">
        <select name="id_gruppo_transazione" class="form-select bg-dark text-white">
          <option value="">Seleziona gruppo</option>
          <?php foreach ($gruppi as $g): ?>
            <option value="<?= (int)$g['id_gruppo_transazione'] ?>"><?= htmlspecialchars($g['descrizione']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <input type="text" name="descrizione_extra" class="form-control bg-dark text-white" placeholder="Descrizione extra">
      </div>
      <div class="col-md-4">
        <button type="submit" class="btn btn-primary w-100">Aggiorna selezionati</button>
      </div>
    </div>
    <table class="table table-dark table-striped">
      <thead>
        <tr>
          <th scope="col"></th>
          <th scope="col">Tipo</th>
          <th scope="col">Descrizione</th>
          <th scope="col">Gruppo</th>
          <th scope="col">Extra</th>
          <th scope="col">Scontrino</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($movimenti as $m): ?>
          <tr>
            <td><input type="checkbox" name="selected[]" value="<?= $m['tipo'] . '-' . $m['id'] ?>"></td>
            <td><?= htmlspecialchars($m['tipo']) ?></td>
            <td><?= htmlspecialchars($m['descrizione']) ?></td>
            <td><?= htmlspecialchars($gruppiMap[$m['id_gruppo_transazione']] ?? '') ?></td>
            <td><?= htmlspecialchars($m['descrizione_extra']) ?></td>
            <td><i class="bi bi-paperclip" onclick="openAllegatoModal(<?= $m['id'] ?>,'<?= $m['tipo'] ?>')"></i></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </form>

  <h5 class="mb-3">Nuova descrizione predefinita</h5>
  <form method="post" class="mb-5">
    <input type="hidden" name="action" value="add_descrizione">
    <div class="mb-3">
      <input type="text" name="descrizione" class="form-control bg-dark text-white" placeholder="Descrizione" required>
    </div>
    <div class="mb-3">
      <select name="new_id_gruppo_transazione" class="form-select bg-dark text-white" required>
        <option value="">Seleziona gruppo</option>
        <?php foreach ($gruppi as $g): ?>
          <option value="<?= (int)$g['id_gruppo_transazione'] ?>"><?= htmlspecialchars($g['descrizione']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <select name="id_metodo_pagamento" class="form-select bg-dark text-white" required>
        <?php foreach ($metodi as $m): ?>
          <option value="<?= (int)$m['id_metodo_pagamento'] ?>"><?= htmlspecialchars($m['descrizione_metodo_pagamento']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <input type="number" name="id_etichetta" class="form-control bg-dark text-white" placeholder="ID etichetta (opzionale)">
    </div>
    <div class="mb-3">
      <input type="text" name="descrizione_extra" class="form-control bg-dark text-white" placeholder="Descrizione extra (opzionale)">
    </div>
    <div class="mb-3">
      <select name="conto" class="form-select bg-dark text-white">
        <option value="credit">Credit</option>
        <option value="revolut">Revolut</option>
      </select>
    </div>
    <button type="submit" class="btn btn-success w-100">Inserisci</button>
  </form>

  <div class="modal fade" id="allegatoModal" tabindex="-1">
    <div class="modal-dialog">
      <form class="modal-content bg-dark text-white" id="allegatoForm" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Gestisci scontrino</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Scontrini da associare</label>
            <input type="text" class="form-control bg-secondary text-white mb-2" placeholder="Filtra..." oninput="filterCaricamenti(this.value)">
            <div id="listaCaricamenti" class="list-group" style="max-height:200px;overflow:auto;"></div>
          </div>
          <hr>
          <div class="mb-3">
            <label class="form-label">File</label>
            <input type="file" class="form-control bg-secondary text-white" name="nome_file" id="allegatoFile" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Supermercato</label>
            <select class="form-select bg-secondary text-white" name="id_supermercato" id="idSupermercato">
              <option value="0"></option>
              <?php foreach ($supermercati as $s): ?>
              <option value="<?= (int)$s['id_supermercato'] ?>"><?= htmlspecialchars($s['descrizione_supermercato']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Data scontrino</label>
            <input type="date" class="form-control bg-secondary text-white" name="data_scontrino" id="dataScontrino">
          </div>
          <div class="mb-3">
            <label class="form-label">Totale scontrino</label>
            <input type="number" step="0.01" class="form-control bg-secondary text-white" name="totale_scontrino" id="totaleScontrino">
          </div>
          <div class="mb-3">
            <label class="form-label">Descrizione</label>
            <input type="text" class="form-control bg-secondary text-white" name="descrizione" id="descrizioneScontrino">
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary w-100">Salva</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  let caricamenti = [];
  let currentMovimento = null;
  let currentSrc = null;
  function openAllegatoModal(id, tipo) {
    currentMovimento = id;
    currentSrc = tipo === 'revolut' ? 'movimenti_revolut' : (tipo === 'entrate' ? 'bilancio_entrate' : 'bilancio_uscite');
    const form = document.getElementById('allegatoForm');
    form.reset();
    document.getElementById('listaCaricamenti').innerHTML = '';
    fetch('ajax/list_caricamenti.php', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => { caricamenti = data; populateCaricamenti(data); });
    new bootstrap.Modal(document.getElementById('allegatoModal')).show();
  }
  function populateCaricamenti(data) {
    const list = document.getElementById('listaCaricamenti');
    list.innerHTML = '';
    data.forEach(c => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'list-group-item list-group-item-action bg-secondary text-white';
      const ds = c.data_scontrino ? c.data_scontrino.substring(0,10) : '';
      btn.textContent = `${c.nome_file} ${ds} €${c.totale_scontrino}`;
      btn.onclick = () => associateCaricamento(c.id_caricamento);
      list.appendChild(btn);
    });
  }
  function filterCaricamenti(term) {
    term = term.toLowerCase();
    document.querySelectorAll('#listaCaricamenti button').forEach(btn => {
      btn.style.display = btn.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
  }
  function associateCaricamento(idCar) {
    fetch('ajax/associate_caricamento.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify({ id_movimento: currentMovimento, src: currentSrc, id_caricamento: idCar })
    }).then(r => r.json()).then(data => { if (data.success) { alert('Scontrino associato'); location.reload(); } });
  }
  document.getElementById('allegatoForm').addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('id_movimento', currentMovimento);
    fd.append('src', currentSrc);
    fetch('ajax/save_caricamento.php', { method:'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => { if (data.success) { alert('Scontrino salvato'); location.reload(); } });
  });
  </script>
</div>
<?php
}


function trova_descrizione_approssimata($descrizione_importata, $descrizioni_mappate)
{
    $descrizione_norm = normalizza_descrizione($descrizione_importata);
    $migliore = null;
    $percentuale = 0;
    foreach ($descrizioni_mappate as $riga) {
        $target_norm = normalizza_descrizione($riga['descrizione']);
        if (@strpos($descrizione_norm, $target_norm) !== false || @strpos($target_norm, $descrizione_norm) !== false) {
            return $riga;
        }
        similar_text($target_norm, $descrizione_norm, $perc);
        if ($perc > $percentuale) {
            $percentuale = $perc;
            $migliore = $riga;
        }
    }
    return $percentuale >= 60 ? $migliore : null;
}

function normalizza_descrizione($stringa)
{
    $stringa = strtolower($stringa);
    return preg_replace('/[^a-z0-9]/', '', $stringa);
}

/**
 * Tenta di associare automaticamente uno scontrino al movimento appena inserito.
 * Il match avviene confrontando la data (solo giorno) e l'importo assoluto con
 * gli scontrini ancora non collegati. In caso di corrispondenza unica viene
 * effettuato l'update automatico; in caso di più corrispondenze vengono restituite
 * al chiamante per una conferma manuale.
 */
function verifica_associazione_scontrino($tabella, $id_tabella, $descrizione, $data_mov, $importo, &$assoc_auto, &$assoc_multi)
{
    global $conn, $idUtenteSession;

    $importo = abs((float)$importo);
    $data_mov = substr($data_mov, 0, 10);

    $sql = "SELECT id_caricamento, nome_file, data_scontrino, totale_scontrino\n"
         . "FROM ocr_caricamenti c\n"
         . "WHERE id_utente = ? AND DATE(data_scontrino) = ? AND totale_scontrino = ?\n"
         . "  AND id_caricamento NOT IN (\n"
         . "    SELECT id_caricamento FROM bilancio_entrate WHERE id_caricamento IS NOT NULL\n"
         . "    UNION SELECT id_caricamento FROM bilancio_uscite WHERE id_caricamento IS NOT NULL\n"
         . "    UNION SELECT id_caricamento FROM movimenti_revolut WHERE id_caricamento IS NOT NULL\n"
         . "  )";
    $stmt = prepare_debug($conn, $sql);
    $stmt->bind_param('isd', $idUtenteSession, $data_mov, $importo);
    execute_debug($stmt);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($rows) === 1) {
        $id_car = $rows[0]['id_caricamento'];
        $idFields = [
            'movimenti_revolut' => 'id_movimento_revolut',
            'bilancio_entrate'  => 'id_entrata',
            'bilancio_uscite'   => 'id_uscita',
        ];
        if (isset($idFields[$tabella])) {
            $field = $idFields[$tabella];
            $stmtUp = prepare_debug($conn, "UPDATE $tabella SET id_caricamento=? WHERE $field=?");
            $stmtUp->bind_param('ii', $id_car, $id_tabella);
            execute_debug($stmtUp);
            $stmtUp->close();
            $assoc_auto[] = [
                'tabella'      => $tabella,
                'id_movimento' => $id_tabella,
                'descrizione'  => $descrizione,
                'caricamento'  => $rows[0],
            ];
        }
    } elseif (count($rows) > 1) {
        $assoc_multi[] = [
            'tabella'      => $tabella,
            'id_movimento' => $id_tabella,
            'descrizione'  => $descrizione,
            'data'         => $data_mov,
            'importo'      => $importo,
            'caricamenti'  => $rows,
        ];
    }
}

function dividi_operazione_per_etichetta($id_etichetta, $tabella, $id_tabella)
{
    global $conn;

    $stmt = prepare_debug($conn, 
        'INSERT INTO bilancio_etichette2operazioni (id_etichetta, tabella_operazione, id_tabella) VALUES (?, ?, ?)'
    );
    $stmt->bind_param('isi', $id_etichetta, $tabella, $id_tabella);
    execute_debug($stmt);
    $id_e2o = $conn->insert_id;
    $stmt->close();

    $stmt = prepare_debug($conn, 
        "SELECT utenti_tra_cui_dividere FROM bilancio_etichette WHERE ifnull(utenti_tra_cui_dividere,'')!='' AND id_etichetta = ?"
    );
    $stmt->bind_param('i', $id_etichetta);
    execute_debug($stmt);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $utenti_dividere = $row['utenti_tra_cui_dividere'] ?? null;

    if ($utenti_dividere) {
        $ar_utenti = explode(',', $utenti_dividere);
        $stmtIns = prepare_debug($conn, 
            'INSERT INTO bilancio_utenti2operazioni_etichettate (id_utente, id_e2o) VALUES (?, ?)'
        );
        foreach ($ar_utenti as $id_utente) {
            $stmtIns->bind_param('ii', $id_utente, $id_e2o);
            execute_debug($stmtIns);
        }
        $stmtIns->close();
    }
}

include 'includes/footer.php';
