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


function get_utenti_e_quote_operazione_etichettata($id_e2o)
{
    global $conn;
    // Quote per utente e dati per la modal
    $stmtU = $conn->prepare(
        "SELECT
        	e2o.id_e2o,
            v.descrizione,
            v.id_gruppo_transazione,
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
        JOIN 
		(SELECT 
    IFNULL(bu.id_utente, IFNULL(ben.id_utente, 
        (CASE 
            WHEN (be2o.tabella_operazione = 'mh') THEN 1 
            WHEN (be2o.tabella_operazione = 'mr') THEN 1 
            ELSE NULL 
        END))) AS id_utente_operazione,
        COALESCE(bu.id_gruppo_transazione,ben.id_gruppo_transazione,mh.id_gruppo_transazione,mr.id_gruppo_transazione) AS id_gruppo_transazione,
    IFNULL(bu.descrizione_operazione, IFNULL(ben.descrizione_operazione, 
        CONVERT(CONVERT(IFNULL(mh.descrizione, mr.description) USING utf8) USING utf8mb4))) AS descrizione_operazione,
    IFNULL(bu.data_operazione, IFNULL(ben.data_operazione, IFNULL(mh.data_operazione, mr.started_date))) AS data_operazione,
    be2o.id_e2o AS id_e2o,
    be2o.id_etichetta AS id_etichetta,
    IFNULL(be2o.descrizione_extra, IFNULL(bu.descrizione_extra, ben.descrizione_extra)) AS descrizione_extra,
    be2o.allegato AS allegato,
    bet.descrizione AS descrizione,
    IFNULL(bu.importo, IFNULL(ben.importo, IFNULL(mh.importo, mr.amount))) AS importo_totale_operazione,
    be2o.importo AS importo_etichetta,
    ((CASE 
        WHEN (IFNULL(be2o.importo, 0) <> 0) THEN be2o.importo 
        ELSE IFNULL(bu.importo, IFNULL(ben.importo, IFNULL(mh.importo, mr.amount))) 
    END) / SUM(IFNULL(bu2e.quote, 1))) AS importo 
FROM 
    bilancio_etichette2operazioni be2o 
        JOIN bilancio_etichette bet ON (bet.id_etichetta = be2o.id_etichetta)
        LEFT JOIN bilancio_entrate ben ON ((be2o.tabella_operazione = 'bilancio_entrate') AND (be2o.id_tabella = ben.id_entrata))
        LEFT JOIN bilancio_uscite bu ON ((be2o.tabella_operazione = 'bilancio_uscite') AND (be2o.id_tabella = bu.id_uscita))
        LEFT JOIN bilancio_utenti2operazioni_etichettate bu2e ON (bu2e.id_e2o = be2o.id_e2o)
        LEFT JOIN movimenti_hype mh ON ((be2o.tabella_operazione = 'movimenti_hype') AND (be2o.id_tabella = mh.id_movimento_hype))
        LEFT JOIN movimenti_revolut mr ON ((be2o.tabella_operazione = 'movimenti_revolut') AND (be2o.id_tabella = mr.id_movimento_revolut))
WHERE 
    (bet.attivo in (0,1)) 
GROUP BY 
    be2o.id_e2o)
		v ON
            u2o.id_e2o = v.id_e2o
        JOIN bilancio_etichette2operazioni e2o ON
            e2o.id_e2o = u2o.id_e2o
        JOIN utenti u ON
            u.id = u2o.id_utente    
        WHERE
            u2o.id_e2o = ?
        ORDER BY
            v.data_operazione
        DESC"
    );
    $stmtU->bind_param('i', $id_e2o);
    $perUser = [];
    if ($stmtU->execute()) {
        $resU = $stmtU->get_result();
        $rows = [];
        while ($r = $resU->fetch_assoc()) {
            
        $r['importo'] = (float)($r['importo_utente'] ?? 0);
        $r['pagante'] = false;
            $rows[] = $r;
        }
        $count = count($rows) ?: 1;
        
        
        $perUser = $rows;
    }
    $stmtU->close();
    return $perUser;
}

/**
 * Calcola l'importo spettante a un utente in base alle quote e al pagante.
 *
 * La logica riprende quella utilizzata nella funzione get_saldo_e_movimenti_utente
 * per determinare il saldo di ciascun utente.
 *
 * @param bool  $isPagante        True se l'utente ha effettuato l'operazione.
 * @param float $importoUtente    Importo specifico assegnato all'utente.
 * @param float $importoEtichetta Importo complessivo dell'etichetta (se diverso dal totale).
 * @param float $importoTotale    Importo totale dell'operazione.
 * @param float $quota            Quota dell'utente.
 *
 * @return float Importo calcolato per l'utente (positivo per credito, negativo per debito).
 */
function calcola_importo_quota(
    bool $isPagante,
    float $importoUtente,
    float $importoEtichetta,
    float $importoTotale,
    float $quota
): float {
    if ($isPagante) {
        if ($importoUtente != 0.0) {
            return -$importoUtente;
        }
        if ($importoEtichetta != 0.0) {
            return -($importoEtichetta * $quota);
        }
        return -($importoTotale - ($importoTotale * $quota));
    }

    if ($importoUtente != 0.0) {
        return $importoUtente;
    }
    if ($importoEtichetta != 0.0) {
        return $importoEtichetta * $quota;
    }
    return $importoTotale * $quota;
}

function get_saldo_e_movimenti_utente($idUtente, $dataSaldo = null)
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
                        WHEN v.id_utente_operazione = u2o.id_utente THEN -(
                            CASE
                                WHEN IFNULL(u2o.importo_utente, 0) <> 0 THEN u2o.importo_utente
                                WHEN v.importo_etichetta <> 0 THEN (v.importo * u2o.quote)
                                ELSE (v.importo_totale_operazione - (v.importo * u2o.quote))
                            END
                        )
                        ELSE (
                            CASE
                                WHEN IFNULL(u2o.importo_utente, 0) <> 0 THEN u2o.importo_utente
                                ELSE (v.importo * u2o.quote)
                            END
                        )
                    END) AS saldo_utente
               FROM bilancio_utenti2operazioni_etichettate u2o
               JOIN v_bilancio_etichette2operazioni_a_testa v ON u2o.id_e2o = v.id_e2o
               JOIN bilancio_etichette2operazioni e2o ON e2o.id_e2o = u2o.id_e2o
               WHERE u2o.id_utente = ? AND " . ($dataSaldo === null ? "u2o.saldata = 0" : "u2o.saldata = 1 AND DATE(u2o.data_saldo) = ?") . "
               ORDER BY v.data_operazione DESC";

    $stmtMov = $conn->prepare($sqlMov);
    if ($dataSaldo === null) {
        $stmtMov->bind_param('i', $idUtente);
    } else {
        $stmtMov->bind_param('is', $idUtente, $dataSaldo);
    }
    $stmtMov->execute();
    $resMov = $stmtMov->get_result();
    $movimenti = [];
    $saldoTot = 0.0;
    while ($row = $resMov->fetch_assoc()) {
        $row['saldo_utente'] = (float)$row['saldo_utente'];
        if ($isAdmin) {
            $stmtDet = $conn->prepare("SELECT u2o.id_u2o, u.nome, u.cognome,
                    (CASE
                        WHEN v.id_utente_operazione = u2o.id_utente THEN -(
                            CASE
                                WHEN IFNULL(u2o.importo_utente, 0) <> 0 THEN u2o.importo_utente
                                WHEN v.importo_etichetta <> 0 THEN (v.importo * u2o.quote)
                                ELSE (v.importo_totale_operazione - (v.importo * u2o.quote))
                            END
                        )
                        ELSE (
                            CASE
                                WHEN IFNULL(u2o.importo_utente, 0) <> 0 THEN u2o.importo_utente
                                ELSE (v.importo * u2o.quote)
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
