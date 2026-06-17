<?php
if (!isset($user) || $user === null) return;
$activePage = $activePage ?? '';
$pageTitle = $pageTitle ?? 'Wabot';
?><!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?> - Wabot</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/css/adminlte.min.css">
<link rel="stylesheet" href="style.css">
<?= $extraHead ?? '' ?>
</head>
<body class="hold-transition<?= $bodyClass ?? '' ?>">
<div class="app-wrapper">

<nav class="app-header navbar navbar-expand navbar" data-bs-theme="dark">
  <ul class="navbar-nav">
    <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="bi bi-list"></i></a></li>
  </ul>
  <ul class="navbar-nav ms-auto">
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><?= htmlspecialchars($user['nombre']) ?></a>
      <div class="dropdown-menu dropdown-menu-end">
        <span class="dropdown-item-text text-secondary"><?= htmlspecialchars($user['rol']) ?></span>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a>
      </div>
    </li>
  </ul>
</nav>

<aside class="app-sidebar" data-bs-theme="dark">
  <div class="sidebar-brand">
    <a href="index.php" class="brand-link"><span class="brand-text fw-light">Wabot</span></a>
  </div>
  <nav class="mt-2">
    <ul class="nav sidebar-menu flex-column">
      <li class="nav-item"><a href="index.php" class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>"><i class="nav-icon bi bi-speedometer2"></i><p>Dashboard</p></a></li>
      <li class="nav-item"><a href="conversaciones.php" class="nav-link <?= $activePage === 'conversaciones' ? 'active' : '' ?>"><i class="nav-icon bi bi-chat-dots"></i><p>Conversaciones</p></a></li>
      <li class="nav-item"><a href="conocimiento.php" class="nav-link <?= $activePage === 'conocimiento' ? 'active' : '' ?>"><i class="nav-icon bi bi-book"></i><p>Conocimiento</p></a></li>
      <?php if ($user['rol'] === 'admin'): ?>
      <li class="nav-header">Administración</li>
      <li class="nav-item"><a href="estadisticas.php" class="nav-link <?= $activePage === 'estadisticas' ? 'active' : '' ?>"><i class="nav-icon bi bi-bar-chart"></i><p>Estadísticas</p></a></li>
      <li class="nav-item"><a href="plantillas.php" class="nav-link <?= $activePage === 'plantillas' ? 'active' : '' ?>"><i class="nav-icon bi bi-envelope"></i><p>Plantillas</p></a></li>
      <li class="nav-item"><a href="usuarios.php" class="nav-link <?= $activePage === 'usuarios' ? 'active' : '' ?>"><i class="nav-icon bi bi-people"></i><p>Usuarios</p></a></li>
      <?php endif; ?>
    </ul>
  </nav>
</aside>

<main class="app-main<?= isset($fullHeight) && $fullHeight ? ' overflow-hidden' : '' ?>">
  <div class="<?= isset($fullHeight) && $fullHeight ? 'h-100 d-flex flex-column' : 'container-fluid p-3' ?>">
    <?= $mainContent ?>
  </div>
</main>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/js/adminlte.min.js"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
