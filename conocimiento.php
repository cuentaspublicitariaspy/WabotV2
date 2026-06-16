<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/KnowledgeManager.php';
requireLogin();

$knowledge = new KnowledgeManager();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $_POST['content'] ?? '';
    if ($knowledge->save($content)) { $message = 'Guardado correctamente'; $messageType = 'success'; }
    else { $message = 'Error al guardar'; $messageType = 'error'; }
}

$content = $knowledge->getContent();
$stats = $knowledge->getStats();
$user = getUsuarioActual();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conocimiento - Wabot</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-brand"><h2>&#128172; Wabot</h2><span>WhatsApp Multiagente</span></div>
        <div class="sidebar-user">
            <div class="avatar"><?= strtoupper(substr($user['nombre'], 0, 1)) ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($user['nombre']) ?></div>
                <div class="user-rol"><?= $user['rol'] ?></div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php"><span class="nav-icon">&#128202;</span> Dashboard</a>
            <a href="conversaciones.php"><span class="nav-icon">&#128172;</span> Conversaciones</a>
            <a href="conocimiento.php" class="active"><span class="nav-icon">&#128196;</span> Conocimiento</a>
            <?php if ($user['rol'] === 'admin'): ?>
                <div class="nav-section">Administración</div>
                <a href="estadisticas.php"><span class="nav-icon">&#128200;</span> Estadísticas</a>
                <a href="plantillas.php"><span class="nav-icon">&#128233;</span> Plantillas</a>
                <a href="usuarios.php"><span class="nav-icon">&#128101;</span> Usuarios</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer"><a href="logout.php">&#128682; Cerrar sesión</a></div>
    </aside>
    <div class="main-content">
        <div class="main-header"><h3>&#128196; Base de conocimiento</h3></div>
        <div class="admin-content">
            <?php if ($message): ?><div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <div class="section">
                <div class="kb-info">
                    <span>&#128196; <?= $stats['lines'] ?> líneas</span>
                    <span>&#128190; <?= number_format($stats['size']) ?> bytes</span>
                </div>
                <form method="POST">
                    <textarea name="content" class="kb-editor" rows="25"><?= htmlspecialchars($content) ?></textarea>
                    <button type="submit">Guardar conocimiento</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
