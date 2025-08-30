<?php require_once __DIR__ . '/permissions.php'; ?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gestione Famiglia 2.0</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/Gestionale25/includes/theme.php">
  <link rel="stylesheet" href="/Gestionale25/assets/style.css">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link rel="manifest" href="/Gestionale25/manifest.json">
  <script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker
        .register('/Gestionale25/service-worker.js?ver=network', { scope: '/Gestionale25/' })
        .catch(console.error);
    });
  }
  </script>
</head>
<body>
<?php 

header('Content-Type: text/html; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4'); // IMPORTANTISSIMO
?>
<!-- Navbar -->
<nav class="navbar navbar-dark bg-dark px-3 d-flex justify-content-between align-items-center">
  <button class="btn btn-dark border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMenu">
    <i class="bi bi-list fs-3 text-white"></i>
  </button>
  <!--<a href="index.php" class="navbar-brand mb-0 h1">Gestione Famiglia 2.0</a>-->
  <a href="index.php" class="navbar-brand mb-0 h1">
    <img src="assets/<?= (($_SESSION['theme_id'] ?? 1) == 2) ? 'banner_nero.png' : 'banner.png' ?>" alt="Gestione Famiglia" height="40" />
  </a>
</nav>

<!-- Offcanvas Menù Laterale -->
<div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="offcanvasMenu" aria-labelledby="offcanvasMenuLabel">
  <div class="offcanvas-header border-bottom border-secondary">
    <h5 class="offcanvas-title" id="offcanvasMenuLabel">Menù</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Chiudi"></button>
  </div>
  <div class="offcanvas-body">
    <?php if (isset($_SESSION['utente_id'])): ?>
    <form method="post" action="cambia_famiglia.php" class="mb-3">
      <select name="id_famiglia_gestione" class="form-select bg-dark text-white border-secondary" onchange="this.form.submit()">
        <?php
        $stmt = $conn->prepare('SELECT f.id_famiglia, f.nome_famiglia, u2f.userlevelid FROM famiglie f JOIN utenti2famiglie u2f ON f.id_famiglia = u2f.id_famiglia WHERE u2f.id_utente = ?');
        $stmt->bind_param('i', $_SESSION['utente_id']);
        $stmt->execute();
        $resFam = $stmt->get_result();
        while ($fam = $resFam->fetch_assoc()):
          if (isset($_SESSION['id_famiglia_gestione']) && $_SESSION['id_famiglia_gestione'] == $fam['id_famiglia']) {
              $_SESSION['userlevelid'] = $fam['userlevelid'];
          }
        ?>
          <option value="<?= (int)$fam['id_famiglia'] ?>" <?= (isset($_SESSION['id_famiglia_gestione']) && $_SESSION['id_famiglia_gestione'] == $fam['id_famiglia']) ? 'selected' : '' ?>><?= htmlspecialchars($fam['nome_famiglia']) ?></option>
        <?php endwhile; $stmt->close(); ?>
      </select>
    </form>
    <form method="post" action="cambia_tema.php" class="mb-3">
      <select name="id_tema" class="form-select bg-dark text-white border-secondary" onchange="this.form.submit()">
        <?php
        $stmtTheme = $conn->prepare('SELECT id, nome FROM temi');
        $stmtTheme->execute();
        $resTheme = $stmtTheme->get_result();
        while ($t = $resTheme->fetch_assoc()):
        ?>
          <option value="<?= (int)$t['id'] ?>" <?= (($_SESSION['theme_id'] ?? 1) == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
        <?php endwhile; $stmtTheme->close(); ?>
      </select>
    </form>
    <?php endif; ?>
    <?php
      $isAdmin = false;
      if (isset($_SESSION['utente_id'])) {
          $stmtAdm = $conn->prepare('SELECT admin FROM utenti WHERE id = ?');
          $stmtAdm->bind_param('i', $_SESSION['utente_id']);
          $stmtAdm->execute();
          $isAdmin = ($stmtAdm->get_result()->fetch_assoc()['admin'] ?? 0) == 1;
          $stmtAdm->close();
      }
      $canImpersonate = $isAdmin && !isset($_SESSION['impersonator_id']);
    ?>
    <ul class="list-unstyled">
      <?php if (has_permission($conn, 'page:index.php', 'view')): ?>
      <li class="mb-3">
        <a href="/Gestionale25/index.php" class="btn btn-outline-light w-100 text-start">
          <i class="bi bi-house me-2 text-white"></i> Home
        </a>
      </li>
      <?php endif; ?>
      <?php if (isset($_SESSION['id_famiglia_gestione']) && $_SESSION['id_famiglia_gestione'] == 1 && has_permission($conn, 'page:etichette_lista.php', 'view')): ?>
      <li class="mb-3">
        <a href="/Gestionale25/etichette_lista.php" class="btn btn-outline-light w-100 text-start">
          <i class="bi bi-tag me-2 text-white"></i> Etichette
        </a>
      </li>
      <?php endif; ?>
      <?php if (has_permission($conn, 'page:credito_utente.php', 'view')): ?>
      <li class="mb-3">
        <a href="/Gestionale25/credito_utente.php" class="btn btn-outline-light w-100 text-start">
          <i class="bi bi-cash-coin me-2 text-white"></i> Saldo personale
        </a>
      </li>
      <?php endif; ?>
      <?php
        $showBudget = has_permission($conn, 'page:budget.php', 'view') ||
                      has_permission($conn, 'page:budget_anno.php', 'view') ||
                      has_permission($conn, 'page:budget_dashboard.php', 'view');
        if ($showBudget):
      ?>
      <li class="mb-3">
        <div class="dropdown w-100">
          <button class="btn btn-outline-light w-100 text-start dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-wallet me-2 text-white"></i> Budget
          </button>
          <ul class="dropdown-menu dropdown-menu-dark w-100">
            <?php if (has_permission($conn, 'page:budget.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/budget.php">Budget</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:budget_anno.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/budget_anno.php">Budget per anno</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:budget_dashboard.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/budget_dashboard.php">Dashboard budget</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:salvadanai.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/salvadanai.php">Salvadanai</a></li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php if (has_permission($conn, 'page:password.php', 'view')): ?>
      <li class="mb-3">
        <a href="/Gestionale25/password.php" class="btn btn-outline-light w-100 text-start">
          <i class="bi bi-key-fill me-2 text-white"></i> Siti e password
        </a>
      </li>
      <?php endif; ?>
      <?php
        $showUtility = has_permission($conn, 'page:mezzi.php', 'view') ||
                      has_permission($conn, 'page:eventi.php', 'view') ||
                      has_permission($conn, 'page:lista_spesa.php', 'view') ||
                      has_permission($conn, 'page:storia.php', 'view') ||
                      has_permission($conn, 'page:film.php', 'view') ||
                      has_permission($conn, 'page:vacanze.php', 'view') ||
                      has_permission($conn, 'page:upload.php', 'view');
        if ($showUtility):
      ?>
      <li class="mb-3">
        <div class="dropdown w-100">
          <button class="btn btn-outline-light w-100 text-start dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-tools me-2 text-white"></i> Utility
          </button>
          <ul class="dropdown-menu dropdown-menu-dark w-100">
            <?php if (has_permission($conn, 'page:mezzi.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/mezzi.php"><i class="bi bi-car-front me-2 text-white"></i>Mezzi</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:eventi.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/eventi.php"><i class="bi bi-calendar-event me-2 text-white"></i>Eventi</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:turni.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/turni.php"><i class="bi bi-calendar-week me-2 text-white"></i>Turni</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:lista_spesa.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/lista_spesa.php"><i class="bi bi-basket me-2 text-white"></i>Lista spesa</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:storia.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/storia.php"><i class="bi bi-clock-history me-2 text-white"></i>Storia</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:film.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/film.php"><i class="bi bi-film me-2 text-white"></i>Film</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:vacanze.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/vacanze_lista.php"><i class="bi bi-airplane me-2 text-white"></i>Vacanze</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:upload.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/upload.php"><i class="bi bi-cloud-upload me-2 text-white"></i>Upload</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'table:turni_sync_google_log', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/table_manager.php?table=turni_sync_google_log"><i class="bi bi-journal-text me-2 text-white"></i>Log sincronizzazione turni</a></li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php
        $showUserAdmin = has_permission($conn, 'page:userlevel_permissions.php', 'view') ||
                         has_permission($conn, 'page:gestione_utenti.php', 'view') ||
                         $canImpersonate;
        if ($showUserAdmin):
      ?>
      <li class="mb-3">
        <div class="dropdown w-100">
          <button class="btn btn-outline-light w-100 text-start dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-gear me-2 text-white"></i> Utenti
          </button>
          <ul class="dropdown-menu dropdown-menu-dark w-100">
            <?php if (has_permission($conn, 'page:userlevel_permissions.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/userlevel_permissions.php"><i class="bi bi-shield-lock me-2 text-white"></i>Permessi Userlevel</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:gestione_utenti.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/gestione_utenti.php"><i class="bi bi-people me-2 text-white"></i>Gestione Utenti</a></li>
            <?php endif; ?>
            <?php if ($canImpersonate): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/impersonate.php"><i class="bi bi-person-badge me-2 text-white"></i>Impersona utente</a></li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php $showSecurity = has_permission($conn, 'page:change_password.php', 'view') || has_permission($conn, 'page:setup_passcode.php', 'view') || has_permission($conn, 'page:setup_passkey.php', 'view');
      if ($showSecurity): ?>
      <li class="mb-3">
        <div class="dropdown w-100">
          <button class="btn btn-outline-light w-100 text-start dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-shield-lock me-2 text-white"></i> Sicurezza
          </button>
          <ul class="dropdown-menu dropdown-menu-dark w-100">
            <?php if (has_permission($conn, 'page:change_password.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/change_password.php"><i class="bi bi-key me-2 text-white"></i>Cambia Password</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:setup_passcode.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/setup_passcode.php"><i class="bi bi-lock me-2 text-white"></i>Imposta Passcode</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:setup_passkey.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/setup_passkey.php"><i class="bi bi-fingerprint me-2 text-white"></i>Crea passkey</a></li>
            <?php endif; ?>
            <li><a class="dropdown-item text-white" href="/Gestionale"><i class="bi bi-arrow-return-left me-2 text-white"></i>Torna al vecchio</a></li>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php
        $tables = [
          'bilancio_descrizione2id' => 'Descrizioni',
          'bilancio_entrate' => 'Entrate',
          'bilancio_gruppi_categorie' => 'Gruppi categorie',
          'bilancio_gruppi_transazione' => 'Gruppi transazione',
          'bilancio_uscite' => 'Uscite',
          'codici_2fa' => 'Codici 2FA',
          'dispositivi_riconosciuti' => 'Dispositivi riconosciuti',
          'famiglie' => 'Famiglie',
          'userlevels' => 'User Levels',
          'utenti' => 'Utenti',
          'utenti2famiglie' => 'Utenti-Famiglie',
          'utenti2ip' => 'Utenti-IP'
        ];
        $tableLinks = [];
        foreach ($tables as $tbl => $label) {
            if (has_permission($conn, 'table:' . $tbl, 'view')) {
                $tableLinks[$tbl] = $label;
            }
        }
        if (!empty($tableLinks)):
      ?>
      <li class="mb-3">
        <div class="dropdown w-100">
          <button class="btn btn-outline-light w-100 text-start dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-gear me-2 text-white"></i> Configurazione
          </button>
          <ul class="dropdown-menu dropdown-menu-dark w-100">
            <li><h6 class="dropdown-header">Bilancio</h6></li>
            <?php if (isset($tableLinks['bilancio_descrizione2id'])): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/table_manager.php?table=bilancio_descrizione2id">Descrizioni</a></li>
            <?php endif; ?>
            <?php if (isset($tableLinks['bilancio_entrate'])): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/table_manager.php?table=bilancio_entrate">Entrate</a></li>
            <?php endif; ?>
            <?php if (isset($tableLinks['bilancio_gruppi_categorie'])): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/table_manager.php?table=bilancio_gruppi_categorie">Gruppi categorie</a></li>
            <?php endif; ?>
            <?php if (isset($tableLinks['bilancio_gruppi_transazione'])): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/table_manager.php?table=bilancio_gruppi_transazione">Gruppi transazione</a></li>
            <?php endif; ?>
            <?php if (isset($tableLinks['bilancio_uscite'])): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/table_manager.php?table=bilancio_uscite">Uscite</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header">Sicurezza</h6></li>
            <?php if (isset($tableLinks['codici_2fa'])): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/table_manager.php?table=codici_2fa">Codici 2FA</a></li>
            <?php endif; ?>
            <?php if (isset($tableLinks['dispositivi_riconosciuti'])): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/table_manager.php?table=dispositivi_riconosciuti">Dispositivi riconosciuti</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header">Gruppi di utenti</h6></li>
            <?php if (isset($tableLinks['famiglie'])): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/table_manager.php?table=famiglie">Famiglie</a></li>
            <?php endif; ?>
            <?php if (isset($tableLinks['userlevels'])): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/table_manager.php?table=userlevels">User Levels</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:invitati_eventi.php', 'view') || has_permission($conn, 'page:invitati_cibo.php', 'view') || has_permission($conn, 'page:eventi_tipi.php', 'view') || has_permission($conn, 'page:eventi_google_rules.php', 'view')): ?>
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header">Eventi</h6></li>
            <?php if (has_permission($conn, 'page:eventi_tipi.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/eventi_tipi.php">Tipi eventi</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:invitati_eventi.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/invitati_eventi.php">Invitati</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:invitati_cibo.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/invitati_cibo.php">Cibo</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:eventi_google_rules.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/eventi_google_rules.php">Regole Google Eventi</a></li>
            <?php endif; ?>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:turni_tipi.php', 'view')): ?>
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header">Turni</h6></li>
            <li><a class="dropdown-item text-white" href="/Gestionale25/turni_tipi.php">Tipi turni</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:temi.php', 'view')): ?>
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header">Aspetto</h6></li>
            <li><a class="dropdown-item text-white" href="/Gestionale25/temi.php">Temi</a></li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php if (isset($_SESSION['impersonator_id'])): ?>
      <li class="mb-3">
        <a href="/Gestionale25/stop_impersonate.php" class="btn btn-outline-warning w-100 text-start">
          <i class="bi bi-arrow-counterclockwise me-2 text-white"></i> Torna al tuo account
        </a>
      </li>
      <?php endif; ?>
      <li>
        <a href="/Gestionale25/logout.php" class="btn btn-outline-danger w-100 text-start">
          <i class="bi bi-box-arrow-right me-2"></i> Logout
        </a>
      </li>

    </ul>
  </div>
</div>

<!-- Inizio contenuto principale -->
<div class="container mt-3">
