<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/render_movimento.php';

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    exit;
}

$like = "%" . $q . "%";
$sql = "SELECT * FROM (
            SELECT id_movimento_revolut AS id, COALESCE(NULLIF(descrizione_extra,''), description) AS descrizione,
                   descrizione_extra, started_date AS data_operazione, amount,
                   etichette, id_gruppo_transazione, 'revolut' AS source, 'movimenti_revolut' AS tabella
            FROM v_movimenti_revolut
            UNION ALL
            SELECT be.id_entrata AS id, be.descrizione_operazione AS descrizione, be.descrizione_extra,
                   be.data_operazione, be.importo AS amount,
                   (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette,
                   be.id_gruppo_transazione, 'ca' AS source, 'bilancio_entrate' AS tabella
            FROM bilancio_entrate be
            UNION ALL
            SELECT bu.id_uscita AS id, bu.descrizione_operazione AS descrizione, bu.descrizione_extra,
                   bu.data_operazione, -bu.importo AS amount,
                   (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = bu.id_uscita AND eo.tabella_operazione='bilancio_uscite') AS etichette,
                   bu.id_gruppo_transazione, 'ca' AS source, 'bilancio_uscite' AS tabella
            FROM bilancio_uscite bu
        ) t
        WHERE descrizione LIKE ? OR descrizione_extra LIKE ? OR id_gruppo_transazione LIKE ? OR etichette LIKE ?
        ORDER BY data_operazione DESC LIMIT 50";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $like, $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();

while ($mov = $result->fetch_assoc()) {
    render_movimento($mov);
}
