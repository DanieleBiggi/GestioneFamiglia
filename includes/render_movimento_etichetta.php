<?php
function render_movimento_etichetta(array $mov, int $id_etichetta) {
    global $conn, $isAdmin;

    // Dati specifici dell'etichetta
    $stmtInfo = $conn->prepare(
        "SELECT id_e2o, descrizione_extra, importo, allegato
           FROM bilancio_etichette2operazioni
          WHERE id_tabella = ? AND tabella_operazione = ? AND id_etichetta = ?"
    );
    $stmtInfo->bind_param('isi', $mov['id'], $mov['tabella'], $id_etichetta);
    $stmtInfo->execute();
    $info = $stmtInfo->get_result()->fetch_assoc() ?: [];
    $stmtInfo->close();

    $descrizione = $info['descrizione_extra'] ?? '';
    if ($descrizione === '') {
        $descrizione = $mov['descrizione'];
    }
    $amountValue = $info['importo'] !== null ? (float)$info['importo'] : $mov['amount'];
    if ($mov['tabella'] === 'bilancio_uscite' && $amountValue >= 0) {
        $amountValue *= -1;
    }

    $importo = number_format($amountValue, 2, ',', '.');
    $dataOra  = date('d/m/Y H:i', strtotime($mov['data_operazione']));

    // Determine icon based on source
    $icon = $mov['source'] === 'revolut' ? 'assets/revolut.jpeg' : 'assets/credit.jpeg';

    // Quote per utente e dati per la modal
    $stmtU = $conn->prepare(
        "SELECT e2o.id_e2o, e.descrizione AS etichetta_descrizione,
                u.id AS id_utente, u.nome, u.cognome,
                u2o.id_u2o, u2o.importo_utente, u2o.quote, u2o.utente_pagante, u2o.saldata, u2o.data_saldo,
                v.importo_totale_operazione, v.importo_etichetta
           FROM bilancio_etichette2operazioni e2o
            JOIN bilancio_etichette e ON e.id_etichetta = e2o.id_etichetta
            JOIN v_bilancio_etichette2operazioni_a_testa v ON v.id_e2o = e2o.id_e2o
            JOIN bilancio_utenti2operazioni_etichettate u2o ON u2o.id_e2o = e2o.id_e2o
            JOIN utenti u ON u.id = u2o.id_utente
          WHERE e2o.id_tabella = ? AND e2o.tabella_operazione = ? AND e2o.id_etichetta = ?"
    );
    $stmtU->bind_param('isi', $mov['id'], $mov['tabella'], $id_etichetta);
    $perUser = [];
    if ($stmtU->execute()) {
        $resU = $stmtU->get_result();
        while ($row = $resU->fetch_assoc()) {
            $importoTot = (float)($row['importo_totale_operazione'] ?? 0);
            $importoEtic = (float)($row['importo_etichetta'] ?? 0);
            $importoUtente = (float)($row['importo_utente'] ?? 0);
            $quote = (float)($row['quote'] ?? 0);
            $imp = 0.0;
            if ($row['utente_pagante']) {
                if ($importoUtente != 0.0) {
                    $imp = -$importoUtente;
                } elseif ($importoEtic != 0.0) {
                    $imp = -($importoEtic * $quote);
                } else {
                    $imp = -($importoTot - ($importoTot * $quote));
                }
            } else {
                if ($importoUtente != 0.0) {
                    $imp = $importoUtente;
                } elseif ($importoEtic != 0.0) {
                    $imp = $importoEtic * $quote;
                } else {
                    $imp = $importoTot * $quote;
                }
            }
            $uid = $row['id_utente'];
            if (!isset($perUser[$uid])) {
                $perUser[$uid] = [
                    'nome'    => $row['nome'],
                    'cognome' => $row['cognome'],
                    'importo' => 0.0,
                    'pagante' => false,
                    'saldata' => true
                ];
            }
            $perUser[$uid]['importo'] += $imp;
            if ($row['utente_pagante']) {
                $perUser[$uid]['pagante'] = true;
            }
            if (!$row['saldata']) {
                $perUser[$uid]['saldata'] = false;
            }
        }
    }
    $stmtU->close();

    $rowId = 'mov-' . $mov['tabella'] . '-' . $mov['id'];
    $idE2o = $mov['id_e2o'] ?? ($info['id_e2o'] ?? 0);
    $dest = 'etichetta_dettaglio_movimento.php?id=' . (int)$idE2o;
    echo '<div id="' . $rowId . '" class="movement d-flex align-items-stretch text-white text-decoration-none" style="cursor:pointer" onclick="window.location.href=\'' . $dest . '\'">';
    if ($isAdmin) {
        echo '<input type="checkbox" class="form-check-input me-2 settle-checkbox" style="min-width:1rem;height:1rem;" onclick="event.stopPropagation();">';
    }
    
    echo '  <div class="flex-grow-1 me-3" style="min-width:0">';
    echo '    <div class="descr fw-semibold">' . htmlspecialchars($descrizione) . '</div>';
    echo '    <div class="small">' . $dataOra . '</div>';
    if ($perUser) {
        echo '    <div class="mt-1">';
        foreach ($perUser as $u) {
            $name = htmlspecialchars(trim(($u['nome'] ?? '') . ' ' . ($u['cognome'] ?? '')));
            $amt  = number_format($u['importo'], 2, ',', '.');
            $class = $u['pagante'] ? 'badge bg-success' : 'badge bg-secondary';
            $status = $u['saldata'] ? '✔' : '✖';
            echo "<span class='" . $class . " me-1'>$name $amt € $status</span>";
        }
        echo '    </div>';
    }
    echo '  </div>';
    echo '  <div class="text-end d-flex flex-column ms-3 align-items-end h-100">';
    echo '    <div class="amount text-white text-nowrap">' . ($amountValue >= 0 ? '+' : '') . $importo . ' €</div>';
    echo '    <img src="' . htmlspecialchars($icon) . '" alt="src" class="flex-shrink-0 mt-auto" style="width:24px;height:24px">';
    echo '  </div>';
    echo '</div>';

}
