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

$movimenti = [];
$gruppi = [];
$etichetteLista = [];
$banche = [];
if ($result) {
    while ($mov = $result->fetch_assoc()) {
        $movimenti[] = $mov;
        $banca = trim((string) ($mov['banca'] ?? ''));
        if ($banca !== '') {
            $banche[$banca] = true;
        }

        $idGruppo = $mov['id_gruppo_transazione'] ?? null;
        if (!empty($idGruppo)) {
            $descrizioneGruppo = trim((string) ($mov['gruppo_descrizione'] ?? ''));
            $gruppi[(int) $idGruppo] = $descrizioneGruppo !== '' ? $descrizioneGruppo : 'ID ' . (int) $idGruppo;
        }

        $etichetteRaw = $mov['etichette'] ?? '';
        if (!empty($etichetteRaw)) {
            foreach (explode(',', $etichetteRaw) as $tag) {
                $tag = trim($tag);
                if ($tag === '') {
                    continue;
                }
                $parts = explode(':', $tag, 2);
                $label = trim($parts[1] ?? $parts[0]);
                if ($label !== '') {
                    $etichetteLista[$label] = true;
                }
            }
        }
    }
}

ksort($gruppi);
ksort($etichetteLista);
$banche = array_keys($banche);
sort($banche);
?>

<div class="container-fluid py-4">
    <h2 class="text-white mb-3">Tutti i movimenti</h2>

    <div class="card bg-dark text-white border-secondary mb-3">
        <div class="card-body">
            <form id="filtersForm">
                <div class="row g-3">
                    <div class="col-12 col-xl-4">
                        <label class="form-label" for="filterFree">Ricerca libera</label>
                        <input type="text" id="filterFree" class="form-control bg-dark text-white border-secondary" placeholder="Descrizione, descrizione extra o note">
                    </div>
                    <div class="col-6 col-xl-2">
                        <label class="form-label" for="filterBanca">Banca</label>
                        <select id="filterBanca" class="form-select bg-dark text-white border-secondary">
                            <option value="">Tutte</option>
                            <?php foreach ($banche as $banca): ?>
                                <option value="<?= htmlspecialchars($banca, ENT_QUOTES) ?>"><?= htmlspecialchars($banca, ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-xl-3">
                        <label class="form-label" for="filterDataDa">Data (da)</label>
                        <input type="date" id="filterDataDa" class="form-control bg-dark text-white border-secondary">
                    </div>
                    <div class="col-6 col-xl-3">
                        <label class="form-label" for="filterDataA">Data (a)</label>
                        <input type="date" id="filterDataA" class="form-control bg-dark text-white border-secondary">
                    </div>
                    <div class="col-6 col-xl-3">
                        <label class="form-label" for="filterImportoDa">Importo (da)</label>
                        <input type="number" step="0.01" id="filterImportoDa" class="form-control bg-dark text-white border-secondary" placeholder="Minimo">
                    </div>
                    <div class="col-6 col-xl-3">
                        <label class="form-label" for="filterImportoA">Importo (a)</label>
                        <input type="number" step="0.01" id="filterImportoA" class="form-control bg-dark text-white border-secondary" placeholder="Massimo">
                    </div>
                    <div class="col-6 col-xl-3">
                        <label class="form-label" for="filterDescrizione">Descrizione</label>
                        <input type="text" id="filterDescrizione" class="form-control bg-dark text-white border-secondary" placeholder="Testo descrizione">
                    </div>
                    <div class="col-6 col-xl-3">
                        <label class="form-label" for="filterDescrizioneExtra">Descrizione extra</label>
                        <input type="text" id="filterDescrizioneExtra" class="form-control bg-dark text-white border-secondary" placeholder="Testo extra">
                    </div>
                    <div class="col-6 col-xl-3">
                        <label class="form-label" for="filterGruppo">Gruppo</label>
                        <select id="filterGruppo" class="form-select bg-dark text-white border-secondary">
                            <option value="">Tutti</option>
                            <option value="__none__">Senza gruppo</option>
                            <?php foreach ($gruppi as $idGruppo => $labelGruppo): ?>
                                <option value="<?= htmlspecialchars((string) $idGruppo, ENT_QUOTES) ?>"><?= htmlspecialchars($labelGruppo, ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-xl-3">
                        <label class="form-label" for="filterEtichette">Etichette</label>
                        <select id="filterEtichette" class="form-select bg-dark text-white border-secondary">
                            <option value="">Tutte</option>
                            <option value="__none__">Senza etichette</option>
                            <?php foreach (array_keys($etichetteLista) as $etichetta): ?>
                                <option value="<?= htmlspecialchars($etichetta, ENT_QUOTES) ?>"><?= htmlspecialchars($etichetta, ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 d-flex flex-wrap justify-content-end gap-2">
                        <button type="button" id="clearFilters" class="btn btn-outline-secondary">Pulisci filtri</button>
                        <button type="submit" id="filterButton" class="btn btn-outline-light">Cerca</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" id="bulkUpdateButton" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal" disabled>
            Aggiorna record selezionati
        </button>
        <div id="visibleCount" class="text-white align-self-center"></div>
    </div>

    <div class="table-responsive">
        <table class="table table-dark table-striped align-middle" id="movimentiTable" data-gruppi="<?= htmlspecialchars(json_encode($gruppi, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT), ENT_QUOTES) ?>">
            <thead>
                <tr>
                    <th class="text-center"><input type="checkbox" id="selectAllRows" class="form-check-input"></th>
                    <th class="sortable" data-sort="banca">Banca</th>
                    <th class="sortable" data-sort="data">Data</th>
                    <th class="sortable" data-sort="importo">Importo</th>
                    <th class="sortable" data-sort="descrizione">Descrizione</th>
                    <th class="sortable" data-sort="descrizione-extra">Descrizione extra</th>
                    <th class="sortable" data-sort="gruppo">Gruppo</th>
                    <th class="sortable" data-sort="etichette">Etichette</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movimenti as $mov): ?>
                    <?php
                    $dataFormattata = date('d/m/Y H:i', strtotime($mov['data_operazione']));
                    $dataIso = date('Y-m-d', strtotime($mov['data_operazione']));
                    $dataTimestamp = strtotime($mov['data_operazione']);
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
                    $icon = $mov['source'] === 'revolut' ? 'assets/revolut.jpeg' : 'assets/credit.jpeg';
                    $bancaLabel = $mov['banca'] ?? '';
                    $gruppoId = !empty($mov['id_gruppo_transazione']) ? (int) $mov['id_gruppo_transazione'] : '';
                    $linkDettaglio = 'dettaglio.php?id=' . (int) $mov['id'] . '&src=' . urlencode($mov['tabella']);
                    ?>
                    <tr
                        data-id="<?= (int) $mov['id'] ?>"
                        data-tabella="<?= htmlspecialchars($mov['tabella'] ?? '', ENT_QUOTES) ?>"
                        data-banca="<?= htmlspecialchars($mov['banca'] ?? '', ENT_QUOTES) ?>"
                        data-data="<?= htmlspecialchars($dataFormattata, ENT_QUOTES) ?>"
                        data-date-iso="<?= htmlspecialchars($dataIso, ENT_QUOTES) ?>"
                        data-date-ts="<?= htmlspecialchars((string) $dataTimestamp, ENT_QUOTES) ?>"
                        data-importo="<?= htmlspecialchars($mov['amount'] ?? '', ENT_QUOTES) ?>"
                        data-descrizione="<?= htmlspecialchars($mov['descrizione'] ?? '', ENT_QUOTES) ?>"
                        data-descrizione-extra="<?= htmlspecialchars($mov['descrizione_extra'] ?? '', ENT_QUOTES) ?>"
                        data-gruppo="<?= htmlspecialchars($gruppoDescrizione, ENT_QUOTES) ?>"
                        data-gruppo-id="<?= htmlspecialchars((string) $gruppoId, ENT_QUOTES) ?>"
                        data-etichette="<?= htmlspecialchars($etichetteText, ENT_QUOTES) ?>"
                        data-note="<?= htmlspecialchars($mov['note'] ?? '', ENT_QUOTES) ?>"
                    >
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input row-select" aria-label="Seleziona movimento">
                        </td>
                        <td>
                            <img src="<?= htmlspecialchars($icon, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($bancaLabel, ENT_QUOTES) ?>" width="24" height="24" class="rounded-circle" title="<?= htmlspecialchars($bancaLabel, ENT_QUOTES) ?>">
                        </td>
                        <td><?= htmlspecialchars($dataFormattata, ENT_QUOTES) ?></td>
                        <td class="fw-semibold <?= $mov['amount'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= $mov['amount'] >= 0 ? '+' : '' ?><?= $importo ?> €
                        </td>
                        <td class="text-truncate cell-descrizione" style="max-width: 180px;" title="<?= htmlspecialchars($mov['descrizione'] ?? '', ENT_QUOTES) ?>">
                            <?= htmlspecialchars($mov['descrizione'] ?? '', ENT_QUOTES) ?>
                        </td>
                        <td class="cell-descrizione-extra"><?= htmlspecialchars($mov['descrizione_extra'] ?? '', ENT_QUOTES) ?></td>
                        <td class="cell-gruppo"><span class="badge <?= $badgeClasse ?>"><?= $badgeTesto ?></span></td>
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
                        <td>
                            <a href="<?= htmlspecialchars($linkDettaglio, ENT_QUOTES) ?>" class="text-white" title="Apri dettaglio">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div id="noResults" class="text-center text-white mt-3 d-none">Nessun movimento trovato.</div>
</div>

<div class="modal fade" id="bulkUpdateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white">
            <form id="bulkUpdateForm">
                <div class="modal-header">
                    <h5 class="modal-title">Aggiornamento massivo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Seleziona i campi da aggiornare sui record selezionati.</p>
                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="bulkUpdateGruppoToggle">
                            <label class="form-check-label" for="bulkUpdateGruppoToggle">Modifica gruppo</label>
                        </div>
                        <select id="bulkUpdateGruppo" class="form-select bg-dark text-white border-secondary" disabled>
                            <option value="">Senza gruppo</option>
                            <?php foreach ($gruppi as $idGruppo => $labelGruppo): ?>
                                <option value="<?= htmlspecialchars((string) $idGruppo, ENT_QUOTES) ?>"><?= htmlspecialchars($labelGruppo, ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="bulkUpdateDescrizioneExtraToggle">
                            <label class="form-check-label" for="bulkUpdateDescrizioneExtraToggle">Modifica descrizione extra</label>
                        </div>
                        <input type="text" id="bulkUpdateDescrizioneExtra" class="form-control bg-dark text-white border-secondary" disabled>
                    </div>
                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="bulkUpdateNoteToggle">
                            <label class="form-check-label" for="bulkUpdateNoteToggle">Modifica note</label>
                        </div>
                        <textarea id="bulkUpdateNote" class="form-control bg-dark text-white border-secondary" rows="3" disabled></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Applica aggiornamento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    #movimentiTable .sortable {
        cursor: pointer;
    }

    #movimentiTable .sortable::after {
        content: '⇅';
        font-size: 0.75rem;
        margin-left: 0.35rem;
        opacity: 0.6;
    }

    #movimentiTable .sortable.sorted-asc::after {
        content: '↑';
        opacity: 1;
    }

    #movimentiTable .sortable.sorted-desc::after {
        content: '↓';
        opacity: 1;
    }
</style>

<script src="js/tutti_movimenti_gestione.js"></script>
<?php include 'includes/footer.php'; ?>
