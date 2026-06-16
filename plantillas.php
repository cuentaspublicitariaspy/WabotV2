<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/TemplateManager.php';
requireAdmin();

$tm = new TemplateManager();
$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $nombre = trim($_POST['nombre'] ?? '');
        $idioma = trim($_POST['idioma'] ?? 'es');
        $contenido = trim($_POST['contenido'] ?? '');
        $parametros = (int)($_POST['parametros'] ?? 0);
        if ($nombre === '' || $contenido === '') { $message = 'Nombre y contenido obligatorios'; $messageType = 'error'; }
        else { $tm->crear($nombre, $idioma, $contenido, $parametros); $message = 'Plantilla creada'; $messageType = 'success'; }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $idioma = trim($_POST['idioma'] ?? 'es');
        $contenido = trim($_POST['contenido'] ?? '');
        $parametros = (int)($_POST['parametros'] ?? 0);
        $activo = !empty($_POST['activo']);
        if ($id > 0) { $tm->actualizar($id, $nombre, $idioma, $contenido, $parametros, $activo); $message = 'Plantilla actualizada'; $messageType = 'success'; }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) { $tm->eliminar($id); $message = 'Plantilla eliminada'; $messageType = 'success'; }
    }
}
$plantillas = $tm->getTodas();
$user = getUsuarioActual();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plantillas - Wabot</title>
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
            <a href="plantillas.php" class="active"><span class="nav-icon">&#128233;</span> Plantillas</a>
            <a href="usuarios.php"><span class="nav-icon">&#128101;</span> Usuarios</a>
        </nav>
        <div class="sidebar-footer"><a href="logout.php">&#128682; Cerrar sesión</a></div>
    </aside>
    <div class="main-content">
        <div class="main-header"><h3>&#128233; Plantillas de mensaje</h3></div>
        <div class="admin-content">
            <?php if ($message): ?><div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <div class="section">
                <h3>Nueva plantilla</h3>
                <form method="POST" class="template-form">
                    <input type="hidden" name="action" value="create">
                    <div class="form-row">
                        <input type="text" name="nombre" placeholder="Nombre (ej: recordatorio_cita)" required>
                        <select name="idioma"><option value="es">Español</option><option value="en">Inglés</option><option value="pt">Portugués</option></select>
                        <input type="number" name="parametros" placeholder="Parámetros (0-5)" min="0" max="5" value="0">
                    </div>
                    <textarea name="contenido" class="kb-editor" rows="4" placeholder="Contenido. Usá {{1}}, {{2}} para parámetros..." required></textarea>
                    <button type="submit">Crear plantilla</button>
                </form>
            </div>
            <div class="section">
                <h3>Plantillas existentes</h3>
                <p style="color:#8696a0;font-size:12px;margin-bottom:12px;">Deben estar aprobadas por Meta para usarse fuera de la ventana de 24hs.</p>
                <table class="table">
                    <thead><tr><th>Nombre</th><th>Idioma</th><th>Contenido</th><th>Params</th><th>Activo</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php if (empty($plantillas)): ?><tr><td colspan="6" style="text-align:center;color:#8696a0;">No hay plantillas aún</td></tr><?php endif; ?>
                        <?php foreach ($plantillas as $p): ?>
                        <tr>
                            <form method="POST">
                                <input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <td><input type="text" name="nombre" value="<?= htmlspecialchars($p['nombre']) ?>" class="inline-input"></td>
                                <td><select name="idioma" class="inline-input"><option value="es" <?= $p['idioma']==='es'?'selected':'' ?>>Español</option><option value="en" <?= $p['idioma']==='en'?'selected':'' ?>>Inglés</option><option value="pt" <?= $p['idioma']==='pt'?'selected':'' ?>>Portugués</option></select></td>
                                <td><input type="text" name="contenido" value="<?= htmlspecialchars($p['contenido']) ?>" class="inline-input" style="width:200px;"></td>
                                <td><input type="number" name="parametros" value="<?= $p['parametros'] ?>" min="0" max="5" class="inline-input" style="width:60px;"></td>
                                <td><input type="checkbox" name="activo" value="1" <?= $p['activo']?'checked':'' ?>></td>
                                <td class="actions"><button type="submit" class="btn-small">Guardar</button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar plantilla?')">
                                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn-small btn-danger">Eliminar</button>
                            </form></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
