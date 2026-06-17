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
        if ($nombre === '' || $email === '' || $password === '') { $message = 'Todos los campos obligatorios'; $messageType = 'danger'; }
        else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            try { $db->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)")->execute([$nombre, $email, $hash, $rol]); $message = 'Usuario creado'; $messageType = 'success'; }
            catch (PDOException $e) { $message = 'Error: email ya existe'; $messageType = 'danger'; }
        }
    } elseif ($action === 'toggle') { $id=(int)($_POST['user_id']??0); if($id>0){ $db->prepare("UPDATE usuarios SET activo = IF(activo=1,0,1) WHERE id=?")->execute([$id]); $message='Estado cambiado'; $messageType='success'; } }
    elseif ($action === 'toggle_disponible') { $id=(int)($_POST['user_id']??0); if($id>0){ $db->prepare("UPDATE usuarios SET disponible = IF(disponible=1,0,1) WHERE id=?")->execute([$id]); $message='Disponibilidad cambiada'; $messageType='success'; } }
    elseif ($action === 'ausente') { $id=(int)($_POST['user_id']??0); if($id>0){ $router->marcarAusente($id); $message='Conversaciones del agente liberadas'; $messageType='success'; } }
    elseif ($action === 'delete') { $id=(int)($_POST['user_id']??0); if($id>0){ $db->prepare("DELETE FROM usuarios WHERE id=? AND rol!='admin'")->execute([$id]); $message='Usuario eliminado'; $messageType='success'; } }
}

$router = new AgentRouter();
$agentesActivos = $router->getAgentesActivos();
$activosIds = array_column($agentesActivos, 'id');
$usuarios = $db->query("SELECT id, nombre, email, rol, activo, disponible, ultimo_acceso, ultimo_logout, created_at FROM usuarios ORDER BY id")->fetchAll();
$user = getUsuarioActual();
$activePage = 'usuarios';
$pageTitle = 'Usuarios';
ob_start();
?>
<?php if ($message): ?>
<div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium <?= $messageType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
  <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-5">
  <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
    Usuarios del sistema
  </h1>
</div>

<!-- Online agents -->
<div class="bg-white border border-slate-100 rounded-2xl p-5 mb-5">
  <h5 class="text-sm font-semibold text-slate-700 mb-3">Agentes en línea</h5>
  <div class="flex flex-wrap gap-2">
    <?php if (empty($agentesActivos)): ?>
      <span class="text-xs text-slate-400">No hay agentes conectados. Solo IA disponible.</span>
    <?php else: foreach ($agentesActivos as $a): ?>
      <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 text-xs font-medium rounded-full">
        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
        <?= htmlspecialchars($a['nombre']) ?>
      </span>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- New user form -->
<div class="bg-white border border-slate-100 rounded-2xl p-5 mb-5">
  <h5 class="text-sm font-semibold text-slate-700 mb-4">Crear nuevo usuario</h5>
  <form method="POST" class="grid grid-cols-1 sm:grid-cols-5 gap-3" autocomplete="off">
    <input type="hidden" name="action" value="create">
    <input type="text" name="nombre" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Nombre" required>
    <input type="email" name="email" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Email" required>
    <input type="password" name="password" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Contraseña" autocomplete="new-password" required>
    <select name="rol" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
      <option value="agent">Agente</option>
      <option value="admin">Admin</option>
    </select>
    <button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition shadow-lg shadow-emerald-200">Crear</button>
  </form>
</div>

<!-- Users table -->
<div class="bg-white border border-slate-100 rounded-2xl overflow-hidden">
  <div class="px-5 py-3 border-b border-slate-100">
    <h5 class="text-sm font-semibold text-slate-700">Usuarios existentes</h5>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-xs">
      <thead class="bg-slate-50 text-left text-slate-500 uppercase tracking-wider">
        <tr>
          <th class="px-3 py-2 font-medium">Nombre</th>
          <th class="px-3 py-2 font-medium">Email</th>
          <th class="px-3 py-2 font-medium">Rol</th>
          <th class="px-3 py-2 font-medium">Estado</th>
          <th class="px-3 py-2 font-medium">Disp.</th>
          <th class="px-3 py-2 font-medium">Último acceso</th>
          <th class="px-3 py-2 font-medium">Acciones</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($usuarios as $u): $online = in_array($u['id'], $activosIds); ?>
        <tr class="hover:bg-slate-50">
          <td class="px-3 py-2 font-medium text-slate-800 whitespace-nowrap"><?= htmlspecialchars($u['nombre']) ?></td>
          <td class="px-3 py-2 text-slate-500"><?= htmlspecialchars($u['email']) ?></td>
          <td class="px-3 py-2">
            <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium <?= $u['rol']==='admin' ? 'bg-purple-50 text-purple-600' : 'bg-blue-50 text-blue-600' ?>"><?= $u['rol'] ?></span>
          </td>
          <td class="px-3 py-2 whitespace-nowrap">
            <?php if ($online): ?>
              <span class="text-emerald-600 font-medium">Activo</span>
            <?php elseif ($u['activo']): ?>
              <span class="text-slate-400">Inactivo</span>
            <?php else: ?>
              <span class="text-red-400 font-medium">Desactivado</span>
            <?php endif; ?>
          </td>
          <td class="px-3 py-2 text-center">
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="toggle_disponible">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button type="submit" class="p-0.5 rounded-full transition <?= $u['disponible'] ? 'text-emerald-500 hover:text-emerald-600' : 'text-slate-300 hover:text-slate-500' ?>" title="<?= $u['disponible']?'Disponible':'No disponible' ?>"><i class="bi <?= $u['disponible']?'bi-check-circle-fill':'bi-circle' ?> text-base"></i></button>
            </form>
          </td>
          <td class="px-3 py-2 text-slate-400 whitespace-nowrap"><?= $u['ultimo_acceso'] ? date('d/m H:i', strtotime($u['ultimo_acceso'])) : '-' ?></td>
          <td class="px-3 py-2 whitespace-nowrap">
            <?php if ($u['rol'] !== 'admin'): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="ausente"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button type="submit" class="w-7 h-7 rounded-lg bg-amber-50 text-amber-500 hover:bg-amber-100 hover:text-amber-600 transition inline-flex items-center justify-center" title="Marcar ausente" onclick="return confirm('¿Liberar todas las conversaciones de <?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>?')"><i class="bi bi-person-dash text-sm"></i></button>
            </form>
            <?php endif; ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button type="submit" class="w-7 h-7 rounded-lg <?= $u['activo'] ? 'bg-yellow-50 text-yellow-500 hover:bg-yellow-100 hover:text-yellow-600' : 'bg-emerald-50 text-emerald-500 hover:bg-emerald-100 hover:text-emerald-600' ?> transition inline-flex items-center justify-center" title="<?= $u['activo']?'Desactivar':'Activar' ?>"><i class="bi <?= $u['activo']?'bi-toggle2-on':'bi-toggle2-off' ?> text-sm"></i></button>
            </form>
            <?php if ($u['rol'] !== 'admin'): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar usuario?')">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button type="submit" class="w-7 h-7 rounded-lg bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-500 transition inline-flex items-center justify-center" title="Eliminar"><i class="bi bi-trash text-sm"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$mainContent = ob_get_clean();
require __DIR__ . '/includes/layout_tailwind.php';
