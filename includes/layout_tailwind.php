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
$avatarUrl = $userFoto ? $userFoto : "https://ui-avatars.com/api/?name=" . urlencode($user['nombre']) . "&background=10b981&color=fff";
$navLinks = [
    'dashboard' => ['label' => 'Dashboard', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>', 'href' => 'index.php'],
    'conversaciones' => ['label' => 'Conversaciones', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>', 'href' => 'conversaciones.php'],
    'conocimiento' => ['label' => 'Conocimiento', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>', 'href' => 'conocimiento.php'],
];
$adminNavLinks = [
    'estadisticas' => ['label' => 'Estadísticas', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>', 'href' => 'estadisticas.php'],
    'usuarios' => ['label' => 'Usuarios', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>', 'href' => 'usuarios.php'],
];
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
        <?= $extraStyle ?? '' ?>
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-900 font-sans overflow-hidden">

    <div class="flex h-full">
        <!-- SIDEBAR -->
        <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col justify-between py-6 shrink-0 shadow-xl">
            <div class="px-6 mb-8">
                <a href="index.php" class="text-white font-bold text-xl flex items-center gap-2">
                    <span class="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center text-white text-sm shadow-lg shadow-emerald-900/50">W</span>
                    <span>Wabot</span>
                </a>
            </div>

            <nav class="flex-1 px-4 space-y-8">
                <div class="space-y-1">
                    <?php foreach ($navLinks as $key => $link): ?>
                    <a href="<?= $link['href'] ?>" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= $activePage === $key ? 'bg-emerald-600 text-white font-medium shadow-lg shadow-emerald-900/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                        <?= $link['icon'] ?>
                        <span><?= $link['label'] ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($user['rol'] === 'admin'): ?>
                <div class="space-y-1">
                    <h3 class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Administración</h3>
                    <?php foreach ($adminNavLinks as $key => $link): ?>
                    <a href="<?= $link['href'] ?>" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= $activePage === $key ? 'bg-emerald-600 text-white font-medium shadow-lg shadow-emerald-900/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' ?>">
                        <?= $link['icon'] ?>
                        <span><?= $link['label'] ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </nav>

            <div id="profile-area" class="px-6 border-t border-slate-800 pt-6 cursor-pointer hover:bg-slate-800 transition py-2 rounded-xl mx-2">
                <div class="flex items-center gap-3">
                    <img src="<?= $avatarUrl ?>" class="w-10 h-10 rounded-full" alt="Avatar">
                    <div>
                        <p class="text-sm text-white font-medium"><?= $userName ?></p>
                        <p class="text-xs text-emerald-400">● Online</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="flex-1 bg-white overflow-auto <?= isset($fullHeight) && $fullHeight ? 'flex flex-col overflow-hidden' : 'p-6' ?>">
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
        const modal = document.getElementById('profile-modal');
        const trigger = document.getElementById('profile-area');
        if (trigger) {
            trigger.addEventListener('click', () => modal.classList.remove('hidden'));
        }
        function closeProfile() { modal.classList.add('hidden'); }
        modal.addEventListener('click', (e) => { if (e.target === modal) closeProfile(); });

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
                    if (data.foto) document.getElementById('profile-preview').src = data.foto;
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
