<?php
require_once __DIR__ . '/includes/Auth.php';

$user = getUsuarioActual();
if ($user !== null) {
    require_once __DIR__ . '/includes/Database.php';
    require_once __DIR__ . '/includes/KnowledgeManager.php';
    require_once __DIR__ . '/includes/AgentRouter.php';

    $db = Database::getConnection();
    $totalConversaciones = $db->query("SELECT COUNT(*) FROM conversaciones")->fetchColumn();
    $pendientes = $db->query("SELECT COUNT(*) FROM conversaciones WHERE estado = 'pendiente'")->fetchColumn();
    $totalUsuarios = (int)$db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    $totalMensajes = (int)$db->query("SELECT COUNT(*) FROM mensajes")->fetchColumn();

    $router = new AgentRouter();
    $agentesActivos = $router->getAgentesActivos();
    $agentesOnline = count($agentesActivos);
    $respondidosHoy = (int)$db->query("SELECT COUNT(*) FROM metricas WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $iaRespondidos = (int)$db->query("SELECT COUNT(*) FROM metricas WHERE respondido_por_ia = 1 AND DATE(created_at) = CURDATE()")->fetchColumn();

    $dbOk = true;
    try { $db->query("SELECT 1"); } catch (Exception $e) { $dbOk = false; }

    $webhookOk = defined('WHATSAPP_VERIFY_TOKEN') && WHATSAPP_VERIFY_TOKEN !== ''
        && defined('WHATSAPP_PHONE_NUMBER_ID') && WHATSAPP_PHONE_NUMBER_ID !== '';
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dashboard - Wabot</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <aside class="sidebar">
            <div class="sidebar-brand">
                <h2>&#128172; Wabot</h2>
                <span>WhatsApp Multiagente</span>
            </div>
            <div class="sidebar-user">
                <div class="avatar"><?= strtoupper(substr($user['nombre'], 0, 1)) ?></div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($user['nombre']) ?></div>
                    <div class="user-rol"><?= $user['rol'] ?></div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="active"><span class="nav-icon">&#128202;</span> Dashboard</a>
                <a href="conversaciones.php"><span class="nav-icon">&#128172;</span> Conversaciones</a>
                <a href="conocimiento.php"><span class="nav-icon">&#128196;</span> Conocimiento</a>
                <?php if ($user['rol'] === 'admin'): ?>
                    <div class="nav-section">Administración</div>
                    <a href="estadisticas.php"><span class="nav-icon">&#128200;</span> Estadísticas</a>
                    <a href="plantillas.php"><span class="nav-icon">&#128233;</span> Plantillas</a>
                    <a href="usuarios.php"><span class="nav-icon">&#128101;</span> Usuarios</a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php">&#128682; Cerrar sesión</a>
            </div>
        </aside>
        <div class="main-content">
            <div class="main-header">
                <h3>Dashboard</h3>
                <div class="header-extras">
                    <span class="status-dot-badge"><span class="status-dot <?= $agentesOnline > 0 ? 'ok' : 'error' ?>"></span> <?= $agentesOnline ?> online</span>
                </div>
            </div>
            <div class="admin-content">
                <div class="cards">
                    <div class="card"><div class="card-value"><?= $pendientes ?></div><div class="card-label">Pendientes</div></div>
                    <div class="card"><div class="card-value"><?= $totalConversaciones ?></div><div class="card-label">Conversaciones</div></div>
                    <div class="card"><div class="card-value"><?= $totalMensajes ?></div><div class="card-label">Mensajes totales</div></div>
                    <div class="card"><div class="card-value"><?= $agentesOnline ?></div><div class="card-label">Agentes online</div></div>
                </div>
                <div class="cards">
                    <div class="card card-secondary"><div class="card-value"><?= $respondidosHoy ?></div><div class="card-label">Respondidos hoy</div></div>
                    <div class="card card-secondary"><div class="card-value"><?= $iaRespondidos ?></div><div class="card-label">Por IA hoy</div></div>
                    <div class="card card-secondary"><div class="card-value"><?= $totalUsuarios ?></div><div class="card-label">Agentes registrados</div></div>
                    <div class="card card-secondary"><div class="card-value"><?= $respondidosHoy - $iaRespondidos ?></div><div class="card-label">Por humanos hoy</div></div>
                </div>
                <div class="status-row">
                    <div class="status-item"><span class="status-dot <?= $dbOk ? 'ok' : 'error' ?>"></span> BD: <?= $dbOk ? 'Conectada' : 'Error' ?></div>
                    <div class="status-item"><span class="status-dot <?= $webhookOk ? 'ok' : 'error' ?>"></span> Webhook: <?= $webhookOk ? 'Configurado' : 'Sin configurar' ?></div>
                    <div class="status-item"><span class="status-dot <?= $agentesOnline > 0 ? 'ok' : 'error' ?>"></span> Agentes: <?= $agentesOnline > 0 ? implode(', ', array_column($agentesActivos, 'nombre')) : 'Solo IA' ?></div>
                </div>
                <div class="section">
                    <h3>Acciones rápidas</h3>
                    <div class="quick-actions">
                        <a href="conversaciones.php" class="action-btn">&#128172; Ver conversaciones</a>
                        <a href="conocimiento.php" class="action-btn">&#128196; Editar conocimiento</a>
                        <?php if ($user['rol'] === 'admin'): ?>
                            <a href="estadisticas.php" class="action-btn">&#128200; Estadísticas</a>
                            <a href="plantillas.php" class="action-btn">&#128233; Plantillas</a>
                            <a href="usuarios.php" class="action-btn">&#128101; Gestionar usuarios</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <script>
            setInterval(function() { fetch('ajax/session.php', { method: 'POST' }).catch(() => {}); }, 30000);
        </script>
    </body>
    </html>
<?php
} else {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $result = login($email, $password);
        if ($result['success']) {
            header('Location: index.php');
            exit;
        }
        $error = $result['error'];
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Iniciar sesión - Wabot</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body class="login-body">
        <div class="login-container">
            <div class="login-box">
                <div class="login-icon">&#128172;</div>
                <h1>Wabot Panel</h1>
                <p class="login-subtitle">WhatsApp Multiagente</p>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <label>Email</label>
                    <input type="email" name="email" required autofocus>
                    <label>Contraseña</label>
                    <input type="password" name="password" required>
                    <button type="submit">Ingresar</button>
                </form>
            </div>
        </div>
    </body>
    </html>
<?php
}
