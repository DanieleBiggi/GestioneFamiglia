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
        $stmt = $conn->prepare('SELECT f.id_famiglia, f.nome_famiglia FROM famiglie f JOIN utenti2famiglie u2f ON f.id_famiglia = u2f.id_famiglia WHERE u2f.id_utente = ?');
        $stmt->bind_param('i', $_SESSION['utente_id']);
        $stmt->execute();
        $resFam = $stmt->get_result();
        while ($fam = $resFam->fetch_assoc()): ?>
          <option value="<?= (int)$fam['id_famiglia'] ?>" <?= (isset($_SESSION['id_famiglia_gestione']) && $_SESSION['id_famiglia_gestione'] == $fam['id_famiglia']) ? 'selected' : '' ?>><?= htmlspecialchars($fam['nome_famiglia']) ?></option>
        <?php endwhile; $stmt->close(); ?>
      </select>
    </form>
    <?php endif; ?>
    <ul class="list-unstyled">
      <li class="mb-3">
        <a href="/Gestionale25/index.php" class="btn btn-outline-light w-100 text-start">
          🏠 Home
        </a>
      </li>
      <?php if (isset($_SESSION['id_famiglia_gestione']) && $_SESSION['id_famiglia_gestione'] == 1): ?>
      <li class="mb-3">
        <a href="/Gestionale25/etichette_lista.php" class="btn btn-outline-light w-100 text-start">
          🏷️ Etichette
        </a>
      </li>
      <?php endif; ?>
      <li class="mb-3">
        <a href="/Gestionale25/credito_utente.php" class="btn btn-outline-light w-100 text-start">
          💰 Saldo personale
        </a>
      </li>
      <li class="mb-3">
        <a href="/Gestionale25/password.php" class="btn btn-outline-light w-100 text-start">
          🔐 Siti e password
        </a>
      </li>
      <?php if (isset($_SESSION['utente_id']) && $_SESSION['utente_id'] == 1): ?>
      <li class="mb-3">
        <a href="/Gestionale25/storia.php" class="btn btn-outline-light w-100 text-start">
          📜 Storia
        </a>
      </li>
      <?php endif; ?>
      <li class="mb-3">
        <div class="dropdown w-100">
          <button class="btn btn-outline-light w-100 text-start dropdown-toggle"
                  data-bs-toggle="dropdown" aria-expanded="false">
            🔐 Sicurezza
          </button>
          <ul class="dropdown-menu dropdown-menu-dark w-100">
            <li><a class="dropdown-item text-white" href="/Gestionale25/change_password.php">🔑 Cambia Password</a></li>
            <li><a class="dropdown-item text-white" href="/Gestionale25/setup_passcode.php">🔒 Imposta Passcode</a></li>
            <li><a class="dropdown-item text-white" href="/Gestionale">Torna al vecchio</a></li>
          </ul>
        </div>
      </li>
      <li class="mb-3">
        <div class="dropdown w-100">
          <button class="btn btn-outline-light w-100 text-start dropdown-toggle"
                  data-bs-toggle="dropdown" aria-expanded="false">
            🗃️ Tabelle
          </button>
          <ul class="dropdown-menu dropdown-menu-dark w-100">
            <li><h6 class="dropdown-header">Bilancio</h6></li>
            <li><a class="dropdown-item text-white" href="/Gestionale25/pages/table_manager.php?table=bilancio_descrizione2id">Descrizioni</a></li>
            <li><a class="dropdown-item text-white" href="/Gestionale25/pages/table_manager.php?table=bilancio_entrate">Entrate</a></li>
            <li><a class="dropdown-item text-white" href="/Gestionale25/pages/table_manager.php?table=bilancio_gruppi_categorie">Gruppi categorie</a></li>
            <li><a class="dropdown-item text-white" href="/Gestionale25/pages/table_manager.php?table=bilancio_gruppi_transazione">Gruppi transazione</a></li>
            <li><a class="dropdown-item text-white" href="/Gestionale25/pages/table_manager.php?table=bilancio_uscite">Uscite</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header">Sicurezza</h6></li>
            <li><a class="dropdown-item text-white" href="/Gestionale25/pages/table_manager.php?table=codici_2fa">Codici 2FA</a></li>
            <li><a class="dropdown-item text-white" href="/Gestionale25/pages/table_manager.php?table=dispositivi_riconosciuti">Dispositivi riconosciuti</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header">Utenti</h6></li>
            <li><a class="dropdown-item text-white" href="/Gestionale25/pages/table_manager.php?table=famiglie">Famiglie</a></li>
            <li><a class="dropdown-item text-white" href="/Gestionale25/pages/table_manager.php?table=userlevels">User Levels</a></li>
            <li><a class="dropdown-item text-white" href="/Gestionale25/pages/table_manager.php?table=utenti">Utenti</a></li>
            <li><a class="dropdown-item text-white" href="/Gestionale25/pages/table_manager.php?table=utenti2famiglie">Utenti-Famiglie</a></li>
            <li><a class="dropdown-item text-white" href="/Gestionale25/pages/table_manager.php?table=utenti2ip">Utenti-IP</a></li>
          </ul>
        </div>
      </li>
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
