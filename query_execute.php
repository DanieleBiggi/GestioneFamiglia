<?php include 'includes/session_check.php'; ?>
<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'includes/db.php';
require_once 'includes/utility.php';
include 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['error' => 'ID mancante']);
    exit;
}

$stmt = $conn->prepare('SELECT stringa_da_completare, parametri FROM dati_remoti WHERE id_dato_remoto = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['error' => 'Record non trovato']);
    exit;
}

$SQLinv = $row['stringa_da_completare'] ?? '';
$parametri = json_decode($row['parametri'] ?? '', true);
if (is_array($parametri)) {
    foreach ($parametri as $chiave => $valore) {
        $SQLinv = str_replace('[[' . $chiave . ']]', $valore, $SQLinv);
    }
}

$utility = new Utility();

$ret = $utility->getDati($SQLinv);
$risultati = is_array($ret) ? $ret : [];

$mappaSintesi = [
    'C99' => 'IMPORTO',
    'C06' => 'IMPORTO',
    '019' => 'IMPORTO',
    'Z50' => 'QUANTITA',
    'Z51' => 'QUANTITA',
];

echo '<div class="mb-3">';
$mostraDettagli = false;
if (empty($risultati)) {
    echo '<p class="text-warning">Nessun risultato restituito dalla query.</p>';
} else {
    $haSintesi = false;
    foreach ($risultati as $ris) {
        $codice = $ris['CODVOCE'] ?? null;
        $colonna = $codice && array_key_exists($codice, $mappaSintesi) ? $mappaSintesi[$codice] : null;
        if ($colonna && isset($ris['DESCRIZ']) && isset($ris[$colonna])) {
            $haSintesi = true;
            echo "<div class='d-flex mb-2'>" .
                "<div class='fw-semibold w-50'>" . htmlspecialchars($ris['DESCRIZ']) . "</div><div class='text-end ps-2 w-50'>" .
                htmlspecialchars($ris[$colonna]) . "</div>" .
                "</div>";
        }
    }

    if (!$haSintesi) {
        // Per query tabellari non mostrare un avviso fuorviante.
        if (!empty($risultati)) {
            $mostraDettagli = true;
        }
    }
}
echo '</div>';
?>
<button id="toggle_details" type="button" class="btn btn-secondary mb-3 <?php echo !empty($mostraDettagli) ? 'd-none' : ''; ?>">
Mostra dettagli</button>
<div id="div_details" style="display:<?php echo !empty($mostraDettagli) ? 'block' : 'none'; ?>">
<?php
if (empty($risultati)) {
    echo '<p class="text-muted">Nessun dettaglio da mostrare.</p>';
} else {
    $campiEsclusi = ($id === 10) ? ['ANNO', 'MESE'] : [];
    $intestazioni = [];

    foreach ($risultati as $ris) {
        foreach ($ris as $chiave => $valore) {
            if (!in_array($chiave, $campiEsclusi, true)) {
                $intestazioni[$chiave] = true;
            }
        }
    }

    echo '<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">'
        . '<span class="text-secondary small">' . count($risultati) . ' risultati</span>'
        . '<span class="fw-semibold">Visualizzazione:</span>'
        . '<button type="button" id="btn_view_list" class="btn btn-outline-primary btn-sm active">Elenco</button>'
        . '<button type="button" id="btn_view_table" class="btn btn-outline-primary btn-sm">Tabella</button>'
        . '</div>';

    echo '<div id="view_list">';
    foreach ($risultati as $ris) {
        foreach ($ris as $chiave => $valore) {
            if (!in_array($chiave, $campiEsclusi, true)) {
                echo '<div class="d-flex flex-wrap justify-content-between gap-2 py-1 border-bottom"><span class="text-secondary">' . htmlspecialchars($chiave) . '</span><span class="text-break text-end">' . htmlspecialchars((string)$valore) . '</span></div>';
            }
        }
        echo '<hr class="my-3">';
    }
    echo '</div>';

    echo '<div id="table_controls" class="d-flex gap-2 mb-2 flex-wrap d-none">'
        . '<input id="result_filter" type="search" class="form-control form-control-sm" style="max-width:320px" placeholder="Filtra i risultati" aria-label="Filtra i risultati">'
        . '<span id="result_count" class="small text-secondary align-self-center"></span>'
        . '</div>';
    echo '<div id="view_table" class="table-responsive d-none" style="max-height:70vh">';
    echo '<table id="result_table" class="table table-sm table-bordered table-striped table-hover align-middle"><thead class="sticky-top"><tr>';
    foreach (array_keys($intestazioni) as $intestazione) {
        echo '<th scope="col" class="text-uppercase text-nowrap" style="cursor:pointer;white-space:nowrap" tabindex="0" aria-sort="none" title="Ordina per questa colonna">' . htmlspecialchars($intestazione) . ' <span class="sort-indicator" aria-hidden="true">↕</span></th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($risultati as $ris) {
        echo '<tr>';
        foreach (array_keys($intestazioni) as $intestazione) {
            $valoreCella = $ris[$intestazione] ?? '';
            $isImporto = preg_match('/(IMPORTO|NETTO|LORDO|BONUS|PREMIO|RETRIB|IMPONIBILE|RITENUT|COMPETENZ|IRPEF|TFR|DETRAZION)/i', $intestazione);
            $isNumero = is_numeric($valoreCella) && $valoreCella !== '';
            $visualizzato = ($isImporto && $isNumero) ? number_format((float)$valoreCella, 2, ',', '.') . ' €' : (string)$valoreCella;
            echo '<td data-sort="' . htmlspecialchars((string)$valoreCella, ENT_QUOTES) . '" class="' . ($isNumero ? 'text-end text-nowrap' : '') . '">' . htmlspecialchars($visualizzato) . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}
?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('toggle_details');
    const details = document.getElementById('div_details');
    const listBtn = document.getElementById('btn_view_list');
    const tableBtn = document.getElementById('btn_view_table');
    const viewList = document.getElementById('view_list');
    const viewTable = document.getElementById('view_table');
    const tableControls = document.getElementById('table_controls');
    const resultFilter = document.getElementById('result_filter');
    const resultCount = document.getElementById('result_count');
    const resultTable = document.getElementById('result_table');

    if (btn) {
        btn.textContent = (details.style.display === 'block') ? 'Nascondi dettagli' : 'Mostra dettagli';
        btn.addEventListener('click', function () {
            if (details.style.display === 'none' || details.style.display === '') {
                details.style.display = 'block';
                btn.textContent = 'Nascondi dettagli';
            } else {
                details.style.display = 'none';
                btn.textContent = 'Mostra dettagli';
            }
        });
    }

    function aggiornaVisualizzazione(mostraTabella) {
        if (!viewList || !viewTable || !listBtn || !tableBtn) {
            return;
        }

        if (mostraTabella) {
            viewList.classList.add('d-none');
            viewTable.classList.remove('d-none');
            tableControls?.classList.remove('d-none');
            listBtn.classList.remove('active');
            tableBtn.classList.add('active');
        } else {
            viewList.classList.remove('d-none');
            viewTable.classList.add('d-none');
            tableControls?.classList.add('d-none');
            listBtn.classList.add('active');
            tableBtn.classList.remove('active');
        }
    }

    if (resultTable && resultFilter) {
        const tbody = resultTable.tBodies[0];
        const originalRows = Array.from(tbody.rows);
        const headers = Array.from(resultTable.tHead.rows[0].cells);
        const refreshFilter = () => {
            const q = resultFilter.value.trim().toLocaleLowerCase('it');
            let shown = 0;
            originalRows.forEach(row => {
                const match = row.textContent.toLocaleLowerCase('it').includes(q);
                row.hidden = !match;
                row.style.display = match ? '' : 'none';
                if (match) shown++;
            });
            resultCount.textContent = shown + ' di ' + originalRows.length + ' righe';
        };
        let sortedColumn = -1;
        let ascending = true;
        const sortRows = index => {
            ascending = sortedColumn === index ? !ascending : true;
            sortedColumn = index;
            const getValue = row => row.cells[index]?.dataset.sort?.trim() ?? '';
            const numeric = originalRows.every(row => {
                const v = getValue(row);
                return v === '' || /^-?\d+(?:\.\d+)?$/.test(v);
            });
            const ordered = [...originalRows].sort((a,b) => {
                const av = getValue(a), bv = getValue(b);
                const comparison = numeric
                    ? ((av === '' ? -Infinity : Number(av)) - (bv === '' ? -Infinity : Number(bv)))
                    : av.localeCompare(bv, 'it', {numeric:true, sensitivity:'base'});
                return ascending ? comparison : -comparison;
            });
            ordered.forEach(row => tbody.appendChild(row));
            headers.forEach((h,i) => {
                h.setAttribute('aria-sort', i === index ? (ascending ? 'ascending' : 'descending') : 'none');
                h.querySelector('.sort-indicator').textContent = i === index ? (ascending ? '↑' : '↓') : '↕';
            });
            refreshFilter();
        };
        headers.forEach((header,index) => {
            header.addEventListener('click', () => sortRows(index));
            header.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); sortRows(index); }
            });
        });
        resultFilter.addEventListener('input', refreshFilter);
        refreshFilter();
    }

    if (listBtn && tableBtn) {
        listBtn.addEventListener('click', function () { aggiornaVisualizzazione(false); });
        tableBtn.addEventListener('click', function () { aggiornaVisualizzazione(true); });
    }
});
</script>
</div>
</div>
