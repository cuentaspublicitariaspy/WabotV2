<?php
require_once __DIR__ . '/includes/Auth.php';

$user = getUsuarioActual();
if ($user !== null) {
    require_once __DIR__ . '/includes/Database.php';
    require_once __DIR__ . '/includes/ChatManager.php';
    require_once __DIR__ . '/includes/AgentRouter.php';
    require_once __DIR__ . '/includes/KnowledgeManager.php';
    require_once __DIR__ . '/includes/TemplateManager.php';
    require_once __DIR__ . '/includes/MetricsCollector.php';

    $db = Database::getConnection();
    $chatManager = new ChatManager();
    $router = new AgentRouter();
    $templateManager = new TemplateManager();

    $totalConversaciones = (int)$db->query("SELECT COUNT(*) FROM conversaciones")->fetchColumn();
    $pendientes = (int)$db->query("SELECT COUNT(*) FROM conversaciones WHERE estado = 'pendiente'")->fetchColumn();
    $totalMensajes = (int)$db->query("SELECT COUNT(*) FROM mensajes")->fetchColumn();
    $agentesActivos = $router->getAgentesActivos();
    $agentesOnline = count($agentesActivos);
    $respondidosHoy = (int)$db->query("SELECT COUNT(*) FROM metricas WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $iaRespondidos = (int)$db->query("SELECT COUNT(*) FROM metricas WHERE respondido_por_ia = 1 AND DATE(created_at) = CURDATE()")->fetchColumn();

    $activePage = 'dashboard';
    $pageTitle = 'Dashboard';
    ob_start();
    ?>
    <!-- Stats Bar -->
    <div class="px-6 py-4 border-b border-slate-100 bg-white flex items-center gap-6 flex-wrap shrink-0">
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span class="text-sm font-semibold text-slate-800"><?= $agentesOnline ?> online</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
            <span class="text-sm text-slate-600"><span class="font-semibold"><?= $pendientes ?></span> pendientes</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
            <span class="text-sm text-slate-600"><span class="font-semibold"><?= $totalConversaciones ?></span> conversaciones</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-violet-500"></span>
            <span class="text-sm text-slate-600"><span class="font-semibold"><?= $totalMensajes ?></span> mensajes</span>
        </div>
        <div class="ml-auto flex gap-2">
            <span class="text-xs bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-full font-medium">IA: <?= $iaRespondidos ?> hoy</span>
            <span class="text-xs bg-blue-50 text-blue-700 px-3 py-1.5 rounded-full font-medium">Humanos: <?= $respondidosHoy - $iaRespondidos ?> hoy</span>
        </div>
    </div>

    <!-- Cards Row -->
    <div class="px-6 py-5 grid grid-cols-1 lg:grid-cols-3 gap-5 shrink-0">
        <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100">
            <h4 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">Conversaciones</h4>
            <p class="text-4xl font-bold text-slate-900"><?= $totalConversaciones ?></p>
            <p class="text-xs text-slate-400 mt-1"><?= $pendientes ?> pendientes de respuesta</p>
        </div>
        <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100">
            <h4 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">Respondidos Hoy</h4>
            <p class="text-4xl font-bold text-slate-900"><?= $respondidosHoy ?></p>
            <p class="text-xs text-slate-400 mt-1"><?= $iaRespondidos ?> por IA · <?= $respondidosHoy - $iaRespondidos ?> por humanos</p>
        </div>
        <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100">
            <h4 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">Agentes en Línea</h4>
            <p class="text-4xl font-bold text-slate-900"><?= $agentesOnline ?></p>
            <p class="text-xs text-slate-400 mt-1"><?= $agentesOnline > 0 ? implode(', ', array_column($agentesActivos, 'nombre')) : 'Solo IA disponible' ?></p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="px-6 pb-5 shrink-0">
        <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100">
            <h4 class="text-sm font-semibold text-slate-700 mb-3">Acciones rápidas</h4>
            <div class="flex gap-3 flex-wrap">
                <a href="conversaciones.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    Conversaciones
                </a>
                <a href="conocimiento.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    Conocimiento
                </a>
                <?php if ($user['rol'] === 'admin'): ?>
                <a href="estadisticas.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    Estadísticas
                </a>
                <a href="usuarios.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    Usuarios
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent conversations -->
    <div class="flex-1 px-6 pb-5 overflow-hidden flex flex-col">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-slate-700">Conversaciones Recientes</h4>
            <a href="conversaciones.php" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">Ver todas</a>
        </div>
        <div class="flex-1 overflow-auto border border-slate-100 rounded-2xl bg-white">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs text-slate-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 font-medium">Cliente</th>
                        <th class="px-4 py-3 font-medium">Último mensaje</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3 font-medium">Asignado</th>
                        <th class="px-4 py-3 font-medium">Fecha</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php
                    $recientes = $chatManager->getConversaciones('', '', (int)$user['id'], $user['rol'] === 'admin');
                    $mostrados = 0;
                    foreach ($recientes as $c):
                        if ($mostrados >= 10) break;
                        $mostrados++;
                    ?>
                    <tr class="hover:bg-slate-50 transition cursor-pointer" onclick="window.location='conversaciones.php'">
                        <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($c['wa_name'] ?: $c['wa_phone']) ?></td>
                        <td class="px-4 py-3 text-slate-500 max-w-[200px] truncate"><?= htmlspecialchars(mb_substr($c['ultimo_mensaje'] ?? '', 0, 60)) ?></td>
                        <td class="px-4 py-3">
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium <?= $c['estado'] === 'pendiente' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' ?>">
                                <?= $c['estado'] === 'pendiente' ? 'Pendiente' : 'Respondido' ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($c['asignado_a_nombre'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-slate-400 text-xs"><?= $c['ultimo_tiempo'] ? date('d/m H:i', strtotime($c['ultimo_tiempo'])) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($mostrados === 0): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No hay conversaciones aún</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    $mainContent = ob_get_clean();
    require __DIR__ . '/includes/layout_tailwind.php';
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
    ?><!DOCTYPE html>
    <html lang="es" data-bs-theme="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Iniciar sesión - Wabot</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/css/adminlte.min.css">
        <link rel="stylesheet" href="style.css">
    </head>
    <body class="login-page">
        <div class="login-box">
            <div class="card card-outline card-primary">
                <div class="card-header text-center">
                    <h2><b>Wabot</b></h2>
                    <p class="text-secondary">WhatsApp Multiagente</p>
                </div>
                <div class="card-body">
                    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                    <form method="POST">
                        <div class="mb-3"><input type="email" name="email" class="form-control" placeholder="Email" required autofocus></div>
                        <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="Contraseña" required></div>
                        <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                    </form>
                </div>
            </div>
        </div>
    </body>
    </html><?php
}
