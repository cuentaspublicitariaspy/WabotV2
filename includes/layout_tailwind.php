<?php
if (!isset($user) || $user === null) return;
require_once __DIR__ . '/Database.php';
$activePage = $activePage ?? '';
$pageTitle = $pageTitle ?? 'Wabot';
$userName = htmlspecialchars($user['nombre']);
$userRol = htmlspecialchars($user['rol']);
$userEmail = '';
try {
    $stmt = Database::getConnection()->prepare("SELECT email FROM usuarios WHERE id = ?");
    $stmt->execute([(int)$user['id']]);
    $row = $stmt->fetch();
    $userEmail = htmlspecialchars($row['email'] ?? '');
} catch (Exception $e) {}
$avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($user['nombre']) . "&background=10b981&color=fff";
$navLinks = [
    'dashboard' => ['label' => 'Dashboard', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>', 'href' => 'index.php'],
    'conversaciones' => ['label' => 'Conversaciones', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>', 'href' => 'conversaciones.php'],
    'conocimiento' => ['label' => 'Conocimiento', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>', 'href' => 'conocimiento.php'],
];
$adminNavLinks = [
    'estadisticas' => ['label' => 'Estadísticas', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>', 'href' => 'estadisticas.php'],
    'plantillas' => ['label' => 'Plantillas', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>', 'href' => 'plantillas.php'],
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
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-800">Editar Perfil</h3>
                <button onclick="closeProfile()" class="text-slate-400 hover:text-slate-600 transition text-xl leading-none">&times;</button>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2 flex items-center gap-6">
                    <img src="<?= $avatarUrl ?>" class="w-20 h-20 rounded-full border-4 border-slate-100">
                    <div>
                        <p class="font-semibold text-slate-800"><?= $userName ?></p>
                        <p class="text-sm text-slate-500"><?= $userEmail ?></p>
                        <p class="text-xs text-slate-400 mt-1">Rol: <?= $userRol ?></p>
                    </div>
                </div>
            </div>
            <div class="p-6 border-t border-slate-100 flex justify-end gap-3">
                <button onclick="closeProfile()" class="px-4 py-2 text-slate-600 font-medium hover:bg-slate-100 rounded-lg transition">Cancelar</button>
                <a href="logout.php" class="px-4 py-2 bg-red-50 text-red-600 font-medium rounded-lg hover:bg-red-100 transition">Cerrar sesión</a>
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
        setInterval(function(){ fetch('ajax/session.php',{method:'POST'}).catch(()=>{}); }, 30000);
    </script>
    <?= $extraScripts ?? '' ?>
</body>
</html>
