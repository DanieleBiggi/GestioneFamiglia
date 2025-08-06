<?php
function render_movimento(array $mov) {
    global $conn;

    $importo = number_format($mov['amount'], 2, ',', '.');

    $dataOra  = date('d/m/Y H:i', strtotime($mov['data_operazione']));

    // Determine icon based on source
    $icon = $mov['source'] === 'revolut' ? 'assets/revolut.jpeg' : 'assets/credit.jpeg';

    $url = 'dettaglio.php?id=' . (int)$mov['id'] . '&src=' . urlencode($mov['source']);

    echo '<div class="movement d-flex justify-content-between align-items-start text-white text-decoration-none" style="cursor:pointer" onclick="window.location.href=\'' . $url . '\'">';
    echo '  <img src="' . htmlspecialchars($icon) . '" alt="src" class="me-2" style="width:24px;height:24px">';

    echo '  <div class="flex-grow-1 me-3">';
    echo '    <div class="descr fw-semibold">' . htmlspecialchars($mov['descrizione']) . '</div>';
    echo '    <div class="small">' . $dataOra . '</div>';

    // Quote per utente se presenti
    $stmtU = $conn->prepare("SELECT u.nome, u.cognome, ue.importo_utente, ue.utente_pagante, ue.saldata
                              FROM bilancio_utenti2operazioni_etichettate ue
                              JOIN utenti u ON u.id_utente = ue.id_utente
                             WHERE ue.id_tabella = ? AND ue.tabella_operazione = ?");
    $stmtU->bind_param('is', $mov['id'], $mov['tabella']);
    if ($stmtU->execute()) {
        $resU = $stmtU->get_result();
        $users = $resU->fetch_all(MYSQLI_ASSOC);
        if ($users) {
            $count = count($users);
            $total = abs($mov['amount']);
            foreach ($users as &$u) {
                if ($u['importo_utente'] === null) {
                    $u['importo_utente'] = $total / $count;
                }
            }
            echo '    <div class="mt-1">';
            foreach ($users as $u) {
                $name = htmlspecialchars(trim(($u['nome'] ?? '') . ' ' . ($u['cognome'] ?? '')));
                $amt  = number_format($u['importo_utente'], 2, ',', '.');
                $class = $u['utente_pagante'] ? 'badge bg-success' : 'badge bg-secondary';
                $status = $u['saldata'] ? '✔' : '✖';
                echo "<span class='" . $class . " me-1'>$name $amt € $status</span>";
            }
            echo '    </div>';
        }
    }
    $stmtU->close();

    echo '  </div>';
    echo '  <div class="text-end">';
    echo '    <div class="amount text-white">' . ($mov['amount'] >= 0 ? '+' : '') . $importo . ' €</div>';
    if (!empty($mov['etichette'])) {
        echo '    <div class="mt-1">';
        foreach (explode(',', $mov['etichette']) as $tag) {
            $tag = trim($tag);

            echo '      <a href="etichetta.php?etichetta=' . urlencode($tag) . '" class="badge-etichetta me-1" onclick="event.stopPropagation();">' . htmlspecialchars($tag) . '</a>';

        }
        echo '    </div>';
    }
    echo '  </div>';

    echo '</div>';

}
