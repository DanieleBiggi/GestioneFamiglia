<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/render_movimento.php';
include 'includes/header.php';

require_once 'includes/render_movimento.php';

?>

<input type="text" id="search" class="form-control bg-dark text-white border-secondary mb-3" placeholder="Cerca nei movimenti">
<div id="searchResults"></div>

<?php
$sql = "SELECT * FROM (
            SELECT id_movimento_revolut AS id, COALESCE(NULLIF(descrizione_extra,''), description) AS descrizione,
                   descrizione_extra, started_date AS data_operazione, amount,
                   etichette, id_gruppo_transazione, 'revolut' AS source, 'movimenti_revolut' AS tabella
            FROM v_movimenti_revolut
            UNION ALL
            SELECT be.id_entrata AS id, COALESCE(NULLIF(be.descrizione_extra,''), be.descrizione_operazione) AS descrizione, be.descrizione_extra,
                   be.data_operazione, be.importo AS amount,
                   (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette,
                   be.id_gruppo_transazione, 'ca' AS source, 'bilancio_entrate' AS tabella
            FROM bilancio_entrate be
            UNION ALL
            SELECT bu.id_uscita AS id, COALESCE(NULLIF(bu.descrizione_extra,''), bu.descrizione_operazione) AS descrizione, bu.descrizione_extra,
                   bu.data_operazione, -bu.importo AS amount,
                   (SELECT GROUP_CONCAT(e.descrizione SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = bu.id_uscita AND eo.tabella_operazione='bilancio_uscite') AS etichette,
                   bu.id_gruppo_transazione, 'ca' AS source, 'bilancio_uscite' AS tabella
            FROM bilancio_uscite bu
        ) t
        ORDER BY data_operazione DESC LIMIT 5";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0): ?>
  <div id="recentMovimenti" class="list-group">
    <?php while($row = $result->fetch_assoc()): ?>
      <?php render_movimento($row); ?>
    <?php endwhile; ?>

  </div>

  <div class="text-center mt-3">
    <a href="tutti_movimenti.php" class="btn btn-outline-light btn-sm">Visualizza tutti</a>
  </div>
<?php else: ?>
  <p class="text-center text-muted">Nessun movimento presente.</p>
<?php endif; ?>

<script src="js/index.js"></script>
<?php include 'includes/footer.php'; ?>
