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
                    $recientes = $chatManager->getConversaciones('', '', (int)$user['id'], in_array($user['rol'], ['super_admin', 'admin']));
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
} elseif (DB_NAME === '') {
    header('Location: setup.php');
    exit;
} else {
    // Show landing page
    ?>
    <!DOCTYPE html>
    <html lang="es" class="scroll-smooth">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Wabot - Plataforma WhatsApp Multiagente y Chatbot IA</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ['Outfit', 'sans-serif'],
                        }
                    }
                }
            }
        </script>
        <style>
            .gradient-bg {
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            }
            .gradient-text {
                background: linear-gradient(90deg, #10b981 0%, #3b82f6 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
        </style>
    </head>
    <body class="bg-slate-900 text-slate-100 font-sans antialiased overflow-x-hidden">

        <!-- HEADER / NAVIGATION -->
        <header class="fixed top-0 left-0 w-full z-50 bg-slate-900/80 backdrop-blur-md border-b border-slate-800">
            <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
                <a href="#" class="flex items-center gap-2 text-white font-bold text-2xl">
                    <span class="w-9 h-9 bg-emerald-500 rounded-xl flex items-center justify-center text-white text-base shadow-lg shadow-emerald-500/20">W</span>
                    <span>Wabot</span>
                </a>
                <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-400">
                    <a href="#features" class="hover:text-white transition">Características</a>
                    <a href="#ia" class="hover:text-white transition">Inteligencia Artificial</a>
                    <a href="#stats" class="hover:text-white transition">Estadísticas</a>
                </nav>
                <div class="flex items-center gap-4">
                    <a href="login.php" class="text-sm font-semibold text-white hover:text-emerald-400 transition">Iniciar Sesión</a>
                    <a href="login.php" class="hidden sm:inline-flex px-5 py-2.5 bg-emerald-500 text-slate-950 text-sm font-semibold rounded-xl hover:bg-emerald-400 transition shadow-lg shadow-emerald-500/10">Empezar gratis</a>
                </div>
            </div>
        </header>

        <!-- HERO SECTION -->
        <section class="relative pt-32 pb-24 md:pt-40 md:pb-32 overflow-hidden gradient-bg">
            <div class="max-w-7xl mx-auto px-6 relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                <div class="lg:col-span-7 text-center lg:text-left space-y-6">
                    <span class="inline-flex px-3.5 py-1.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">WhatsApp Business API</span>
                    <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold tracking-tight text-white leading-tight">
                        Multiplica tus ventas en WhatsApp con <span class="gradient-text">Agentes e IA</span>
                    </h1>
                    <p class="text-base sm:text-lg md:text-xl text-slate-400 max-w-2xl mx-auto lg:mx-0">
                        Conecta múltiples agentes de soporte a un solo número de WhatsApp. Automatiza respuestas las 24 horas con Inteligencia Artificial entrenada con la base de conocimientos de tu empresa.
                    </p>
                    <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4 pt-4">
                        <a href="login.php" class="w-full sm:w-auto text-center px-8 py-4 bg-emerald-500 text-slate-950 font-bold rounded-2xl hover:bg-emerald-400 transition shadow-xl shadow-emerald-500/20">
                            Iniciar prueba gratuita
                        </a>
                        <a href="#features" class="w-full sm:w-auto text-center px-8 py-4 bg-slate-800 text-white font-bold rounded-2xl hover:bg-slate-700 transition border border-slate-700">
                            Ver características
                        </a>
                    </div>
                </div>
                <div class="lg:col-span-5 relative">
                    <!-- Visual Mockup -->
                    <div class="relative mx-auto max-w-[380px] lg:max-w-none bg-slate-800 rounded-3xl p-3 border border-slate-700 shadow-2xl shadow-emerald-500/5 aspect-[4/3] flex flex-col overflow-hidden">
                        <div class="flex items-center justify-between border-b border-slate-700 pb-3 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-red-500"></span>
                                <span class="w-3 h-3 rounded-full bg-yellow-500"></span>
                                <span class="w-3 h-3 rounded-full bg-green-500"></span>
                            </div>
                            <span class="text-xs text-slate-500">wabot.panel</span>
                        </div>
                        <div class="flex-1 bg-slate-950 rounded-2xl p-4 flex flex-col justify-between text-xs space-y-4">
                            <div class="space-y-2">
                                <div class="bg-slate-800 p-3 rounded-xl border border-slate-700 max-w-[80%]">
                                    <p class="text-slate-300">Hola, ¿tienen disponibilidad del producto?</p>
                                    <span class="text-[10px] text-slate-500 mt-1 block">Cliente - 18:10</span>
                                </div>
                                <div class="bg-emerald-500/10 text-emerald-300 p-3 rounded-xl border border-emerald-500/20 max-w-[80%] ml-auto">
                                    <p>🤖 <b>Asistente IA:</b> ¡Hola! Sí, tenemos stock disponible. ¿Te gustaría realizar el pedido?</p>
                                    <span class="text-[10px] text-emerald-500 mt-1 block">Respondido por IA - 18:10</span>
                                </div>
                            </div>
                            <div class="bg-slate-900 border border-slate-800 p-3 rounded-xl flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                    <span class="text-slate-400 font-medium">WhatsApp Conectado</span>
                                </div>
                                <span class="bg-emerald-500/10 text-emerald-400 px-2 py-0.5 rounded text-[10px] font-semibold">API Activa</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Decorative light sphere -->
            <div class="absolute top-1/4 right-0 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl -z-10"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl -z-10"></div>
        </section>

        <!-- FEATURES SECTION -->
        <section id="features" class="py-24 border-t border-slate-800 bg-slate-950">
            <div class="max-w-7xl mx-auto px-6">
                <div class="text-center max-w-3xl mx-auto space-y-4 mb-16">
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Todo lo que necesitas para tu soporte en WhatsApp</h2>
                    <p class="text-slate-400">Una suite completa diseñada para optimizar los tiempos de respuesta de tu equipo comercial.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <!-- Feature 1 -->
                    <div class="bg-slate-900 border border-slate-800 p-8 rounded-3xl hover:border-emerald-500/30 transition group">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-6 text-xl group-hover:bg-emerald-500 group-hover:text-slate-950 transition">
                            <i class="bi bi-chat-left-dots"></i>
                        </div>
                        <h3 class="text-lg font-bold text-white mb-2">Bandeja Multiagente</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">Conecta a todo tu equipo a una misma línea de WhatsApp. Asigna y transfiere chats fácilmente.</p>
                    </div>
                    <!-- Feature 2 -->
                    <div class="bg-slate-900 border border-slate-800 p-8 rounded-3xl hover:border-emerald-500/30 transition group">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-6 text-xl group-hover:bg-emerald-500 group-hover:text-slate-950 transition">
                            <i class="bi bi-robot"></i>
                        </div>
                        <h3 class="text-lg font-bold text-white mb-2">Respuestas con IA</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">Automatiza la atención con Inteligencia Artificial capaz de comprender el contexto de tus documentos y responder al instante.</p>
                    </div>
                    <!-- Feature 3 -->
                    <div class="bg-slate-900 border border-slate-800 p-8 rounded-3xl hover:border-emerald-500/30 transition group">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-6 text-xl group-hover:bg-emerald-500 group-hover:text-slate-950 transition">
                            <i class="bi bi-bar-chart-line"></i>
                        </div>
                        <h3 class="text-lg font-bold text-white mb-2">Estadísticas Clave</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">Mide el rendimiento de tus agentes humanos y de la IA con métricas de mensajes diarios y tiempos de resolución.</p>
                    </div>
                    <!-- Feature 4 -->
                    <div class="bg-slate-900 border border-slate-800 p-8 rounded-3xl hover:border-emerald-500/30 transition group">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-6 text-xl group-hover:bg-emerald-500 group-hover:text-slate-950 transition">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h3 class="text-lg font-bold text-white mb-2">Widget Web</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">Integrá un widget de atención al cliente en tu sitio web con configuración personalizada de colores y mensajes de bienvenida.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="bg-slate-950 border-t border-slate-800 py-12">
            <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-2 text-white font-bold text-xl">
                    <span class="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center text-white text-sm">W</span>
                    <span>Wabot</span>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-6 text-sm text-slate-500">
                    <a href="privacidad.html" class="hover:text-slate-300 transition">Política de Privacidad</a>
                    <a href="terminos.html" class="hover:text-slate-300 transition">Condiciones de Servicio</a>
                    <span class="text-slate-700">|</span>
                    <span>© <?= date('Y') ?> Wabot. Todos los derechos reservados.</span>
                </div>
            </div>
        </footer>

    </body>
    </html>
    <?php
}
