<?php
include '../includes/session_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/render_movimento.php';
setlocale(LC_TIME, 'it_IT.UTF-8');

$mese = $_GET['mese'] ?? date('Y-m');

$idUtente = $_SESSION['utente_id'] ?? 0;

$movimenti_revolut = "";
if (isset($_SESSION['id_famiglia_gestione']) && $_SESSION['id_famiglia_gestione'] == 1) {
    $movimenti_revolut =
        "SELECT id_movimento_revolut AS id, COALESCE(NULLIF(descrizione_extra,''), description) AS descrizione, bm.descrizione_extra,
                   started_date AS data_operazione, amount,
                   (SELECT GROUP_CONCAT(CONCAT(e.id_etichetta, ':', e.descrizione) SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = bm.id_movimento_revolut AND eo.tabella_operazione='movimenti_revolut') AS etichette,
                   bm.id_gruppo_transazione, g.descrizione AS gruppo_descrizione, 'revolut' AS source, 'movimenti_revolut' AS tabella, null as mezzo
            FROM v_movimenti_revolut_filtrati bm
            LEFT JOIN bilancio_gruppi_transazione g ON g.id_gruppo_transazione = bm.id_gruppo_transazione
            UNION ALL";
}

$sql = "SELECT * FROM (
            {$movimenti_revolut}
            SELECT be.id_entrata AS id, COALESCE(NULLIF(be.descrizione_extra,''), be.descrizione_operazione) AS descrizione, be.descrizione_extra,
                   be.data_operazione, be.importo AS amount,
                   (SELECT GROUP_CONCAT(CONCAT(e.id_etichetta, ':', e.descrizione) SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette,
                   be.id_gruppo_transazione, g.descrizione AS gruppo_descrizione, 'ca' AS source, 'bilancio_entrate' AS tabella, be.mezzo
            FROM bilancio_entrate be
            LEFT JOIN bilancio_gruppi_transazione g ON g.id_gruppo_transazione = be.id_gruppo_transazione
            WHERE be.id_utente = {$idUtente}
            UNION ALL
            SELECT bu.id_uscita AS id, COALESCE(NULLIF(bu.descrizione_extra,''), bu.descrizione_operazione) AS descrizione, bu.descrizione_extra,
                   bu.data_operazione, -bu.importo AS amount,
                   (SELECT GROUP_CONCAT(CONCAT(e.id_etichetta, ':', e.descrizione) SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = bu.id_uscita AND eo.tabella_operazione='bilancio_uscite') AS etichette,
                   bu.id_gruppo_transazione, g.descrizione AS gruppo_descrizione, 'ca' AS source, 'bilancio_uscite' AS tabella, bu.mezzo
            FROM bilancio_uscite bu
            LEFT JOIN bilancio_gruppi_transazione g ON g.id_gruppo_transazione = bu.id_gruppo_transazione
            WHERE bu.id_utente = {$idUtente}
        ) t
        WHERE DATE_FORMAT(data_operazione, '%Y-%m') = ?
        ORDER BY data_operazione DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $mese);
$stmt->execute();
$result = $stmt->get_result();

echo '<div class="month-section" data-mese="' . htmlspecialchars($mese, ENT_QUOTES) . '">';
$giorno_corrente = '';
while ($mov = $result->fetch_assoc()) {
    $giorno = strftime('%A %e %B', strtotime($mov['data_operazione']));
    if ($giorno !== $giorno_corrente) {
        echo '<div class="day-header mt-3 mb-1 fw-bold">' . ucfirst($giorno) . '</div>';
        $giorno_corrente = $giorno;
    }

    render_movimento($mov);
}
echo '</div>';

