<?php
/**
 * Calcola le prossime scadenze dei tagliandi per i mezzi attivi.
 *
 * @param mysqli    $conn        Connessione al database
 * @param int|null  $idFamiglia  (opzionale) Limita ai mezzi della famiglia indicata
 * @return array                 Elenco con informazioni sulle scadenze
 */
function get_tagliandi_scadenze(mysqli $conn, ?int $idFamiglia = null): array {
    $sql = "SELECT m.id_mezzo, m.nome_mezzo, m.data_immatricolazione, m.id_utente, u.email,
                   mt.id_tagliando, mt.nome_tagliando, mt.mesi_da_immatricolazione,
                   mt.massimo_km_tagliando, mt.frequenza_mesi, mt.frequenza_km
            FROM mezzi m
            JOIN mezzi_tagliandi mt ON mt.id_mezzo = m.id_mezzo
            JOIN utenti u ON u.id = m.id_utente
            WHERE m.attivo = 1";
    if ($idFamiglia !== null) {
        $sql .= " AND m.id_famiglia = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $idFamiglia);
    } else {
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $scadenze = [];
    while ($row = $res->fetch_assoc()) {
        // ultimo evento di tagliando
        $stmtEv = $conn->prepare("SELECT data_evento, km_evento FROM mezzi_eventi WHERE id_mezzo = ? AND id_tipo_evento = 1 ORDER BY data_evento DESC LIMIT 1");
        $stmtEv->bind_param('i', $row['id_mezzo']);
        $stmtEv->execute();
        $ev = $stmtEv->get_result()->fetch_assoc();
        $stmtEv->close();

        // ultimo chilometraggio registrato
        $stmtKm = $conn->prepare("SELECT chilometri FROM mezzi_chilometri WHERE id_mezzo = ? ORDER BY data_chilometro DESC LIMIT 1");
        $stmtKm->bind_param('i', $row['id_mezzo']);
        $stmtKm->execute();
        $kmRow = $stmtKm->get_result()->fetch_assoc();
        $stmtKm->close();

        if ($ev) {
            $nextDate = date('Y-m-d', strtotime($ev['data_evento'] . " +{$row['frequenza_mesi']} months"));
            $nextKm = (int)$ev['km_evento'] + (int)$row['frequenza_km'];
        } else {
            $nextDate = date('Y-m-d', strtotime($row['data_immatricolazione'] . " +{$row['mesi_da_immatricolazione']} months"));
            $nextKm = (int)$row['massimo_km_tagliando'];
        }

        $currentKm = $kmRow['chilometri'] ?? null;
        $kmRemaining = $currentKm !== null ? $nextKm - (int)$currentKm : null;
        $daysRemaining = (int) floor((strtotime($nextDate) - time()) / 86400);

        $scadenze[] = [
            'id_mezzo'       => (int)$row['id_mezzo'],
            'nome_mezzo'     => $row['nome_mezzo'],
            'id_tagliando'   => (int)$row['id_tagliando'],
            'nome_tagliando' => $row['nome_tagliando'],
            'next_date'      => $nextDate,
            'next_km'        => $nextKm,
            'days_remaining' => $daysRemaining,
            'km_remaining'   => $kmRemaining,
            'email'          => $row['email'],
            'id_utente'      => (int)$row['id_utente']
        ];
    }
    $stmt->close();
    return $scadenze;
}
?>
