<?php
/**
 * Utility functions per la tabella bilancio_etichette2operazioni.
 */

/**
 * Controlla che l'id fornito esista nella tabella specificata.
 *
 * @param string $tabella_operazione Valore di tabella_operazione (entrate, uscite, revolut, hype).
 * @param int    $id_tabella         Identificativo da verificare.
 *
 * @return bool  true se esiste, false altrimenti.
 */
function checkIdTabellaEsiste(string $tabella_operazione, int $id_tabella): bool
{
    global $conn;

    $map = [
        'entrate' => ['table' => 'bilancio_entrate',     'column' => 'id_entrata'],
        'uscite'  => ['table' => 'bilancio_uscite',      'column' => 'id_uscita'],
        'revolut' => ['table' => 'movimenti_revolut',    'column' => 'id_movimento_revolut'],
        'hype'    => ['table' => 'movimenti_hype',       'column' => 'id_movimento_hype'],
    ];

    if (!isset($map[$tabella_operazione])) {
        return false;
    }

    $info = $map[$tabella_operazione];
    $sql = sprintf('SELECT 1 FROM %s WHERE %s = ? LIMIT 1', $info['table'], $info['column']);
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $id_tabella);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $exists;
}

/**
 * Cancella tutte le etichette collegate a un record.
 *
 * Da chiamare dopo la cancellazione di un record da una delle tabelle
 * bilancio_entrate, bilancio_uscite, movimenti_revolut, movimenti_hype.
 *
 * @param string $tabella_operazione Valore di tabella_operazione associato al record cancellato.
 * @param int    $id_tabella         Identificativo del record cancellato.
 *
 * @return void
 */
function eliminaEtichetteCollegate(string $tabella_operazione, int $id_tabella): void
{
    global $conn;
    $stmt = $conn->prepare(
        'DELETE FROM bilancio_etichette2operazioni WHERE tabella_operazione = ? AND id_tabella = ?'
    );
    if ($stmt) {
        $stmt->bind_param('si', $tabella_operazione, $id_tabella);
        $stmt->execute();
        $stmt->close();
    }
}

function get_saldo_e_movimenti_utente($idUtente)
{ 
    global $conn;
    $loggedUserId = $_SESSION['utente_id'] ?? 0;
    $famigliaId   = $_SESSION['id_famiglia_gestione'] ?? 0;
    $isAdmin      = ($loggedUserId == 1);
    $ar = [];
    $ar['movimenti'] = [];
    $ar['saldoTot'] = 0;    
    $sqlMov = "SELECT
                    u2o.id_u2o,
                    u2o.id_e2o,
                    e2o.id_tabella,
                    e2o.tabella_operazione AS tabella,
                    e2o.id_etichetta,
                    CONCAT(IFNULL(v.descrizione_extra,v.descrizione_operazione), ' (', v.importo_totale_operazione, ')') AS descrizione,
                    v.data_operazione,
                    v.descrizione AS etichetta_descrizione,
                    (CASE
                        WHEN u2o.utente_pagante = 1 THEN -(
                            CASE
                                WHEN IFNULL(u2o.importo_utente, 0) <> 0 THEN u2o.importo_utente
                                WHEN v.importo_etichetta <> 0 THEN (v.importo_etichetta * u2o.quote)
                                ELSE (v.importo_totale_operazione - (v.importo_totale_operazione * u2o.quote))
                            END
                        )
                        ELSE (
                            CASE
                                WHEN IFNULL(u2o.importo_utente, 0) <> 0 THEN u2o.importo_utente
                                WHEN v.importo_etichetta <> 0 THEN (v.importo_etichetta * u2o.quote)
                                ELSE (v.importo_totale_operazione * u2o.quote)
                            END
                        )
                    END) AS saldo_utente
               FROM bilancio_utenti2operazioni_etichettate u2o
               JOIN v_bilancio_etichette2operazioni_a_testa v ON u2o.id_e2o = v.id_e2o
               JOIN bilancio_etichette2operazioni e2o ON e2o.id_e2o = u2o.id_e2o
               WHERE u2o.id_utente = ? AND u2o.saldata = 0
               ORDER BY v.data_operazione DESC";
    
    $stmtMov = $conn->prepare($sqlMov);
    $stmtMov->bind_param('i', $idUtente);
    $stmtMov->execute();
    $resMov = $stmtMov->get_result();
    $movimenti = [];
    $saldoTot = 0.0;
    while ($row = $resMov->fetch_assoc()) {
        $row['saldo_utente'] = (float)$row['saldo_utente'];
        if ($isAdmin) {
            $stmtDet = $conn->prepare("SELECT u2o.id_u2o, u.nome, u.cognome,
                    (CASE
                        WHEN u2o.utente_pagante = 1 THEN -(
                            CASE
                                WHEN IFNULL(u2o.importo_utente, 0) <> 0 THEN u2o.importo_utente
                                WHEN v.importo_etichetta <> 0 THEN (v.importo_etichetta * u2o.quote)
                                ELSE (v.importo_totale_operazione - (v.importo_totale_operazione * u2o.quote))
                            END
                        )
                        ELSE (
                            CASE
                                WHEN IFNULL(u2o.importo_utente, 0) <> 0 THEN u2o.importo_utente
                                WHEN v.importo_etichetta <> 0 THEN (v.importo_etichetta * u2o.quote)
                                ELSE (v.importo_totale_operazione * u2o.quote)
                            END
                        )
                    END) AS importo,
                    u2o.saldata,
                    u2o.data_saldo
               FROM bilancio_utenti2operazioni_etichettate u2o
               JOIN v_bilancio_etichette2operazioni_a_testa v ON u2o.id_e2o = v.id_e2o
               JOIN utenti u ON u.id = u2o.id_utente
               WHERE u2o.id_e2o = ?");
            $stmtDet->bind_param('i', $row['id_e2o']);
            $stmtDet->execute();
            $resDet = $stmtDet->get_result();
            $rowsDet = [];
            while ($r = $resDet->fetch_assoc()) {
                $r['importo'] = (float)$r['importo'];
                $rowsDet[] = $r;
            }
            $stmtDet->close();
            $row['rows'] = $rowsDet;
        }
        $saldoTot += $row['saldo_utente'];
        $movimenti[] = $row;
    }
    $stmtMov->close();
    $ar['movimenti'] = $movimenti;
    $ar['saldoTot'] = $saldoTot;
    return $ar;
}
