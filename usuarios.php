<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/AgentRouter.php';
requireAdmin();

$db = Database::getConnection();
$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $nombre = trim($_POST['nombre'] ?? ''); $email = trim($_POST['email'] ?? ''); $password = $_POST['password'] ?? ''; $rol = $_POST['rol'] ?? 'agent';
        if ($nombre === '' || $email === '' || $password === '') { $message = 'Todos los campos obligatorios'; $messageType = 'error'; }
        else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            try { $db->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)")->execute([$nombre, $email, $hash, $rol]); $message = 'Usuario creado'; $messageType = 'success'; }
            catch (PDOException $e) { $message = 'Error: email ya existe'; $messageType = 'error'; }
        }
    } elseif ($action === 'toggle') { $id=(int)($_POST['user_id']??0); if($id>0){ $db->prepare("UPDATE usuarios SET activo = IF(activo=1,0,1) WHERE id=?")->execute([$id]); $message='Estado cambiado'; $messageType='success'; } }
    elseif ($action === 'toggle_disponible') { $id=(int)($_POST['user_id']??0); if($id>0){ $db->prepare("UPDATE usuarios SET disponible = IF(disponible=1,0,1) WHERE id=?")->execute([$id]); $message='Disponibilidad cambiada'; $messageType='success'; } }
    elseif ($action === 'delete') { $id=(int)($_POST['user_id']??0); if($id>0){ $db->prepare("DELETE FROM usuarios WHERE id=? AND rol!='admin'")->execute([$id]); $message='Usuario eliminado'; $messageType='success'; } }
}

$router = new AgentRouter();
$agentesActivos = $router->getAgentesActivos();
$activosIds = array_column($agentesActivos, 'id');
$usuarios = $db->query("SELECT id, nombre, email, rol, activo, disponible, ultimo_acceso, ultimo_logout, created_at FROM usuarios ORDER BY id")->fetchAll();
$user = getUsuarioActual();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Wabot</title>
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
            <a href="conocimiento.php"><span class="nav-icon">&#128196;</span> Conocimiento</a>
            <div class="nav-section">Administración</div>
            <a href="estadisticas.php"><span class="nav-icon">&#128200;</span> Estadísticas</a>
            <a href="plantillas.php"><span class="nav-icon">&#128233;</span> Plantillas</a>
            <a href="usuarios.php" class="active"><span class="nav-icon">&#128101;</span> Usuarios</a>
        </nav>
        <div class="sidebar-footer"><a href="logout.php">&#128682; Cerrar sesión</a></div>
    </aside>
    <div class="main-content">
        <div class="main-header"><h3>&#128101; Usuarios del sistema</h3></div>
        <div class="admin-content">
            <?php if ($message): ?><div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <div class="section">
                <h3>Agentes en línea</h3>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <?php if (empty($agentesActivos)): ?><span style="color:#8696a0;font-size:13px;">No hay agentes conectados. Solo IA disponible.</span>
                    <?php else: foreach ($agentesActivos as $a): ?><span style="background:#2a3942;padding:5px 12px;border-radius:20px;font-size:12px;display:flex;align-items:center;gap:6px;"><span class="status-dot ok"></span> <?= htmlspecialchars($a['nombre']) ?> (<?= $a['rol'] ?>)</span><?php endforeach; endif; ?>
                </div>
            </div>
            <div class="section">
                <h3>Crear nuevo usuario</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="form-row">
                        <input type="text" name="nombre" placeholder="Nombre" required>
                        <input type="email" name="email" placeholder="Email" required>
                        <input type="password" name="password" placeholder="Contraseña" required>
                    </div>
                    <div class="form-row">
                        <select name="rol"><option value="agent">Agente</option><option value="admin">Admin</option></select>
                        <button type="submit">Crear usuario</button>
                    </div>
                </form>
            </div>
            <div class="section">
                <h3>Usuarios existentes</h3>
                <table class="table">
                    <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th>Online</th><th>Disponible</th><th>Último acceso</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): $online = in_array($u['id'], $activosIds); ?>
                        <tr>
                            <td><?= htmlspecialchars($u['nombre']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="role-badge <?= $u['rol'] ?>"><?= $u['rol'] ?></span></td>
                            <td><span class="status-dot <?= $u['activo']?'ok':'error' ?>"></span> <?= $u['activo']?'Activo':'Inactivo' ?></td>
                            <td><span class="status-dot <?= $online?'ok':'error' ?>"></span> <?= $online?'En línea':'Desconectado' ?></td>
                            <td><form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle_disponible"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button type="submit" class="btn-small"><?= $u['disponible']?'Sí':'No' ?></button></form></td>
                            <td><?= $u['ultimo_acceso'] ? date('d/m H:i', strtotime($u['ultimo_acceso'])) : '-' ?></td>
                            <td class="actions">
                                <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button type="submit" class="btn-small"><?= $u['activo']?'Desactivar':'Activar' ?></button></form>
                                <?php if ($u['rol'] !== 'admin'): ?><form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar usuario?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button type="submit" class="btn-small btn-danger">Eliminar</button></form><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
