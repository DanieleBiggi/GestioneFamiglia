<?php
require_once __DIR__ . '/etichette_utils.php';

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
        "SELECT
	e2o.id_e2o,
    v.descrizione,
    u.id AS id_utente,
    u.nome,
    u.cognome,
    u2o.id_u2o,
 (
     CASE 
     	WHEN v.id_utente_operazione = u2o.id_utente 
     	THEN 
     	-(
        0
        ) ELSE(
        CASE WHEN IFNULL(u2o.importo_utente, 0) <> 0 THEN u2o.importo_utente ELSE(v.importo * u2o.quote)
        END
        )
END
) AS importo_utente,
    u2o.quote,
    u2o.saldata,
    u2o.data_saldo,
    v.id_utente_operazione,
    v.importo_totale_operazione,
    v.importo_etichetta    
FROM
    bilancio_utenti2operazioni_etichettate u2o
JOIN v_bilancio_etichette2operazioni_a_testa v ON
    u2o.id_e2o = v.id_e2o
JOIN bilancio_etichette2operazioni e2o ON
    e2o.id_e2o = u2o.id_e2o
JOIN utenti u ON
    u.id = u2o.id_utente    
WHERE
    u2o.id_e2o = ?
ORDER BY
    v.data_operazione
DESC
    "
    );
    $stmtU->bind_param('i', $mov['id_e2o']);
    $perUser = [];
    if ($stmtU->execute()) {
        $resU = $stmtU->get_result();
        $rows = [];
        while ($r = $resU->fetch_assoc()) {
            $rows[] = $r;
        }
        $count = count($rows) ?: 1;
        foreach ($rows as $row) {
            $quote = $row['quote'] !== null ? (float)$row['quote'] : (1 / $count);
            $importoTot = (float)($row['importo_totale_operazione'] ?? 0);
            $importoEtic = (float)($row['importo_etichetta'] ?? 0);
            $importoUtente = (float)($row['importo_utente'] ?? 0);
            $isPagante = ((int)$row['id_utente_operazione'] === (int)$row['id_utente']);
            $imp = calcola_importo_quota($isPagante, $importoUtente, $importoEtic, $importoTot, $quote);
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
            $perUser[$uid]['importo'] += $importoUtente;
            /*
            if ($isPagante) {
                $perUser[$uid]['pagante'] = true;
            }
            */
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
