<?php include 'includes/session_check.php'; ?>
<?php
include 'includes/db.php';
require_once 'includes/permissions.php';
if (!has_permission($conn, 'page:index.php', 'view')) { http_response_code(403); exit('Accesso negato'); }
require_once 'includes/render_movimento.php';
include 'includes/header.php';

// Limit data to the current user when fetching personal balances
$idUtente = $_SESSION['utente_id'] ?? 0;
$idFamiglia = $_SESSION['id_famiglia_gestione'] ?? 0;

$utilityServices = [
    [
        'href' => 'aggiungi_entrata.php',
        'icon' => 'bi-arrow-down-circle',
        'label' => 'Aggiungi entrata',
        'permission' => null,
        'show_in_modal' => false,
    ],
    [
        'href' => 'turni.php',
        'icon' => 'bi-calendar-week',
        'label' => 'Turni',
        'permission' => 'page:turni.php',
        'show_in_modal' => true,
        'always_show_in_modal' => true,
    ],
    [
        'href' => 'upload_movimenti.php',
        'icon' => 'bi-cloud-upload',
        'label' => 'Carica file',
        'permission' => null,
        'show_in_modal' => false,
    ],
    [
        'href' => 'aggiungi_uscita.php',
        'icon' => 'bi-arrow-up-circle',
        'label' => 'Aggiungi uscita',
        'permission' => 'page:aggiungi_uscita.php',
        'show_in_modal' => true,
    ],
    [
        'href' => 'password.php',
        'icon' => 'bi-shield-lock',
        'label' => 'Siti e password',
        'permission' => 'page:password.php',
        'show_in_modal' => true,
    ],
    [
        'href' => 'budget.php',
        'icon' => 'bi-wallet2',
        'label' => 'Budget',
        'permission' => 'page:budget.php',
        'show_in_modal' => true,
    ],
    [
        'href' => 'mezzi.php',
        'icon' => 'bi-truck',
        'label' => 'Mezzi',
        'permission' => 'page:mezzi.php',
        'show_in_modal' => true,
    ],
    [
        'href' => 'eventi.php',
        'icon' => 'bi-calendar-event',
        'label' => 'Eventi',
        'permission' => 'page:eventi.php',
        'show_in_modal' => true,
    ],
    [
        'href' => 'lista_spesa.php',
        'icon' => 'bi-cart',
        'label' => 'Lista spesa',
        'permission' => 'page:lista_spesa.php',
        'show_in_modal' => true,
    ],
    [
        'href' => 'storia.php',
        'icon' => 'bi-clock-history',
        'label' => 'Storia',
        'permission' => 'page:storia.php',
        'show_in_modal' => true,
    ],
    [
        'href' => 'film.php',
        'icon' => 'bi-film',
        'label' => 'Film',
        'permission' => 'page:film.php',
        'show_in_modal' => true,
    ],
    [
        'href' => 'vacanze_lista.php',
        'icon' => 'bi-airplane',
        'label' => 'Vacanze',
        'permission' => 'page:vacanze.php',
        'show_in_modal' => true,
    ],
    [
        'href' => 'ocr_caricamenti_scontrini.php',
        'icon' => 'bi-receipt',
        'label' => 'Scontrini',
        'permission' => 'page:ocr_caricamenti_scontrini.php',
        'show_in_modal' => true,
    ],
    [
        'href' => 'upload.php',
        'icon' => 'bi-cloud-upload',
        'label' => 'Upload',
        'permission' => 'page:upload.php',
        'show_in_modal' => true,
    ],
    [
        'href' => 'etichette_lista.php',
        'icon' => 'bi-tags',
        'label' => 'Etichette',
        'permission' => 'page:etichette_lista.php',
        'show_in_modal' => true,
        'condition' => function () use ($idFamiglia) {
            return (int) $idFamiglia === 1;
        },
    ],
];

$availableUtilities = array_values(array_filter($utilityServices, function ($utility) use ($conn) {
    if (isset($utility['condition']) && is_callable($utility['condition']) && !$utility['condition']()) {
        return false;
    }

    if (!empty($utility['permission']) && !has_permission($conn, $utility['permission'], 'view')) {
        return false;
    }

    return true;
}));

$firstUtilities = array_slice($availableUtilities, 0, 3);
$firstUtilityHrefs = array_column($firstUtilities, 'href');
$remainingUtilities = array_values(array_filter($availableUtilities, function ($utility) use ($firstUtilityHrefs) {
    if (!($utility['show_in_modal'] ?? true)) {
        return false;
    }

    if (!empty($utility['always_show_in_modal'])) {
        return true;
    }

    return !in_array($utility['href'], $firstUtilityHrefs, true);
}));

$hasAdditionalUtilities = false;
foreach ($remainingUtilities as $utility) {
    if (!in_array($utility['href'], $firstUtilityHrefs, true)) {
        $hasAdditionalUtilities = true;
        break;
    }
}

$renderUtilityGrid = function () use ($firstUtilities, $remainingUtilities, $hasAdditionalUtilities) {
    if (empty($firstUtilities)) {
        return;
    }
    ?>
    <div class="row text-center g-2 mb-3">
      <?php foreach ($firstUtilities as $utility): ?>
        <div class="col-3">
          <a href="<?= htmlspecialchars($utility['href'], ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none text-white">
            <div class="badge-etichetta rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                 style="width:50px;height:50px">
              <i class="bi <?= htmlspecialchars($utility['icon'], ENT_QUOTES, 'UTF-8') ?> fs-4"></i>
            </div>
            <div><?= htmlspecialchars($utility['label'], ENT_QUOTES, 'UTF-8') ?></div>
          </a>
        </div>
      <?php endforeach; ?>
      <?php if ($hasAdditionalUtilities): ?>
        <div class="col-3">
          <a href="#" class="text-decoration-none text-white" data-bs-toggle="modal" data-bs-target="#altroModal">
            <div class="badge-etichetta rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                 style="width:50px;height:50px">
              <i class="bi bi-three-dots fs-4"></i>
            </div>
            <div>Altro</div>
          </a>
        </div>
      <?php endif; ?>
    </div>
    <?php
};

$salvadanai = [];
$salvadanaiVisibili = [];
if ($idFamiglia) {
    $stmt = $conn->prepare("SELECT s.id_salvadanaio, s.nome_salvadanaio, s.importo_attuale, MAX(b.importo) AS importo, COALESCE(MAX(u.nascosto),0) AS nascosto, COALESCE(MAX(u.preferito),0) AS preferito FROM salvadanai s JOIN budget b ON b.id_salvadanaio = s.id_salvadanaio LEFT JOIN utenti2salvadanai u ON u.id_salvadanaio = s.id_salvadanaio AND u.id_utente = ? WHERE b.id_famiglia = ? AND b.data_scadenza > CURDATE() GROUP BY s.id_salvadanaio, s.nome_salvadanaio, s.importo_attuale");
    $stmt->bind_param('ii', $idUtente, $idFamiglia);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $salvadanai[] = $row;
        if (!$row['nascosto']) {
            $salvadanaiVisibili[] = $row;
        }
    }
    usort($salvadanaiVisibili, fn($a,$b) => $b['preferito'] <=> $a['preferito']);
    $stmt->close();
}

if (has_permission($conn, 'page:index.php-movimenti', 'view')): ?>
<?php if (!empty($salvadanaiVisibili)): ?>
<div id="salvadanaiCarousel" class="carousel slide mb-3" data-bs-interval="false">
  
  <div class="carousel-inner">
    <?php foreach ($salvadanaiVisibili as $k => $s): ?>
    <div class="carousel-item <?= $k === 0 ? 'active' : '' ?>">
      <div class="text-center">
        <div class="d-flex justify-content-center align-items-center">
          <h5 class="mb-1 me-2"><?= htmlspecialchars($s['nome_salvadanaio']) ?></h5>
          <span class="badge bg-secondary"><?= number_format($s['importo'], 2, ',', '.') ?></span>
        </div>
        <div class="fs-3"><?= number_format($s['importo_attuale'], 2, ',', '.') ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="carousel-indicators" style="position: static;margin-top: 0.5rem;">
    <?php foreach ($salvadanaiVisibili as $k => $s): ?>
      <button type="button" data-bs-target="#salvadanaiCarousel" data-bs-slide-to="<?= $k ?>" class="<?= $k === 0 ? 'active' : '' ?>" aria-current="<?= $k === 0 ? 'true' : 'false' ?>" aria-label="Slide <?= $k + 1 ?>"></button>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="text-end mb-3">
  <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#salvadanaiModal"><i class="bi bi-gear"></i></button>
</div>

<div class="modal fade" id="salvadanaiModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title">Gestione salvadanai</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="salvadanaiForm">
          <?php foreach ($salvadanai as $s): ?>
          <div class="d-flex justify-content-between align-items-center mb-2 salvadanaio-item" data-id="<?= $s['id_salvadanaio'] ?>" data-nascosto="<?= $s['nascosto'] ?>" data-preferito="<?= $s['preferito'] ?>">
            <span><?= htmlspecialchars($s['nome_salvadanaio']) ?></span>
            <div>
              <i class="me-3 toggle-preferito bi <?= $s['preferito'] ? 'bi-star-fill text-warning' : 'bi-star' ?>"></i>
              <i class="toggle-nascosto bi <?= $s['nascosto'] ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
            </div>
          </div>
          <?php endforeach; ?>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
        <button type="button" class="btn btn-primary" id="saveSalvadanai">Salva</button>
      </div>
    </div>
  </div>
</div>
<?php $renderUtilityGrid(); ?>

<input type="text" id="search" class="form-control bg-dark text-white border-secondary mb-3" placeholder="Cerca nei movimenti">
<div id="searchResults"></div>

<?php
  $movimenti_revolut = "";
  if (isset($_SESSION['id_famiglia_gestione']) && $_SESSION['id_famiglia_gestione'] == 1)
  {
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
            ".$movimenti_revolut."
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
        ORDER BY data_operazione DESC LIMIT 3";

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
<?php endif; 
$movimenti_revolut = "";
  if (isset($_SESSION['id_famiglia_gestione']) && $_SESSION['id_famiglia_gestione'] == 1)
  {
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
            ".$movimenti_revolut."
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
        WHERE id_gruppo_transazione IS NULL AND data_operazione >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
        ORDER BY data_operazione DESC";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0): ?>
    <div class="mt-2 mb-2"><?= $result->num_rows ?> movimenti senza gruppo</div>
  <div id="MovimentiSenzaGruppo" class="list-group">
    <?php while($row = $result->fetch_assoc()): ?>
      <?php render_movimento($row); ?>
    <?php endwhile; ?>

  </div>
<?php else: ?>
  <!--<p class="text-center text-white mt-2">Tutti i movimenti hanno un gruppo.</p>-->
<?php endif; 
    ?>

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
<?php if (!empty($firstUtilities)): ?>
  <?php $renderUtilityGrid(); ?>
<?php else: ?>
  <p class="text-center text-muted">Nessun servizio disponibile.</p>
<?php endif; ?>
<p class="text-center text-muted">Movimenti non disponibili per questa famiglia.</p>
<?php endif; ?>

<?php if (!empty($remainingUtilities) && $hasAdditionalUtilities): ?>
<div class="modal fade" id="altroModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title">Altre funzioni</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-grid gap-2">
          <?php foreach ($remainingUtilities as $utility): ?>
          <a href="<?= htmlspecialchars($utility['href'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-light w-100 text-start d-flex align-items-center">
            <i class="bi <?= htmlspecialchars($utility['icon'], ENT_QUOTES, 'UTF-8') ?> me-2"></i><?= htmlspecialchars($utility['label'], ENT_QUOTES, 'UTF-8') ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
