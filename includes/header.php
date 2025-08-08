<?php require_once __DIR__ . '/permissions.php'; ?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gestione Famiglia 2.0</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/Gestionale25/assets/style.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-dark bg-dark px-3 d-flex justify-content-between align-items-center">
  <button class="btn btn-dark border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMenu">
    <i class="bi bi-list fs-3"></i>
  </button>
  <a href="index.php" class="navbar-brand mb-0 h1">Gestione Famiglia 2.0</a>
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
          🏠 Home
        </a>
      </li>
      <?php endif; ?>
      <?php if (isset($_SESSION['id_famiglia_gestione']) && $_SESSION['id_famiglia_gestione'] == 1 && has_permission($conn, 'page:etichette_lista.php', 'view')): ?>
      <li class="mb-3">
        <a href="/Gestionale25/etichette_lista.php" class="btn btn-outline-light w-100 text-start">
          🏷️ Etichette
        </a>
      </li>
      <?php endif; ?>
      <?php if (has_permission($conn, 'page:credito_utente.php', 'view')): ?>
      <li class="mb-3">
        <a href="/Gestionale25/credito_utente.php" class="btn btn-outline-light w-100 text-start">
          💰 Saldo personale
        </a>
      </li>
      <?php endif; ?>
      <?php if (has_permission($conn, 'page:password.php', 'view')): ?>
      <li class="mb-3">
        <a href="/Gestionale25/password.php" class="btn btn-outline-light w-100 text-start">
          🔐 Siti e password
        </a>
      </li>
      <?php endif; ?>
      <?php if (has_permission($conn, 'page:storia.php', 'view')): ?>
      <li class="mb-3">
        <a href="/Gestionale25/storia.php" class="btn btn-outline-light w-100 text-start">
          📜 Storia
        </a>
      </li>
      <?php endif; ?>
      <?php if (has_permission($conn, 'page:userlevel_permissions.php', 'view')): ?>
      <li class="mb-3">
        <a href="/Gestionale25/userlevel_permissions.php" class="btn btn-outline-light w-100 text-start">
          🛡️ Permessi Userlevel
        </a>
      </li>
      <?php endif; ?>
      <?php if (has_permission($conn, 'page:gestione_utenti.php', 'view')): ?>
      <li class="mb-3">
        <a href="/Gestionale25/gestione_utenti.php" class="btn btn-outline-light w-100 text-start">
          👥 Gestione Utenti
        </a>
      </li>
      <?php endif; ?>
      <?php $showSecurity = has_permission($conn, 'page:change_password.php', 'view') || has_permission($conn, 'page:setup_passcode.php', 'view');
      if ($showSecurity): ?>
      <li class="mb-3">
        <div class="dropdown w-100">
          <button class="btn btn-outline-light w-100 text-start dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            🔐 Sicurezza
          </button>
          <ul class="dropdown-menu dropdown-menu-dark w-100">
            <?php if (has_permission($conn, 'page:change_password.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/change_password.php">🔑 Cambia Password</a></li>
            <?php endif; ?>
            <?php if (has_permission($conn, 'page:setup_passcode.php', 'view')): ?>
            <li><a class="dropdown-item text-white" href="/Gestionale25/setup_passcode.php">🔒 Imposta Passcode</a></li>
            <?php endif; ?>
            <li><a class="dropdown-item text-white" href="/Gestionale">Torna al vecchio</a></li>
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
            🗃️ Tabelle
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
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php if ($canImpersonate): ?>
      <li class="mb-3">
        <a href="/Gestionale25/impersonate.php" class="btn btn-outline-light w-100 text-start">
          👤 Impersona utente
        </a>
      </li>
      <?php endif; ?>
      <?php if (isset($_SESSION['impersonator_id'])): ?>
      <li class="mb-3">
        <a href="/Gestionale25/stop_impersonate.php" class="btn btn-outline-warning w-100 text-start">
          ⏪ Torna al tuo account
        </a>
      </li>
      <?php endif; ?>
      <li>
        <a href="/Gestionale25/logout.php" class="btn btn-outline-danger w-100 text-start">
          ⎋ Logout
        </a>
      </li>

    </ul>
  </div>
</div>

<!-- Inizio contenuto principale -->
<div class="container mt-3">
