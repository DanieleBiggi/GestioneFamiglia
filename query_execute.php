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
        echo '<p class="text-muted">Nessun riepilogo disponibile per questa query.</p>';
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

    echo '<div class="d-flex align-items-center gap-2 mb-3">'
        . '<span class="fw-semibold">Visualizzazione:</span>'
        . '<button type="button" id="btn_view_list" class="btn btn-outline-primary btn-sm active">Elenco</button>'
        . '<button type="button" id="btn_view_table" class="btn btn-outline-primary btn-sm">Tabella</button>'
        . '</div>';

    echo '<div id="view_list">';
    foreach ($risultati as $ris) {
        foreach ($ris as $chiave => $valore) {
            if (!in_array($chiave, $campiEsclusi, true)) {
                echo htmlspecialchars($chiave) . ': ' . htmlspecialchars((string)$valore) . '<br>';
            }
        }
        echo '<hr>';
    }
    echo '</div>';

    echo '<div id="view_table" class="table-responsive d-none">';
    echo '<table class="table table-sm table-bordered"><thead><tr>';
    foreach (array_keys($intestazioni) as $intestazione) {
        echo '<th class="text-uppercase">' . htmlspecialchars($intestazione) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($risultati as $ris) {
        echo '<tr>';
        foreach (array_keys($intestazioni) as $intestazione) {
            $valoreCella = $ris[$intestazione] ?? '';
            echo '<td>' . htmlspecialchars((string)$valoreCella) . '</td>';
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
            listBtn.classList.remove('active');
            tableBtn.classList.add('active');
        } else {
            viewList.classList.remove('d-none');
            viewTable.classList.add('d-none');
            listBtn.classList.add('active');
            tableBtn.classList.remove('active');
        }
    }

    if (listBtn && tableBtn) {
        listBtn.addEventListener('click', function () { aggiornaVisualizzazione(false); });
        tableBtn.addEventListener('click', function () { aggiornaVisualizzazione(true); });
    }
});
</script>
</div>
</div>
