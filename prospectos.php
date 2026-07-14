<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/ProspectManager.php';
requireLogin();
$user = getUsuarioActual();
if (!in_array($user['rol'], ['super_admin','admin','agent'], true)) { header('Location: index.php'); exit; }
$manager = new ProspectManager();
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';
    if ($action === 'delete' && isset($_POST['prospecto_id'])) {
        $manager->eliminar((int) $_POST['prospecto_id']);
        header('Location: prospectos.php?deleted=1'); exit;
    }
    if ($action === 'create') {
        $id = $manager->crear($_POST);
        header('Location: prospectos.php?id=' . $id . '&saved=1'); exit;
    }
    if (isset($_POST['prospecto_id'])) {
        $manager->actualizar((int) $_POST['prospecto_id'], $_POST);
        header('Location: prospectos.php?id=' . (int)$_POST['prospecto_id'] . '&saved=1'); exit;
    }
}
$search = trim($_GET['search'] ?? ''); $heat = $_GET['heat'] ?? '';
$prospectos = $manager->listar($search, $heat);
$nuevo = ($_GET['mode'] ?? '') === 'nuevo';
$seleccionado = isset($_GET['id']) ? $manager->obtener((int)$_GET['id']) : null;
if ($nuevo) $seleccionado = ['id'=>0,'nombre'=>'','email'=>'','whatsapp'=>'','empresa'=>'','ocupacion'=>'','sitio_web'=>'','direccion'=>'','ciudad'=>'','pais'=>'','estado'=>'nuevo','nivel_interes'=>'medio','temperatura'=>'tibio','puntaje'=>0,'intencion'=>'','resumen'=>'','notas'=>''];
$activePage='prospectos'; $pageTitle='Prospectos';
ob_start();
?>
<div class="max-w-[1600px] mx-auto min-h-full bg-white">
  <div class="h-16 px-5 border-b border-slate-200 flex items-center justify-between gap-4">
    <div><h1 class="text-sm font-bold text-slate-800">Prospectos</h1><p class="text-xs text-slate-400">Perfiles unificados de WhatsApp y Chatbot</p></div>
    <div class="flex items-center gap-3"><span class="bg-slate-100 text-slate-500 text-xs px-2.5 py-1 rounded-full font-bold"><?= count($prospectos) ?></span><a href="prospectos.php?mode=nuevo&search=<?= urlencode($search) ?>&heat=<?= urlencode($heat) ?>" class="bg-slate-900 text-white px-3 py-2 rounded-lg text-xs font-semibold shadow-sm hover:bg-slate-800">+ Añadir</a><a href="conversaciones.php" class="bg-emerald-600 text-white px-3 py-2 rounded-lg text-xs font-semibold shadow-sm hover:bg-emerald-700">Ver conversaciones</a></div>
  </div>
  <?php if (isset($_GET['saved'])): ?><div class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm font-medium">✓ Prospecto guardado.</div><?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?><div class="rounded-xl border border-slate-200 bg-slate-50 text-slate-600 px-4 py-3 text-sm font-medium">Prospecto eliminado.</div><?php endif; ?>
  <form class="p-4 border-b border-slate-200 bg-slate-50/60 flex flex-wrap items-end gap-3" method="GET">
    <label class="flex-1 min-w-[220px] text-[11px] font-semibold text-slate-500">Nombre o contacto<input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar..." class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500"></label>
    <label class="w-full sm:w-48 text-[11px] font-semibold text-slate-500">Temperatura<select name="heat" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"><option value="">Todas</option><?php foreach(['frio'=>'Frío','tibio'=>'Tibio','caliente'=>'Caliente','muy_caliente'=>'Muy caliente'] as $key=>$label): ?><option value="<?= $key ?>" <?= $heat===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
    <a href="prospectos.php" class="rounded-lg px-3 py-2 text-slate-400 hover:text-emerald-600" title="Limpiar filtros">×</a>
  </form>
  <div class="border border-slate-200 overflow-hidden bg-white">
      <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50 text-left text-xs text-slate-500"><tr><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Origen</th><th class="px-4 py-3">Fecha de ingreso</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acciones</th></tr></thead><tbody class="divide-y divide-slate-100">
      <?php foreach($prospectos as $p):
        $selected=(int)($seleccionado['id']??0)===(int)$p['id'];
        $tone=$p['temperatura']==='muy_caliente'?'red':($p['temperatura']==='caliente'?'orange':($p['temperatura']==='tibio'?'amber':'sky'));
        $estado=$p['estado'] ?? 'nuevo';
        $estadoLabel=['nuevo'=>'Nuevo','contactado'=>'Contactado','seguimiento'=>'Seguimiento','cerrado'=>'Cerrado'][$estado] ?? 'Nuevo';
        $telefono=preg_replace('/\D+/', '', (string)$p['whatsapp']);
      ?>
      <tr class="hover:bg-slate-50 <?= $selected?'bg-emerald-50':'' ?>">
        <td class="px-4 py-3"><div class="flex items-center gap-3"><span class="w-1.5 h-8 rounded-full bg-<?= $tone ?>-500"></span><div><p class="font-semibold text-slate-800"><?= htmlspecialchars($p['nombre']?:'Sin nombre') ?></p><p class="text-xs text-slate-400"><?= htmlspecialchars($p['empresa']?:($p['email']?:$p['whatsapp'])) ?></p></div></div></td>
        <td class="px-4 py-3"><span class="text-xs bg-slate-100 text-slate-600 px-2 py-1 rounded-full"><?= htmlspecialchars($p['canales']?:'—') ?></span></td>
        <td class="px-4 py-3 text-xs text-slate-500"><?= date('d/m/Y',strtotime($p['created_at'])) ?></td>
        <td class="px-4 py-3"><span class="text-xs font-semibold text-slate-600"><span class="inline-block h-1.5 w-1.5 rounded-full bg-<?= $tone ?>-500 mr-1"></span><?= $estadoLabel ?></span></td>
        <td class="px-4 py-3"><div class="flex justify-end gap-1.5">
          <?php if($telefono): ?><a href="tel:<?= htmlspecialchars($telefono) ?>" title="Llamar" class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white">☎</a><a href="https://wa.me/<?= htmlspecialchars($telefono) ?>" target="_blank" rel="noopener" title="WhatsApp" class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white">◉</a><?php endif; ?>
          <a href="prospectos.php?id=<?= $p['id'] ?>&search=<?= urlencode($search) ?>&heat=<?= urlencode($heat) ?>" title="Editar ficha" class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-slate-100 text-slate-600 hover:bg-slate-700 hover:text-white">✎</a>
          <form method="POST" onsubmit="return confirm('¿Eliminar este prospecto? Esta acción no se puede deshacer.');"><input type="hidden" name="action" value="delete"><input type="hidden" name="prospecto_id" value="<?= $p['id'] ?>"><button title="Eliminar" class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-red-50 text-red-600 hover:bg-red-600 hover:text-white">⌫</button></form>
        </div></td>
      </tr>
      <?php endforeach; ?>
      <?php if(!$prospectos): ?><tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">Todavía no hay prospectos. Se crearán al recibir mensajes o analizarlos desde Conversaciones.</td></tr><?php endif; ?>
      </tbody></table></div>
  </div>
</div>
<?php if($seleccionado): ?>
<div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/45" role="dialog" aria-modal="true" aria-labelledby="prospect-modal-title">
  <div class="w-full max-w-3xl max-h-[92vh] overflow-hidden rounded-2xl bg-white shadow-2xl">
    <form method="POST" class="flex max-h-[92vh] flex-col"><input type="hidden" name="action" value="<?= $nuevo ? 'create' : 'update' ?>"><?php if(!$nuevo): ?><input type="hidden" name="prospecto_id" value="<?= $seleccionado['id'] ?>"><?php endif; ?>
      <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4"><div><p class="text-xs font-semibold text-emerald-600">Ficha comercial</p><h2 id="prospect-modal-title" class="mt-1 font-bold text-slate-800"><?= $nuevo ? 'Añadir prospecto' : 'Editar prospecto' ?></h2></div><div class="flex items-center gap-4"><span class="text-2xl font-bold text-slate-800"><?= (int)$seleccionado['puntaje'] ?><small class="text-xs text-slate-400">/100</small></span><a href="prospectos.php?search=<?= urlencode($search) ?>&heat=<?= urlencode($heat) ?>" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Cerrar ficha">✕</a></div></div>
      <div class="overflow-y-auto p-6 space-y-4"><div class="grid grid-cols-1 sm:grid-cols-2 gap-3"><?php foreach(['nombre'=>'Nombre','email'=>'Email','whatsapp'=>'WhatsApp','empresa'=>'Empresa','ocupacion'=>'Ocupación','sitio_web'=>'Web','direccion'=>'Dirección','ciudad'=>'Ciudad','pais'=>'País'] as $field=>$label): ?><label class="text-xs font-medium text-slate-600 <?= in_array($field,['nombre','email','direccion','sitio_web'])?'sm:col-span-2':'' ?>"><?= $label ?><input name="<?= $field ?>" value="<?= htmlspecialchars($seleccionado[$field]??'') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label><?php endforeach; ?></div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3"><label class="text-xs font-medium text-slate-600">Estado<select name="estado" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm"><?php foreach(['nuevo'=>'Nuevo','contactado'=>'Contactado','seguimiento'=>'Seguimiento','cerrado'=>'Cerrado'] as $v=>$label): ?><option value="<?= $v ?>" <?= $seleccionado['estado']===$v?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><label class="text-xs font-medium text-slate-600">Interés<select name="nivel_interes" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm"><?php foreach(['bajo','medio','alto'] as $v): ?><option <?= $seleccionado['nivel_interes']===$v?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></label><label class="text-xs font-medium text-slate-600">Temperatura<select name="temperatura" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm"><?php foreach(['frio','tibio','caliente','muy_caliente'] as $v): ?><option value="<?= $v ?>" <?= $seleccionado['temperatura']===$v?'selected':'' ?>><?= str_replace('_',' ',$v) ?></option><?php endforeach; ?></select></label></div>
      <label class="text-xs font-medium text-slate-600">Puntaje (0–100)<input type="number" min="0" max="100" name="puntaje" value="<?= (int)$seleccionado['puntaje'] ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label><label class="text-xs font-medium text-slate-600">Intención<textarea name="intencion" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= htmlspecialchars($seleccionado['intencion']) ?></textarea></label><label class="text-xs font-medium text-slate-600">Resumen<textarea name="resumen" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= htmlspecialchars($seleccionado['resumen']) ?></textarea></label><label class="text-xs font-medium text-slate-600">Notas internas<textarea name="notas" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= htmlspecialchars($seleccionado['notas']) ?></textarea></label></div>
      <div class="flex items-center justify-between gap-3 border-t border-slate-200 px-6 py-4"><?php if(!$nuevo): ?><button type="submit" form="delete-prospect-<?= $seleccionado['id'] ?>" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Eliminar</button><?php else: ?><span></span><?php endif; ?><div class="flex gap-3"><a href="prospectos.php?search=<?= urlencode($search) ?>&heat=<?= urlencode($heat) ?>" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-500 hover:bg-slate-100">Cancelar</a><button class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Guardar ficha</button></div></div>
    </form>
    <?php if(!$nuevo): ?><form id="delete-prospect-<?= $seleccionado['id'] ?>" method="POST" onsubmit="return confirm('¿Eliminar este prospecto? Esta acción no se puede deshacer.');"><input type="hidden" name="action" value="delete"><input type="hidden" name="prospecto_id" value="<?= $seleccionado['id'] ?>"></form><?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php $mainContent=ob_get_clean(); require __DIR__ . '/includes/layout_tailwind.php';
