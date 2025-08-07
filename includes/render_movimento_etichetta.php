<?php
function render_movimento_etichetta(array $mov, int $id_etichetta) {
    global $conn;

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

    $descrizione = $info['descrizione_extra'] ?? $mov['descrizione'];
    $amountValue = $info['importo'] !== null ? (float)$info['importo'] : $mov['amount'];
    if ($mov['tabella'] === 'bilancio_uscite' && $amountValue >= 0) {
        $amountValue *= -1;
    }

    $importo = number_format($amountValue, 2, ',', '.');
    $dataOra  = date('d/m/Y H:i', strtotime($mov['data_operazione']));

    // Determine icon based on source
    $icon = $mov['source'] === 'revolut' ? 'assets/revolut.jpeg' : 'assets/credit.jpeg';

    $url = 'dettaglio.php?id=' . (int)$mov['id'] . '&src=' . urlencode($mov['tabella']);

    $rowId = 'mov-' . $mov['tabella'] . '-' . $mov['id'];
    echo '<div id="' . $rowId . '" class="movement d-flex justify-content-between align-items-start text-white text-decoration-none" style="cursor:pointer" onclick="window.location.href=\'' . $url . '\'">';
    echo '  <img src="' . htmlspecialchars($icon) . '" alt="src" class="me-2" style="width:24px;height:24px">';
    echo '  <div class="flex-grow-1 me-3">';
    echo '    <div class="descr fw-semibold">' . htmlspecialchars($descrizione) . '</div>';
    echo '    <div class="small">' . $dataOra . '</div>';

    // Quote per utente se presenti
    $stmtU = $conn->prepare(
        "SELECT e2o.id_e2o, e2o.importo AS importo_e2o, e.descrizione AS etichetta_descrizione,
                u.id AS id_utente, u.nome, u.cognome,
                u2o.id_u2o, u2o.importo_utente, u2o.utente_pagante, u2o.saldata, u2o.data_saldo
           FROM bilancio_etichette2operazioni e2o
           JOIN bilancio_etichette e ON e.id_etichetta = e2o.id_etichetta
           JOIN bilancio_utenti2operazioni_etichettate u2o ON u2o.id_e2o = e2o.id_e2o
           JOIN utenti u ON u.id = u2o.id_utente
          WHERE e2o.id_tabella = ? AND e2o.tabella_operazione = ? AND e2o.id_etichetta = ?"
    );
    $stmtU->bind_param('isi', $mov['id'], $mov['tabella'], $id_etichetta);
    if ($stmtU->execute()) {
        $resU = $stmtU->get_result();
        $groups = [];
        while ($row = $resU->fetch_assoc()) {
            $idE2o = $row['id_e2o'];
            if (!isset($groups[$idE2o])) {
                $groups[$idE2o] = [
                    'total' => $row['importo_e2o'] !== null ? (float)$row['importo_e2o'] : abs($mov['amount']),
                    'rows'  => [],
                    'descrizione' => $row['etichetta_descrizione']
                ];
            }
            if ($row['id_utente']) {
                $groups[$idE2o]['rows'][] = $row;
            }
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

            // Pulsante per modificare le quote del gruppo corrispondente all'etichetta corrente
            if (!empty($mov['id_e2o']) && isset($groups[$mov['id_e2o']])) {
                $rowsJson = htmlspecialchars(json_encode($groups[$mov['id_e2o']]['rows'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP));
                echo "    <div class='mt-1'>";
                echo "      <button class='btn btn-sm btn-outline-light' data-id-e2o='{$mov['id_e2o']}' data-rows='{$rowsJson}' onclick='openU2oModal(this);event.stopPropagation();'>Gestisci quote</button>";
                echo "    </div>";
            }
        }
    }
    $stmtU->close();

    echo '  </div>';
    echo '  <div class="text-end">';
    echo '    <div class="amount text-white">' . ($amountValue >= 0 ? '+' : '') . $importo . ' €</div>';
    $idE2oAttr = htmlspecialchars($info['id_e2o'] ?? '', ENT_QUOTES);
    $descAttr = htmlspecialchars($info['descrizione_extra'] ?? '', ENT_QUOTES);
    $impAttr = htmlspecialchars($info['importo'] ?? '', ENT_QUOTES);
    $allAttr = htmlspecialchars($info['allegato'] ?? '', ENT_QUOTES);
    $rowAttr = htmlspecialchars($rowId, ENT_QUOTES);
    echo '    <button class="btn btn-sm btn-link text-white edit-e2o" data-id-e2o="' . $idE2oAttr . '" data-descrizione-extra="' . $descAttr . '" data-importo="' . $impAttr . '" data-allegato="' . $allAttr . '" data-row-id="' . $rowAttr . '" onclick="event.stopPropagation();"><i class="bi bi-pencil"></i></button>';
    echo '  </div>';
    echo '</div>';

}
