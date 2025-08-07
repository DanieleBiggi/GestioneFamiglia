<?php
function render_movimento_etichetta(array $mov) {
    global $conn;

    $importo = number_format($mov['amount'], 2, ',', '.');
    $dataOra  = date('d/m/Y H:i', strtotime($mov['data_operazione']));

    // Determine icon based on source
    $icon = $mov['source'] === 'revolut' ? 'assets/revolut.jpeg' : 'assets/credit.jpeg';

    $url = 'dettaglio.php?id=' . (int)$mov['id'] . '&src=' . urlencode($mov['tabella']);

    echo '<div class="movement d-flex justify-content-between align-items-start text-white text-decoration-none" style="cursor:pointer" onclick="window.location.href=\'' . $url . '\'">';
    echo '  <img src="' . htmlspecialchars($icon) . '" alt="src" class="me-2" style="width:24px;height:24px">';
    echo '  <div class="flex-grow-1 me-3">';
    echo '    <div class="descr fw-semibold">' . htmlspecialchars($mov['descrizione']) . '</div>';
    echo '    <div class="small">' . $dataOra . '</div>';

    // Quote per utente se presenti
    $stmtU = $conn->prepare(
        "SELECT e2o.id_e2o, e2o.importo AS importo_e2o, u.id as id_utente, u.nome, u.cognome,
                u2o.importo_utente, u2o.utente_pagante, u2o.saldata
           FROM bilancio_etichette2operazioni e2o
           JOIN bilancio_utenti2operazioni_etichettate u2o ON u2o.id_e2o = e2o.id_e2o
           JOIN utenti u ON u.id = u2o.id_utente
          WHERE e2o.id_tabella = ? AND e2o.tabella_operazione = ?"
    );
    $stmtU->bind_param('is', $mov['id'], $mov['tabella']);
    if ($stmtU->execute()) {
        $resU = $stmtU->get_result();
        $groups = [];
        while ($row = $resU->fetch_assoc()) {
            $idE2o = $row['id_e2o'];
            if (!isset($groups[$idE2o])) {
                $groups[$idE2o] = [
                    'total' => $row['importo_e2o'] !== null ? (float)$row['importo_e2o'] : abs($mov['amount']),
                    'rows'  => []
                ];
            }
            $groups[$idE2o]['rows'][] = $row;
        }

        if ($groups) {
            $perUser = [];
            foreach ($groups as $g) {
                $rows  = $g['rows'];
                $total = $g['total'];
                $count = count($rows);
                foreach ($rows as $r) {
                    $imp = $r['importo_utente'];
                    if ($imp === null) {
                        $imp = $total / $count;
                    }
                    $uid = $r['id_utente'];
                    if (!isset($perUser[$uid])) {
                        $perUser[$uid] = [
                            'nome'    => $r['nome'],
                            'cognome' => $r['cognome'],
                            'importo' => 0,
                            'pagante' => false,
                            'saldata' => true
                        ];
                    }
                    $perUser[$uid]['importo'] += $imp;
                    if ($r['utente_pagante']) {
                        $perUser[$uid]['pagante'] = true;
                    }
                    if (!$r['saldata']) {
                        $perUser[$uid]['saldata'] = false;
                    }
                }
            }

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
        }
    }
    $stmtU->close();

    echo '  </div>';
    echo '  <div class="text-end">';
    echo '    <div class="amount text-white">' . ($mov['amount'] >= 0 ? '+' : '') . $importo . ' €</div>';
    echo '  </div>';
    echo '</div>';

}
