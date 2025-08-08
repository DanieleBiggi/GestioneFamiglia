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
