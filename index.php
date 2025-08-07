<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/render_movimento.php';
include 'includes/header.php';

require_once 'includes/render_movimento.php';

if (isset($_SESSION['id_famiglia_gestione']) && $_SESSION['id_famiglia_gestione'] == 1): ?>

<div class="mb-3">
  <a href="aggiungi_entrata.php" class="btn btn-outline-light btn-sm">Aggiungi entrata</a>
  <a href="aggiungi_uscita.php" class="btn btn-outline-light btn-sm">Aggiungi uscita</a>
  <a href="upload_movimenti.php" class="btn btn-outline-light btn-sm">Carica file</a>
</div>

<input type="text" id="search" class="form-control bg-dark text-white border-secondary mb-3" placeholder="Cerca nei movimenti">
<div id="searchResults"></div>

<?php
 $sql = "SELECT * FROM (
            SELECT id_movimento_revolut AS id, COALESCE(NULLIF(descrizione_extra,''), description) AS descrizione, bm.descrizione_extra,
                   started_date AS data_operazione, amount,
                   (SELECT GROUP_CONCAT(e.id_etichetta SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = bm.id_movimento_revolut AND eo.tabella_operazione='movimenti_revolut') AS etichette,
                   be.id_gruppo_transazione, 'revolut' AS source, 'movimenti_revolut' AS tabella, null as mezzo
            FROM movimenti_revolut bm            
            UNION ALL
            SELECT be.id_entrata AS id, COALESCE(NULLIF(be.descrizione_extra,''), be.descrizione_operazione) AS descrizione, be.descrizione_extra,
                   be.data_operazione, be.importo AS amount,
                   (SELECT GROUP_CONCAT(e.id_etichetta SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = be.id_entrata AND eo.tabella_operazione='bilancio_entrate') AS etichette,
                   be.id_gruppo_transazione, 'ca' AS source, 'bilancio_entrate' AS tabella, be.mezzo
            FROM bilancio_entrate be
            UNION ALL
            SELECT bu.id_uscita AS id, COALESCE(NULLIF(bu.descrizione_extra,''), bu.descrizione_operazione) AS descrizione, bu.descrizione_extra,
                   bu.data_operazione, -bu.importo AS amount,
                   (SELECT GROUP_CONCAT(e.id_etichetta SEPARATOR ',')
                      FROM bilancio_etichette2operazioni eo
                      JOIN bilancio_etichette e ON e.id_etichetta = eo.id_etichetta
                     WHERE eo.id_tabella = bu.id_uscita AND eo.tabella_operazione='bilancio_uscite') AS etichette,
                   bu.id_gruppo_transazione, 'ca' AS source, 'bilancio_uscite' AS tabella, bu.mezzo
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

 <!-- Modal conferma eliminazione -->
 <div class="modal fade" id="deleteModal" tabindex="-1">
   <div class="modal-dialog">
     <div class="modal-content bg-dark text-white">
       <div class="modal-header">
         <h5 class="modal-title">Conferma eliminazione</h5>
         <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
       </div>
       <div class="modal-body">
         Sei sicuro di voler eliminare questo movimento?
       </div>
       <div class="modal-footer">
         <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
         <button type="button" class="btn btn-danger" id="confirmDelete">Elimina</button>
       </div>
     </div>
   </div>
 </div>

 <script src="js/index.js"></script>
 <script src="js/delete_movimento.js"></script>
<?php else: ?>
<p class="text-center text-muted">Movimenti non disponibili per questa famiglia.</p>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
