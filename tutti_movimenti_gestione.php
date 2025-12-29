<?php include 'includes/session_check.php'; ?>
<?php
require_once 'includes/db.php';
include 'includes/header.php';
setlocale(LC_TIME, 'it_IT.UTF-8');

$idUtente = $_SESSION['utente_id'] ?? 0;

$movimenti_revolut = "";
if (isset($_SESSION['id_famiglia_gestione']) && $_SESSION['id_famiglia_gestione'] == 1) {
    $movimenti_revolut =
        "SELECT id_movimento_revolut AS id,
                COALESCE(NULLIF(descrizione_extra,''), description) AS descrizione,
                bm.descrizione_extra,
                started_date AS data_operazione,
                amount,
                bm.note,
                (SELECT GROUP_CONCAT(CONCAT(e.id_etichetta, ':', e.descrizione) SEPARATOR ',')
                   FROM bilancio_etichette2operazioni eo
                   JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                  WHERE eo.id_tabella = bm.id_movimento_revolut AND eo.tabella_operazione='movimenti_revolut') AS etichette,
                bm.id_gruppo_transazione,
                g.descrizione AS gruppo_descrizione,
                'revolut' AS source,
                'movimenti_revolut' AS tabella,
                NULL AS mezzo,
                'Revolut' AS banca
         FROM v_movimenti_revolut_filtrati bm
         LEFT JOIN bilancio_gruppi_transazione g ON g.id_gruppo_transazione = bm.id_gruppo_transazione
         UNION ALL";
}

$sql = "SELECT * FROM (
            {$movimenti_revolut}
            SELECT be.id_entrata AS id,
                   COALESCE(NULLIF(be.descrizione_extra,''), be.descrizione_operazione) AS descrizione,
                   be.descrizione_extra,
                   be.data_operazione,
                   be.importo AS amount,
                   be.note,
                   (SELECT GROUP_CONCAT(CONCAT(e.id_etichetta, ':', e.descrizione) SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette,
                   be.id_gruppo_transazione,
                   g.descrizione AS gruppo_descrizione,
                   'ca' AS source,
                   'bilancio_entrate' AS tabella,
                   be.mezzo,
                   'Credit Agricole' AS banca
            FROM bilancio_entrate be
            LEFT JOIN bilancio_gruppi_transazione g ON g.id_gruppo_transazione = be.id_gruppo_transazione
            WHERE be.id_utente = {$idUtente}
            UNION ALL
            SELECT bu.id_uscita AS id,
                   COALESCE(NULLIF(bu.descrizione_extra,''), bu.descrizione_operazione) AS descrizione,
                   bu.descrizione_extra,
                   bu.data_operazione,
                   -bu.importo AS amount,
                   bu.note,
                   (SELECT GROUP_CONCAT(CONCAT(e.id_etichetta, ':', e.descrizione) SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = bu.id_uscita AND eo.tabella_operazione='bilancio_uscite') AS etichette,
                   bu.id_gruppo_transazione,
                   g.descrizione AS gruppo_descrizione,
                   'ca' AS source,
                   'bilancio_uscite' AS tabella,
                   bu.mezzo,
                   'Credit Agricole' AS banca
            FROM bilancio_uscite bu
            LEFT JOIN bilancio_gruppi_transazione g ON g.id_gruppo_transazione = bu.id_gruppo_transazione
            WHERE bu.id_utente = {$idUtente}
        ) t
        ORDER BY data_operazione DESC";
$result = $conn->query($sql);
?>

<div class="container py-4">
    <h2 class="text-white mb-3">Tutti i movimenti</h2>

    <div class="card bg-dark text-white border-secondary mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="filterFree">Ricerca libera</label>
                    <input type="text" id="filterFree" class="form-control bg-dark text-white border-secondary" placeholder="Descrizione, descrizione extra o note">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="filterBanca">Banca</label>
                    <input type="text" id="filterBanca" class="form-control bg-dark text-white border-secondary" placeholder="Revolut, Credit Agricole">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="filterData">Data</label>
                    <input type="text" id="filterData" class="form-control bg-dark text-white border-secondary" placeholder="gg/mm/aaaa">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="filterImporto">Importo</label>
                    <input type="text" id="filterImporto" class="form-control bg-dark text-white border-secondary" placeholder="+10, -20">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="filterDescrizione">Descrizione</label>
                    <input type="text" id="filterDescrizione" class="form-control bg-dark text-white border-secondary" placeholder="Testo descrizione">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="filterDescrizioneExtra">Descrizione extra</label>
                    <input type="text" id="filterDescrizioneExtra" class="form-control bg-dark text-white border-secondary" placeholder="Testo extra">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="filterGruppo">Gruppo</label>
                    <input type="text" id="filterGruppo" class="form-control bg-dark text-white border-secondary" placeholder="Nome gruppo">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="filterEtichette">Etichette</label>
                    <input type="text" id="filterEtichette" class="form-control bg-dark text-white border-secondary" placeholder="Tag">
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-dark table-striped align-middle" id="movimentiTable">
            <thead>
                <tr>
                    <th>Banca</th>
                    <th>Data</th>
                    <th>Importo</th>
                    <th>Descrizione</th>
                    <th>Descrizione extra</th>
                    <th>Gruppo</th>
                    <th>Etichette</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($mov = $result->fetch_assoc()): ?>
                    <?php
                    $dataFormattata = date('d/m/Y H:i', strtotime($mov['data_operazione']));
                    $importo = number_format($mov['amount'], 2, ',', '.');
                    $gruppoDescrizione = trim($mov['gruppo_descrizione'] ?? '');
                    $haGruppo = !empty($mov['id_gruppo_transazione']);
                    $badgeClasse = $haGruppo ? 'bg-info text-dark' : 'bg-secondary';
                    if ($haGruppo) {
                        $descrizioneGruppo = $gruppoDescrizione !== ''
                            ? htmlspecialchars($gruppoDescrizione, ENT_QUOTES)
                            : 'ID ' . (int) $mov['id_gruppo_transazione'];
                        $badgeTesto = 'Gruppo: ' . $descrizioneGruppo;
                    } else {
                        $badgeTesto = 'Senza gruppo';
                    }
                    $etichetteRaw = $mov['etichette'] ?? '';
                    $etichetteText = '';
                    if (!empty($etichetteRaw)) {
                        $etichetteParts = [];
                        foreach (explode(',', $etichetteRaw) as $tag) {
                            $tag = trim($tag);
                            if ($tag === '') {
                                continue;
                            }
                            $parts = explode(':', $tag, 2);
                            $etichetteParts[] = $parts[1] ?? $parts[0];
                        }
                        $etichetteText = implode(', ', $etichetteParts);
                    }
                    ?>
                    <tr
                        data-banca="<?= htmlspecialchars($mov['banca'] ?? '', ENT_QUOTES) ?>"
                        data-data="<?= htmlspecialchars($dataFormattata, ENT_QUOTES) ?>"
                        data-importo="<?= htmlspecialchars($mov['amount'] ?? '', ENT_QUOTES) ?>"
                        data-descrizione="<?= htmlspecialchars($mov['descrizione'] ?? '', ENT_QUOTES) ?>"
                        data-descrizione-extra="<?= htmlspecialchars($mov['descrizione_extra'] ?? '', ENT_QUOTES) ?>"
                        data-gruppo="<?= htmlspecialchars($gruppoDescrizione, ENT_QUOTES) ?>"
                        data-etichette="<?= htmlspecialchars($etichetteText, ENT_QUOTES) ?>"
                        data-note="<?= htmlspecialchars($mov['note'] ?? '', ENT_QUOTES) ?>"
                    >
                        <td><?= htmlspecialchars($mov['banca'] ?? '', ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($dataFormattata, ENT_QUOTES) ?></td>
                        <td class="fw-semibold <?= $mov['amount'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= $mov['amount'] >= 0 ? '+' : '' ?><?= $importo ?> €
                        </td>
                        <td><?= htmlspecialchars($mov['descrizione'] ?? '', ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($mov['descrizione_extra'] ?? '', ENT_QUOTES) ?></td>
                        <td><span class="badge <?= $badgeClasse ?>"><?= $badgeTesto ?></span></td>
                        <td>
                            <?php if (!empty($etichetteRaw)): ?>
                                <?php foreach (explode(',', $etichetteRaw) as $tag): ?>
                                    <?php
                                    $tag = trim($tag);
                                    if ($tag === '') {
                                        continue;
                                    }
                                    $parts = explode(':', $tag, 2);
                                    $idTag = $parts[0];
                                    $descTag = $parts[1] ?? $parts[0];
                                    ?>
                                    <span class="badge-etichetta me-1"><?= htmlspecialchars($descTag, ENT_QUOTES) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <div id="noResults" class="text-center text-white mt-3 d-none">Nessun movimento trovato.</div>
</div>

<script src="js/tutti_movimenti_gestione.js"></script>
<?php include 'includes/footer.php'; ?>
