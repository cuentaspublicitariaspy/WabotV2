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
        if ($nombre === '' || $contenido === '') { $message = 'Nombre y contenido obligatorios'; $messageType = 'danger'; }
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
$activePage = 'plantillas';
$pageTitle = 'Plantillas';
ob_start();
?>
<?php if ($message): ?>
<div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium <?= $messageType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
  <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="flex items-center gap-2 mb-5">
  <h1 class="text-2xl font-bold text-slate-800">Plantillas de mensaje</h1>
</div>

<!-- New template form -->
<div class="bg-white border border-slate-100 rounded-2xl p-5 mb-5">
  <h5 class="text-sm font-semibold text-slate-700 mb-4">Nueva plantilla</h5>
  <form method="POST" class="space-y-3">
    <input type="hidden" name="action" value="create">
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
      <input type="text" name="nombre" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Nombre (ej: recordatorio_cita)" required>
      <select name="idioma" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
        <option value="es">Español</option>
        <option value="en">Inglés</option>
        <option value="pt">Portugués</option>
      </select>
      <input type="number" name="parametros" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Params" min="0" max="5" value="0">
    </div>
    <textarea name="contenido" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" rows="3" placeholder="Contenido. Usá {{1}}, {{2}} para parámetros..." required></textarea>
    <button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition shadow-lg shadow-emerald-200">Crear plantilla</button>
  </form>
</div>

<!-- Existing templates -->
<div class="bg-white border border-slate-100 rounded-2xl overflow-hidden">
  <div class="px-5 py-3 border-b border-slate-100">
    <h5 class="text-sm font-semibold text-slate-700">Plantillas existentes</h5>
    <p class="text-xs text-slate-400 mt-1">Deben estar aprobadas por Meta para usarse fuera de la ventana de 24hs.</p>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-left text-xs text-slate-500 uppercase tracking-wider">
        <tr>
          <th class="px-4 py-3 font-medium">Nombre</th>
          <th class="px-4 py-3 font-medium">Idioma</th>
          <th class="px-4 py-3 font-medium">Contenido</th>
          <th class="px-4 py-3 font-medium">Params</th>
          <th class="px-4 py-3 font-medium">Activo</th>
          <th class="px-4 py-3 font-medium">Acciones</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php if (empty($plantillas)): ?>
        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No hay plantillas aún</td></tr>
        <?php endif; ?>
        <?php foreach ($plantillas as $p): ?>
        <tr class="hover:bg-slate-50">
          <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <td class="px-4 py-2"><input type="text" name="nombre" value="<?= htmlspecialchars($p['nombre']) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500"></td>
            <td class="px-4 py-2">
              <select name="idioma" class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
                <option value="es" <?= $p['idioma']==='es'?'selected':'' ?>>Español</option>
                <option value="en" <?= $p['idioma']==='en'?'selected':'' ?>>Inglés</option>
                <option value="pt" <?= $p['idioma']==='pt'?'selected':'' ?>>Portugués</option>
              </select>
            </td>
            <td class="px-4 py-2"><input type="text" name="contenido" value="<?= htmlspecialchars($p['contenido']) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" style="min-width:180px;"></td>
            <td class="px-4 py-2"><input type="number" name="parametros" value="<?= $p['parametros'] ?>" min="0" max="5" class="w-16 border border-slate-200 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500"></td>
            <td class="px-4 py-2">
              <label class="inline-flex items-center cursor-pointer">
                <input type="checkbox" name="activo" value="1" class="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= $p['activo']?'checked':'' ?>>
              </label>
            </td>
            <td class="px-4 py-2 whitespace-nowrap">
              <button type="submit" class="px-3 py-1.5 bg-blue-50 text-blue-600 text-xs font-medium rounded-lg hover:bg-blue-100 transition">Guardar</button>
          </form>
          <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar plantilla?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button type="submit" class="px-3 py-1.5 bg-red-50 text-red-600 text-xs font-medium rounded-lg hover:bg-red-100 transition ml-1">Eliminar</button>
          </form>
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
