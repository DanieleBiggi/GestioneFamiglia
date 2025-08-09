<?php
include '../includes/session_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/render_movimento.php';

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    exit;
}

$like = "%" . $q . "%";
$sql = "SELECT * FROM (
            SELECT bm.id_movimento_revolut AS id, COALESCE(NULLIF(bm.descrizione_extra,''), bm.description) AS descrizione, bm.description as descrizione_operazione,
                   bm.descrizione_extra, bm.started_date AS data_operazione, bm.amount,
                   (SELECT GROUP_CONCAT(CONCAT(e.id_etichetta, ':', e.descrizione) SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = bm.id_movimento_revolut AND eo.tabella_operazione='movimenti_revolut') AS etichette,
                   bm.id_gruppo_transazione, 'revolut' AS source, 'movimenti_revolut' AS tabella, NULL AS mezzo
            FROM v_movimenti_revolut_filtrati bm
            UNION ALL
            SELECT be.id_entrata AS id, COALESCE(NULLIF(be.descrizione_extra,''), be.descrizione_operazione) AS descrizione, be.descrizione_extra, be.descrizione_operazione as descrizione_operazione,
                   be.data_operazione, be.importo AS amount,
                   (SELECT GROUP_CONCAT(CONCAT(e.id_etichetta, ':', e.descrizione) SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette,
                   be.id_gruppo_transazione, 'ca' AS source, 'bilancio_entrate' AS tabella, be.mezzo
            FROM bilancio_entrate be
            UNION ALL
            SELECT bu.id_uscita AS id, COALESCE(NULLIF(bu.descrizione_extra,''), bu.descrizione_operazione) AS descrizione, bu.descrizione_extra, bu.descrizione_operazione as descrizione_operazione,
                   bu.data_operazione, -bu.importo AS amount,
                   (SELECT GROUP_CONCAT(CONCAT(e.id_etichetta, ':', e.descrizione) SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = bu.id_uscita AND eo.tabella_operazione='bilancio_uscite') AS etichette,
                   bu.id_gruppo_transazione, 'ca' AS source, 'bilancio_uscite' AS tabella, bu.mezzo
            FROM bilancio_uscite bu
        ) t
        WHERE descrizione LIKE ? OR descrizione_operazione LIKE ? OR descrizione_extra LIKE ? OR id_gruppo_transazione LIKE ? OR etichette LIKE ?
        ORDER BY data_operazione DESC LIMIT 50";
$stmt = $conn->prepare($sql);
$stmt->bind_param('sssss', $like, $like, $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();

while ($mov = $result->fetch_assoc()) {
    render_movimento($mov);
}
