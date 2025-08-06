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
  <span class="navbar-brand mb-0 h1">Gestione Famiglia 2.0</span>
</nav>

<!-- Offcanvas Menù Laterale -->
<div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="offcanvasMenu" aria-labelledby="offcanvasMenuLabel">
  <div class="offcanvas-header border-bottom border-secondary">
    <h5 class="offcanvas-title" id="offcanvasMenuLabel">Menù</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Chiudi"></button>
  </div>
  <div class="offcanvas-body">
    <ul class="list-unstyled">
      <li class="mb-3">
        <a href="/Gestionale25/index.php" class="btn btn-outline-light w-100 text-start">
          🏠 Home
        </a>
      </li>
      <li class="mb-3">
        <a href="/Gestionale25/etichette_lista.php" class="btn btn-outline-light w-100 text-start">
          🏷️ Etichette
        </a>
      </li>
      <li class="mb-3">
        <a href="/Gestionale25/change_password.php" class="btn btn-outline-light w-100 text-start">
          🔑 Cambia Password
        </a>
      </li>
      <li class="mb-3">
        <a href="/Gestionale25/setup_passcode.php" class="btn btn-outline-light w-100 text-start">
          🔒 Imposta Passcode
        </a>
      </li>
      <li class="mb-3">
        <a href="/Gestionale" class="btn btn-outline-light w-100 text-start">
          Torna al vecchio
        </a>
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
