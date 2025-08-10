<?php
function render_budget(array $row): void {
    $id = (int)($row['id'] ?? ($row['id_budget'] ?? 0));
    $descrizione = trim($row['descrizione'] ?? '');
    $tipologia = trim($row['tipologia'] ?? '');
    $salvadanaio = trim($row['nome_salvadanaio'] ?? '');
    $tipologiaSpesa = trim($row['tipologia_spesa'] ?? '');
    $importoNum = isset($row['importo']) ? (float)$row['importo'] : 0;
    $importoFmt = number_format($importoNum, 2, ',', '.');
    $dataInizioRaw = $row['data_inizio'] ?? '';
    $dataFineRaw = $row['data_fine'] ?? ($row['data_scadenza'] ?? '');
    $dataInizio = $dataInizioRaw ? date('d/m/Y', strtotime($dataInizioRaw)) : '';
    $dataFine = $dataFineRaw ? date('d/m/Y', strtotime($dataFineRaw)) : '';
    $search = strtolower(trim($descrizione . ' ' . $tipologia . ' ' . $salvadanaio . ' ' . $tipologiaSpesa . ' ' . $dataInizio . ' ' . $dataFine . ' ' . $importoFmt));
    $searchAttr = htmlspecialchars($search, ENT_QUOTES);
    $icon = '';
    $tipologiaLower = strtolower($tipologia);
    if ($tipologiaLower === 'entrata') {
        $icon = 'bi-arrow-down-circle text-success';
    } elseif ($tipologiaLower === 'uscita') {
        $icon = 'bi-arrow-up-circle text-danger';
    }
    echo '<div class="list-group-item movement text-white budget-item d-flex flex-column flex-sm-row justify-content-between align-items-start"'
        . ' data-id="' . $id . '"'
        . ' data-search="' . $searchAttr . '"'
        . ' data-descrizione="' . htmlspecialchars($descrizione, ENT_QUOTES) . '"'
        . ' data-tipologia="' . htmlspecialchars($tipologia, ENT_QUOTES) . '"'
        . ' data-salvadanaio="' . htmlspecialchars($salvadanaio, ENT_QUOTES) . '"'
        . ' data-tipologia-spesa="' . htmlspecialchars($tipologiaSpesa, ENT_QUOTES) . '"'
        . ' data-inizio="' . htmlspecialchars($dataInizioRaw, ENT_QUOTES) . '"'
        . ' data-fine="' . htmlspecialchars($dataFineRaw, ENT_QUOTES) . '"'
        . ' data-importo="' . htmlspecialchars((string)$importoNum, ENT_QUOTES) . '">';
    echo '  <div class="flex-grow-1 me-sm-3">';
    echo '    <div class="fw-semibold">' . htmlspecialchars($descrizione) . '</div>';
    if ($dataInizio || $dataFine) {
        $dates = $dataInizio;
        if ($dataFine) {
            $dates .= ' - ' . $dataFine;
        }
        echo '    <div class="small">' . htmlspecialchars($dates) . '</div>';
    }
     echo '    <div>' . $importoFmt . ' &euro;</div>';
    echo '  </div>';
    echo '  <div class="text-sm-end mt-2 mt-sm-0 d-flex flex-column align-items-end">';
    if ($icon) {
        echo '    <i class="bi ' . $icon . ' mb-1"></i>';
    }
   
    if ($salvadanaio !== '') {
        echo '    <div class="mt-1"><span class="badge-etichetta" style=" min-width: 150px;text-align:center">' . htmlspecialchars($salvadanaio) . '</span></div>';
    }
    echo '  </div>';
    echo '</div>';
}
?>
