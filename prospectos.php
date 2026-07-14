<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/ProspectManager.php';
requireLogin();
$user = getUsuarioActual();
if (!in_array($user['rol'], ['super_admin','admin','agent'], true)) { header('Location: index.php'); exit; }
$manager = new ProspectManager();
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prospecto_id'])) {
    $manager->actualizar((int)$_POST['prospecto_id'], $_POST);
    header('Location: prospectos.php?id=' . (int)$_POST['prospecto_id'] . '&saved=1'); exit;
}
$search = trim($_GET['search'] ?? ''); $heat = $_GET['heat'] ?? '';
$prospectos = $manager->listar($search, $heat);
$seleccionado = isset($_GET['id']) ? $manager->obtener((int)$_GET['id']) : null;
$activePage='prospectos'; $pageTitle='Prospectos';
ob_start();
?>
<div class="max-w-[1600px] mx-auto min-h-full bg-white">
  <div class="h-16 px-5 border-b border-slate-200 flex items-center justify-between gap-4">
    <div><h1 class="text-sm font-bold text-slate-800">Prospectos</h1><p class="text-xs text-slate-400">Perfiles unificados de WhatsApp y Chatbot</p></div>
    <div class="flex items-center gap-3"><span class="bg-slate-100 text-slate-500 text-xs px-2.5 py-1 rounded-full font-bold"><?= count($prospectos) ?></span><a href="conversaciones.php" class="bg-emerald-600 text-white px-3 py-2 rounded-lg text-xs font-semibold shadow-sm hover:bg-emerald-700">Ver conversaciones</a></div>
  </div>
  <?php if (isset($_GET['saved'])): ?><div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm font-medium">✓ Prospecto actualizado.</div><?php endif; ?>
  <form class="p-4 border-b border-slate-200 bg-slate-50/60 flex flex-wrap items-end gap-3" method="GET">
    <label class="flex-1 min-w-[220px] text-[11px] font-semibold text-slate-500">Nombre o contacto<input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar..." class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500"></label>
    <label class="w-full sm:w-48 text-[11px] font-semibold text-slate-500">Temperatura<select name="heat" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"><option value="">Todas</option><?php foreach(['frio'=>'Frío','tibio'=>'Tibio','caliente'=>'Caliente','muy_caliente'=>'Muy caliente'] as $key=>$label): ?><option value="<?= $key ?>" <?= $heat===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrar</button>
    <a href="prospectos.php" class="rounded-lg px-3 py-2 text-slate-400 hover:text-emerald-600" title="Limpiar filtros">×</a>
  </form>
  <div class="grid grid-cols-1 xl:grid-cols-[1fr_430px] gap-6">
    <div class="border border-slate-200 rounded-none overflow-hidden bg-white">
      <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50 text-left text-xs text-slate-500"><tr><th class="px-4 py-3">Nombre</th><th class="px-4 py-3">Origen</th><th class="px-4 py-3">Fecha de ingreso</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acciones</th></tr></thead><tbody class="divide-y divide-slate-100">
      <?php foreach($prospectos as $p): $selected=(int)($seleccionado['id']??0)===(int)$p['id']; $tone=$p['temperatura']==='muy_caliente'?'red':($p['temperatura']==='caliente'?'orange':($p['temperatura']==='tibio'?'amber':'sky')); ?><tr class="hover:bg-slate-50 <?= $selected?'bg-emerald-50':'' ?>"><td class="px-4 py-3"><div class="flex items-center gap-3"><span class="w-1.5 h-8 rounded-full bg-<?= $tone ?>-500"></span><div><p class="font-semibold text-slate-800"><?= htmlspecialchars($p['nombre']?:'Sin nombre') ?></p><p class="text-xs text-slate-400"><?= htmlspecialchars($p['empresa']?:($p['email']?:$p['whatsapp'])) ?></p></div></div></td><td class="px-4 py-3"><span class="text-xs bg-slate-100 text-slate-600 px-2 py-1 rounded-full"><?= htmlspecialchars($p['canales']?:'—') ?></span></td><td class="px-4 py-3 text-xs text-slate-500"><?= date('d/m/Y',strtotime($p['created_at'])) ?></td><td class="px-4 py-3"><span class="text-xs font-semibold text-<?= $tone ?>-700">● <?= htmlspecialchars(str_replace('_',' ',$p['temperatura'])) ?></span></td><td class="px-4 py-3 text-right"><a href="prospectos.php?id=<?= $p['id'] ?>&search=<?= urlencode($search) ?>&heat=<?= urlencode($heat) ?>" class="inline-flex rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-600 hover:text-white">Ver ficha</a></td></tr><?php endforeach; ?>
      <?php if(!$prospectos): ?><tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">Todavía no hay prospectos. Se crearán al recibir mensajes o analizarlos desde Conversaciones.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
    <aside class="border border-slate-200 rounded-2xl bg-white p-5 xl:sticky xl:top-6 h-fit">
      <?php if($seleccionado): ?><form method="POST" class="space-y-4"><input type="hidden" name="prospecto_id" value="<?= $seleccionado['id'] ?>"><div class="flex items-center justify-between"><div><p class="text-xs font-semibold text-emerald-600">Ficha comercial</p><h2 class="font-bold text-slate-800 mt-1">Editar prospecto</h2></div><span class="text-2xl font-bold text-slate-800"><?= (int)$seleccionado['puntaje'] ?><small class="text-xs text-slate-400">/100</small></span></div>
      <div class="grid grid-cols-2 gap-3"><?php foreach(['nombre'=>'Nombre','email'=>'Email','whatsapp'=>'WhatsApp','empresa'=>'Empresa','ocupacion'=>'Ocupación','sitio_web'=>'Web','direccion'=>'Dirección','ciudad'=>'Ciudad','pais'=>'País'] as $field=>$label): ?><label class="text-xs font-medium text-slate-600 <?= in_array($field,['nombre','email','direccion','sitio_web'])?'col-span-2':'' ?>"><?= $label ?><input name="<?= $field ?>" value="<?= htmlspecialchars($seleccionado[$field]??'') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label><?php endforeach; ?></div>
      <div class="grid grid-cols-3 gap-3"><label class="text-xs font-medium text-slate-600">Interés<select name="nivel_interes" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm"><?php foreach(['bajo','medio','alto'] as $v): ?><option <?= $seleccionado['nivel_interes']===$v?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></label><label class="text-xs font-medium text-slate-600 col-span-2">Temperatura<select name="temperatura" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm"><?php foreach(['frio','tibio','caliente','muy_caliente'] as $v): ?><option value="<?= $v ?>" <?= $seleccionado['temperatura']===$v?'selected':'' ?>><?= str_replace('_',' ',$v) ?></option><?php endforeach; ?></select></label></div>
      <label class="text-xs font-medium text-slate-600">Puntaje (0–100)<input type="number" min="0" max="100" name="puntaje" value="<?= (int)$seleccionado['puntaje'] ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label><label class="text-xs font-medium text-slate-600">Intención<textarea name="intencion" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= htmlspecialchars($seleccionado['intencion']) ?></textarea></label><label class="text-xs font-medium text-slate-600">Resumen<textarea name="resumen" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= htmlspecialchars($seleccionado['resumen']) ?></textarea></label><label class="text-xs font-medium text-slate-600">Notas internas<textarea name="notas" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= htmlspecialchars($seleccionado['notas']) ?></textarea></label><button class="w-full rounded-xl bg-emerald-600 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Guardar ficha</button></form>
      <?php else: ?><div class="py-16 text-center"><div class="w-12 h-12 mx-auto rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">◌</div><p class="font-semibold text-slate-700 mt-4">Seleccioná un prospecto</p><p class="text-sm text-slate-400 mt-1">Vas a poder completar y corregir toda su ficha comercial.</p></div><?php endif; ?>
    </aside>
  </div>
</div>
<?php $mainContent=ob_get_clean(); require __DIR__ . '/includes/layout_tailwind.php';
