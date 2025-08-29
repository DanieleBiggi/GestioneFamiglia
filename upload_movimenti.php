<?php
include 'includes/session_check.php';
include 'includes/db.php';
include 'includes/header.php';

if (isset($_POST['action']) && $_POST['action'] === 'update_movimenti') {
    $ids = $_POST['selected'] ?? [];
    $idGruppo = $_POST['id_gruppo_transazione'] !== '' ? $_POST['id_gruppo_transazione'] : null;
    $descrizioneExtra = $_POST['descrizione_extra'] ?? null;

    foreach ($ids as $token) {
        list($tipo, $id) = explode('-', $token);
        if ($tipo === 'entrate') {
            $tabella = 'bilancio_entrate';
            $colId  = 'id_entrata';
            $stmtUpd = $conn->prepare("UPDATE $tabella SET id_gruppo_transazione = ?, descrizione_extra = ? WHERE $colId = ? AND id_utente = ?");
            $stmtUpd->bind_param('isii', $idGruppo, $descrizioneExtra, $id, $_SESSION['utente_id']);
        } elseif ($tipo === 'uscite') {
            $tabella = 'bilancio_uscite';
            $colId  = 'id_uscita';
            $stmtUpd = $conn->prepare("UPDATE $tabella SET id_gruppo_transazione = ?, descrizione_extra = ? WHERE $colId = ? AND id_utente = ?");
            $stmtUpd->bind_param('isii', $idGruppo, $descrizioneExtra, $id, $_SESSION['utente_id']);
        } elseif ($tipo === 'revolut') {
            $tabella = 'movimenti_revolut';
            $colId  = 'id_movimento_revolut';
            $stmtUpd = $conn->prepare("UPDATE $tabella SET id_gruppo_transazione = ?, descrizione_extra = ? WHERE $colId = ?");
            $stmtUpd->bind_param('isi', $idGruppo, $descrizioneExtra, $id);
        } else {
            continue;
        }
        $stmtUpd->execute();
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

    $stmtIns = $conn->prepare("INSERT INTO bilancio_descrizione2id (id_utente, descrizione, id_gruppo_transazione, id_metodo_pagamento, id_etichetta, descrizione_extra, conto) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtIns->bind_param('isiiiss', $_SESSION['utente_id'], $descrizione, $idGruppo, $idMetodo, $idEtichetta, $descrExtra, $conto);
    $stmtIns->execute();
    $stmtIns->close();

    echo "<div class='alert alert-success'>Descrizione salvata</div>";
} elseif ($_FILES && is_uploaded_file($_FILES['fileToUpload']['tmp_name'])) {
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

        // Precarica le descrizioni note per cercare corrispondenze fuzzy
        $stmtMap = $conn->prepare(
            "SELECT descrizione, id_gruppo_transazione, id_metodo_pagamento, id_etichetta
               FROM bilancio_descrizione2id
              WHERE conto = 'revolut' AND id_utente = ?"
        );
        $stmtMap->bind_param('i', $_SESSION['utente_id']);
        $stmtMap->execute();
        $descrizioni_mappate = $stmtMap->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtMap->close();

        $stmtInsert = $conn->prepare(
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

            // Ricerca miglior corrispondenza con descrizioni note
            $dati_gruppo = trova_descrizione_approssimata($descrizione_orig, $descrizioni_mappate);
            if ($dati_gruppo) {
                $id_gruppo    = $dati_gruppo['id_gruppo_transazione'];
                $id_etichetta = $dati_gruppo['id_etichetta'];
                $descrizione  = $dati_gruppo['descrizione'];
            }

            $descrizione_extra = $descrizione_orig;

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
        $idUtenteSession = $_SESSION['utente_id'];

        // Precarica le descrizioni note per il conto principale
        $stmtMap = $conn->prepare(
            "SELECT descrizione, id_gruppo_transazione, id_metodo_pagamento, id_etichetta
               FROM bilancio_descrizione2id
              WHERE id_utente = ? AND conto = 'credit'"
        );
        $stmtMap->bind_param('i', $idUtenteSession);
        $stmtMap->execute();
        $descrizioni_mappate = $stmtMap->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtMap->close();

        $stmtInsertUscite = $conn->prepare(
            'INSERT INTO bilancio_uscite (id_utente, id_tipologia, id_gruppo_transazione, id_metodo_pagamento, descrizione_operazione, descrizione_extra, importo, data_operazione) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmtInsertEntrate = $conn->prepare(
            'INSERT INTO bilancio_entrate (id_utente, id_tipologia, id_gruppo_transazione, id_metodo_pagamento, descrizione_operazione, descrizione_extra, importo, data_operazione) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
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
            $descrizione_orig   = $descrizione;
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

            // Ricerca della miglior corrispondenza nelle descrizioni note
            $descrizione_gruppo_metodo = trova_descrizione_approssimata($descrizione_orig, $descrizioni_mappate);
            if ($descrizione_gruppo_metodo) {
              $id_gruppo_transazione    = $descrizione_gruppo_metodo['id_gruppo_transazione'];
              $id_metodo_pagamento      = $descrizione_gruppo_metodo['id_metodo_pagamento'];
              $id_etichetta             = $descrizione_gruppo_metodo['id_etichetta'];
              $descrizione              = $descrizione_gruppo_metodo['descrizione'];
            } else {
                echo "<div class='alert alert-warning' >Senza gruppo o metodo:<br>" . $descrizione_orig . "</div>";
            }

            $descrizione_extra = $descrizione_orig;

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

    // Precarica gruppi e metodi di pagamento
    $stmt = $conn->prepare("SELECT id_gruppo_transazione, descrizione FROM bilancio_gruppi_transazione WHERE id_utente = ? ORDER BY descrizione");
    $stmt->bind_param('i', $idUtenteSession);
    $stmt->execute();
    $gruppi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare("SELECT id_metodo_pagamento, descrizione_metodo_pagamento FROM bilancio_metodo_pagamento WHERE attivo = 1 ORDER BY descrizione_metodo_pagamento");
    $stmt->execute();
    $metodi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare("SELECT id_supermercato, descrizione_supermercato FROM ocr_supermercati ORDER BY descrizione_supermercato");
    $stmt->execute();
    $supermercati = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Movimenti recenti
    $movimenti = [];
    if ($max_started_revolut && (!$max_data_banca || strtotime($max_started_revolut) > strtotime($max_data_banca))) {
        $stmt = $conn->prepare(
            "SELECT id_movimento_revolut AS id, 'revolut' AS tipo, description AS descrizione, id_gruppo_transazione, descrizione_extra, id_caricamento\n" 
            . " FROM movimenti_revolut m\n"
            . " LEFT JOIN bilancio_gruppi_transazione g ON m.id_gruppo_transazione = g.id_gruppo_transazione\n"
            . " WHERE g.id_utente = ? OR m.id_gruppo_transazione IS NULL\n"
            . " ORDER BY started_date DESC LIMIT 50"
        );
        $stmt->bind_param('i', $idUtenteSession);
        $stmt->execute();
        $movimenti = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $stmt = $conn->prepare("SELECT id_entrata AS id, 'entrate' AS tipo, descrizione_operazione AS descrizione, id_gruppo_transazione, descrizione_extra, id_caricamento FROM bilancio_entrate WHERE id_utente = ? ORDER BY data_operazione DESC LIMIT 50");
        $stmt->bind_param('i', $idUtenteSession);
        $stmt->execute();
        $movimenti = array_merge($movimenti, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        $stmt->close();

        $stmt = $conn->prepare("SELECT id_uscita AS id, 'uscite' AS tipo, descrizione_operazione AS descrizione, id_gruppo_transazione, descrizione_extra, id_caricamento FROM bilancio_uscite WHERE id_utente = ? ORDER BY data_operazione DESC LIMIT 50");
        $stmt->bind_param('i', $idUtenteSession);
        $stmt->execute();
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
            <td><?= htmlspecialchars($m['id_gruppo_transazione']) ?></td>
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
    fetch('ajax/list_caricamenti.php')
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
      body: JSON.stringify({ id_movimento: currentMovimento, src: currentSrc, id_caricamento: idCar })
    }).then(r => r.json()).then(data => { if (data.success) { alert('Scontrino associato'); location.reload(); } });
  }
  document.getElementById('allegatoForm').addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('id_movimento', currentMovimento);
    fd.append('src', currentSrc);
    fetch('ajax/save_caricamento.php', { method:'POST', body: fd })
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
        if (strpos($descrizione_norm, $target_norm) !== false || strpos($target_norm, $descrizione_norm) !== false) {
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

