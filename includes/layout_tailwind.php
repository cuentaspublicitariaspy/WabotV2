<?php
if (!isset($user) || $user === null) return;
require_once __DIR__ . '/Database.php';
$activePage = $activePage ?? '';
$pageTitle = $pageTitle ?? 'Wabot';
$userName = htmlspecialchars($user['nombre']);
$userRol = htmlspecialchars($user['rol']);
$userEmail = '';
$userWhatsapp = '';
$userFoto = '';
try {
    $stmt = Database::getConnection()->prepare("SELECT email, whatsapp, foto_perfil FROM usuarios WHERE id = ?");
    $stmt->execute([(int)$user['id']]);
    $row = $stmt->fetch();
    $userEmail = htmlspecialchars($row['email'] ?? '');
    $userWhatsapp = htmlspecialchars($row['whatsapp'] ?? '');
    $userFoto = htmlspecialchars($row['foto_perfil'] ?? '');
} catch (Exception $e) {}
$cacheBuster = '?t=' . time();
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$avatarUrl = $userFoto ? $basePath . '/' . ltrim($userFoto, '/') . $cacheBuster : "https://ui-avatars.com/api/?name=" . urlencode($user['nombre']) . "&background=10b981&color=fff";
$navLinks = [
    'dashboard' => ['label' => 'Dashboard', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>', 'href' => 'index.php'],
    'conversaciones' => ['label' => 'Conversaciones', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>', 'href' => 'conversaciones.php'],
    'prospectos' => ['label' => 'Prospectos', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"></path></svg>', 'href' => 'prospectos.php'],
    'agenda' => ['label' => 'Agenda', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"></path></svg>', 'href' => 'agenda.php'],
];
$adminNavLinks = [
    'estadisticas' => ['label' => 'Estadísticas', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>', 'href' => 'estadisticas.php'],

    'pruebas' => ['label' => 'Pruebas', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3v6.75l-4.5 7.794A3 3 0 007.848 22h8.304a3 3 0 002.598-4.456l-4.5-7.794V3m-4.5 0h4.5M8 14h8"></path></svg>', 'href' => 'pruebas.php'],
    'usuarios' => ['label' => 'Usuarios', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>', 'href' => 'usuarios.php'],
];
$esAdminOrSuper = in_array($user['rol'], ['super_admin', 'admin']);
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Wabot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        ::-webkit-scrollbar { width: 8px; height:8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; border-radius:4px; }
        ::-webkit-scrollbar-thumb { background: #94a3b8; border-radius:4px; }
        ::-webkit-scrollbar-thumb:hover { background: #64748b; }
        .modal-overlay { background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(2px); }
        html, body { max-width: 100%; overflow-x: hidden; }
        .wc-mobile-overlay { background: rgba(15,23,42,.55); backdrop-filter: blur(2px); }
        @media (max-width: 767px) {
            .wc-mobile-scroll { -webkit-overflow-scrolling: touch; }
            .table-responsive { border: 0; }
            .table-responsive > .table { min-width: 640px; }
            input, select, textarea, button { max-width: 100%; }
        }
        <?= $extraStyle ?? '' ?>
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-900 font-sans overflow-hidden">

    <div class="flex h-full">
        <!-- SIDEBAR -->
        <div id="wc-mobile-overlay" class="hidden fixed inset-0 z-40 wc-mobile-overlay md:hidden"></div>
        <aside id="wc-sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-900 text-slate-300 flex flex-col py-6 shadow-xl transform -translate-x-full transition-transform duration-200 ease-out md:static md:w-64 md:translate-x-0 md:shrink-0">
            <div class="px-6 mb-8">
                <a href="index.php" class="text-white font-bold text-xl flex items-center gap-2">
                    <span class="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center text-white text-sm shadow-lg shadow-emerald-900/50">W</span>
                    <span>Wabot</span>
                </a>
            </div>

            <nav class="flex-1 px-4 space-y-8 overflow-y-auto">
                <div class="space-y-1">
                    <?php foreach ($navLinks as $key => $link): ?>
                    <a href="<?= $link['href'] ?>" data-mobile-close class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= $activePage === $key ? 'bg-emerald-600 text-white font-medium shadow-lg shadow-emerald-900/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                        <?= $link['icon'] ?>
                        <span><?= $link['label'] ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($esAdminOrSuper): ?>
                <div class="space-y-1">
                    <h3 class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Administración</h3>
                    <?php foreach ($adminNavLinks as $key => $link): ?>
                    <a href="<?= $link['href'] ?>" data-mobile-close class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= $activePage === $key ? 'bg-emerald-600 text-white font-medium shadow-lg shadow-emerald-900/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                        <?= $link['icon'] ?>
                        <span><?= $link['label'] ?></span>
                    </a>
                    <?php endforeach; ?>
                    <?php if (in_array($user['rol'], ['super_admin', 'admin'])): ?>
                    <a href="settings.php" data-mobile-close class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= $activePage === 'settings' ? 'bg-emerald-600 text-white font-medium shadow-lg shadow-emerald-900/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                        <span>Configuración</span>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </nav>

            <div class="px-4 border-t border-slate-800 pt-4 space-y-1">
                <div id="profile-area" class="flex items-center gap-3 px-4 py-3 rounded-lg cursor-pointer hover:bg-slate-800 transition">
                    <img id="sidebar-avatar" src="<?= $avatarUrl ?>" class="w-10 h-10 rounded-full object-cover" alt="Avatar">
                    <div>
                        <p class="text-sm text-white font-medium"><?= $userName ?></p>
                        <p class="text-xs text-emerald-400">● Online</p>
                    </div>
                </div>
                <a href="logout.php" data-mobile-close class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all text-slate-400 hover:bg-slate-800 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="flex-1 min-w-0 w-full bg-white overflow-auto wc-mobile-scroll <?= isset($fullHeight) && $fullHeight ? 'flex flex-col overflow-hidden' : 'p-4 md:p-6' ?>">
            <div class="sticky top-0 z-30 -mx-4 -mt-4 mb-4 flex items-center justify-between border-b border-slate-100 bg-white/95 px-4 py-3 backdrop-blur md:hidden">
                <a href="index.php" class="flex items-center gap-2 font-bold text-slate-900"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500 text-sm text-white">W</span>Wabot</a>
                <button id="wc-mobile-menu" type="button" aria-label="Abrir menú" aria-expanded="false" class="rounded-xl border border-slate-200 p-2.5 text-slate-700"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            </div>
            <?= $mainContent ?>
        </main>
    </div>

    <!-- Profile Modal -->
    <div id="profile-modal" class="fixed inset-0 z-50 hidden modal-overlay flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-800">Mi Perfil</h3>
                <button onclick="closeProfile()" class="text-slate-400 hover:text-slate-600 transition text-xl leading-none">&times;</button>
            </div>
            <form id="profile-form" class="p-6 space-y-5">
                <div class="flex items-center gap-5">
                    <div class="relative">
                        <img id="profile-preview" src="<?= $avatarUrl ?>" class="w-20 h-20 rounded-full border-4 border-slate-100 object-cover">
                        <label for="foto_input" class="absolute bottom-0 right-0 w-7 h-7 bg-emerald-500 rounded-full flex items-center justify-center cursor-pointer shadow-md hover:bg-emerald-600 transition">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </label>
                        <input type="hidden" name="foto_actual" value="<?= $userFoto ?>">
                        <input type="file" id="foto_input" name="foto_perfil" accept="image/*" class="hidden" onchange="previewFoto(event)">
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800 text-lg"><?= $userName ?></p>
                        <p class="text-sm text-slate-500"><?= $userEmail ?></p>
                        <span class="inline-block mt-1 text-xs font-medium px-2 py-0.5 rounded-full <?= $userRol === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>"><?= ucfirst($userRol) ?></span>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nombre</label>
                        <input type="text" name="nombre" value="<?= $userName ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input type="email" name="email" value="<?= $userEmail ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">WhatsApp</label>
                        <input type="text" name="whatsapp" value="<?= $userWhatsapp ?>" placeholder="+595981234567" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    </div>
                </div>
                <div id="profile-msg" class="hidden text-sm font-medium px-3 py-2 rounded-lg"></div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeProfile()" class="px-4 py-2 text-slate-600 font-medium hover:bg-slate-100 rounded-lg transition text-sm">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition text-sm">Guardar cambios</button>
                </div>
            </form>
            <hr class="border-slate-200 mx-6">
            <form id="password-form" class="p-6 space-y-4">
                <h4 class="text-sm font-bold text-slate-800">Cambiar contraseña</h4>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Contraseña actual</label>
                    <input type="password" name="password_actual" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nueva contraseña</label>
                    <input type="password" name="password_nueva" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Confirmar nueva contraseña</label>
                    <input type="password" name="password_confirmar" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
                <div id="password-msg" class="hidden text-sm font-medium px-3 py-2 rounded-lg"></div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="submit" class="px-4 py-2 bg-slate-800 text-white font-medium rounded-lg hover:bg-slate-900 transition text-sm">Cambiar contraseña</button>
                </div>
            </form>
            <div class="p-6 border-t border-slate-100 flex justify-between items-center">
                <a href="logout.php" class="px-4 py-2 bg-red-50 text-red-600 font-medium rounded-lg hover:bg-red-100 transition text-sm"><i class="bi bi-box-arrow-right me-1"></i>Cerrar sesión</a>
            </div>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('wc-sidebar');
        const mobileMenu = document.getElementById('wc-mobile-menu');
        const mobileOverlay = document.getElementById('wc-mobile-overlay');
        function closeMobileMenu() {
            sidebar.classList.add('-translate-x-full');
            mobileOverlay.classList.add('hidden');
            mobileMenu?.setAttribute('aria-expanded', 'false');
        }
        function openMobileMenu() {
            sidebar.classList.remove('-translate-x-full');
            mobileOverlay.classList.remove('hidden');
            mobileMenu?.setAttribute('aria-expanded', 'true');
        }
        mobileMenu?.addEventListener('click', () => sidebar.classList.contains('-translate-x-full') ? openMobileMenu() : closeMobileMenu());
        mobileOverlay?.addEventListener('click', closeMobileMenu);
        document.querySelectorAll('[data-mobile-close]').forEach((link) => link.addEventListener('click', closeMobileMenu));
        window.addEventListener('resize', () => { if (window.innerWidth >= 768) closeMobileMenu(); });

                const BASE_PATH = '<?= $basePath ?>';
        const profileModal = document.getElementById('profile-modal');
        const trigger = document.getElementById('profile-area');
        if (trigger) {
            trigger.addEventListener('click', (event) => {
                event.stopPropagation();
                profileModal.classList.remove('hidden');
            });
        }
        function closeProfile() { profileModal.classList.add('hidden'); }
        profileModal.addEventListener('click', (e) => { if (e.target === profileModal) closeProfile(); });

        function previewFoto(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (ev) => document.getElementById('profile-preview').src = ev.target.result;
            reader.readAsDataURL(file);
        }

        function showMsg(el, msg, ok) {
            el.classList.remove('hidden', 'text-green-800', 'bg-green-100', 'text-red-800', 'bg-red-100');
            el.textContent = msg;
            el.classList.add(ok ? 'text-green-800' : 'text-red-800', ok ? 'bg-green-100' : 'bg-red-100');
            if (ok) setTimeout(() => el.classList.add('hidden'), 3000);
        }

        document.getElementById('profile-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            fd.set('action', 'guardar');
            const msg = document.getElementById('profile-msg');
            msg.classList.add('hidden');
            try {
                const res = await fetch('ajax/perfil.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showMsg(msg, 'Perfil actualizado', true);
                    var fotoUrl = data.foto ? BASE_PATH + '/' + data.foto.replace(/^\//, '') + '?t=' + Date.now() : '';
                    if (fotoUrl) {
                        document.getElementById('profile-preview').src = fotoUrl;
                        document.querySelector('#profile-area img').src = fotoUrl;
                    }
                    document.querySelector('#profile-area .text-sm.text-white').textContent = fd.get('nombre');
                } else {
                    showMsg(msg, data.errors?.join(', ') || 'Error al guardar', false);
                }
            } catch (err) {
                showMsg(msg, 'Error de conexión', false);
            }
        });

        document.getElementById('password-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            fd.set('action', 'cambiar_password');
            const msg = document.getElementById('password-msg');
            msg.classList.add('hidden');
            try {
                const res = await fetch('ajax/perfil.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showMsg(msg, 'Contraseña cambiada correctamente', true);
                    e.target.reset();
                } else {
                    showMsg(msg, data.errors?.join(', ') || 'Error al cambiar contraseña', false);
                }
            } catch (err) {
                showMsg(msg, 'Error de conexión', false);
            }
        });

        setInterval(function(){ fetch('ajax/session.php',{method:'POST'}).catch(()=>{}); }, 30000);
    </script>
    <?= $extraScripts ?? '' ?>
</body>
</html>
