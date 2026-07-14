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
$metricas = $manager->metricas();
$prospectos = $manager->listar($search, $heat);
$seleccionado = isset($_GET['id']) ? $manager->obtener((int)$_GET['id']) : null;
$activePage='prospectos'; $pageTitle='Prospectos';
ob_start();
?>
<div class="p-6 max-w-[1600px] mx-auto">
  <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div><p class="text-sm font-semibold text-emerald-600 uppercase tracking-[.16em]">Comercial</p><h1 class="text-2xl font-bold text-slate-900 mt-1">Prospectos</h1><p class="text-sm text-slate-500 mt-1">Perfiles unificados de WhatsApp y Chatbot.</p></div>
    <a href="conversaciones.php" class="px-4 py-2.5 text-sm font-semibold rounded-xl bg-slate-900 text-white hover:bg-slate-800">Ver conversaciones</a>
  </div>
  <?php if (isset($_GET['saved'])): ?><div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm font-medium">✓ Prospecto actualizado.</div><?php endif; ?>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php $cards=[['Total',$metricas['total'],'bg-slate-900 text-white'],['Calientes',$metricas['calientes'],'bg-orange-50 text-orange-800'],['Muy calientes',$metricas['muy_calientes'],'bg-red-50 text-red-800'],['Puntaje promedio',$metricas['puntaje_promedio'].'/100','bg-violet-50 text-violet-800']]; foreach($cards as [$label,$value,$class]): ?>
    <div class="rounded-2xl p-5 <?= $class ?>"><p class="text-xs font-semibold uppercase tracking-wider opacity-70"><?= $label ?></p><p class="text-3xl font-bold mt-2"><?= $value ?></p></div>
    <?php endforeach; ?>
  </div>
  <form class="flex flex-wrap gap-3 mb-5" method="GET">
    <input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar nombre, empresa, email, WhatsApp..." class="w-full md:w-96 rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
    <select name="heat" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="">Todas las temperaturas</option><?php foreach(['frio'=>'Frío','tibio'=>'Tibio','caliente'=>'Caliente','muy_caliente'=>'Muy caliente'] as $key=>$label): ?><option value="<?= $key ?>" <?= $heat===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select>
    <button class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Filtrar</button>
  </form>
  <div class="grid grid-cols-1 xl:grid-cols-[1fr_430px] gap-6">
    <div class="border border-slate-200 rounded-2xl overflow-hidden bg-white">
      <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3">Prospecto</th><th class="px-4 py-3">Canal</th><th class="px-4 py-3">Intención</th><th class="px-4 py-3">Interés</th><th class="px-4 py-3">Temperatura</th><th class="px-4 py-3">Puntaje</th></tr></thead><tbody class="divide-y divide-slate-100">
      <?php foreach($prospectos as $p): $selected=(int)($seleccionado['id']??0)===(int)$p['id']; ?><tr onclick="location.href='prospectos.php?id=<?= $p['id'] ?>&search=<?= urlencode($search) ?>&heat=<?= urlencode($heat) ?>'" class="cursor-pointer hover:bg-slate-50 <?= $selected?'bg-emerald-50':'' ?>"><td class="px-4 py-3"><p class="font-semibold text-slate-800"><?= htmlspecialchars($p['nombre']?:'Sin nombre') ?></p><p class="text-xs text-slate-400"><?= htmlspecialchars($p['empresa']?:($p['email']?:$p['whatsapp'])) ?></p></td><td class="px-4 py-3 text-xs text-slate-500"><?= htmlspecialchars($p['canales']?:'—') ?></td><td class="px-4 py-3 max-w-[220px] truncate text-slate-600"><?= htmlspecialchars($p['intencion']?:'Sin analizar') ?></td><td class="px-4 py-3 capitalize text-slate-600"><?= htmlspecialchars($p['nivel_interes']) ?></td><td class="px-4 py-3"><span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $p['temperatura']==='muy_caliente'?'bg-red-100 text-red-700':($p['temperatura']==='caliente'?'bg-orange-100 text-orange-700':($p['temperatura']==='tibio'?'bg-amber-100 text-amber-700':'bg-sky-100 text-sky-700')) ?>"><?= htmlspecialchars(str_replace('_',' ',$p['temperatura'])) ?></span></td><td class="px-4 py-3 font-bold text-slate-700"><?= (int)$p['puntaje'] ?></td></tr><?php endforeach; ?>
      <?php if(!$prospectos): ?><tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">Todavía no hay prospectos. Se crearán al recibir mensajes o analizarlos desde Conversaciones.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
    <aside class="border border-slate-200 rounded-2xl bg-white p-5 xl:sticky xl:top-6 h-fit">
      <?php if($seleccionado): ?><form method="POST" class="space-y-4"><input type="hidden" name="prospecto_id" value="<?= $seleccionado['id'] ?>"><div class="flex items-center justify-between"><div><p class="text-xs uppercase tracking-wider font-semibold text-emerald-600">Ficha comercial</p><h2 class="font-bold text-slate-800 mt-1">Editar prospecto</h2></div><span class="text-2xl font-bold text-slate-800"><?= (int)$seleccionado['puntaje'] ?><small class="text-xs text-slate-400">/100</small></span></div>
      <div class="grid grid-cols-2 gap-3"><?php foreach(['nombre'=>'Nombre','email'=>'Email','whatsapp'=>'WhatsApp','empresa'=>'Empresa','ocupacion'=>'Ocupación','sitio_web'=>'Web','direccion'=>'Dirección','ciudad'=>'Ciudad','pais'=>'País'] as $field=>$label): ?><label class="text-xs font-medium text-slate-600 <?= in_array($field,['nombre','email','direccion','sitio_web'])?'col-span-2':'' ?>"><?= $label ?><input name="<?= $field ?>" value="<?= htmlspecialchars($seleccionado[$field]??'') ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label><?php endforeach; ?></div>
      <div class="grid grid-cols-3 gap-3"><label class="text-xs font-medium text-slate-600">Interés<select name="nivel_interes" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm"><?php foreach(['bajo','medio','alto'] as $v): ?><option <?= $seleccionado['nivel_interes']===$v?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></label><label class="text-xs font-medium text-slate-600 col-span-2">Temperatura<select name="temperatura" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm"><?php foreach(['frio','tibio','caliente','muy_caliente'] as $v): ?><option value="<?= $v ?>" <?= $seleccionado['temperatura']===$v?'selected':'' ?>><?= str_replace('_',' ',$v) ?></option><?php endforeach; ?></select></label></div>
      <label class="text-xs font-medium text-slate-600">Puntaje (0–100)<input type="number" min="0" max="100" name="puntaje" value="<?= (int)$seleccionado['puntaje'] ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label><label class="text-xs font-medium text-slate-600">Intención<textarea name="intencion" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= htmlspecialchars($seleccionado['intencion']) ?></textarea></label><label class="text-xs font-medium text-slate-600">Resumen<textarea name="resumen" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= htmlspecialchars($seleccionado['resumen']) ?></textarea></label><label class="text-xs font-medium text-slate-600">Notas internas<textarea name="notas" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= htmlspecialchars($seleccionado['notas']) ?></textarea></label><button class="w-full rounded-xl bg-emerald-600 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Guardar ficha</button></form>
      <?php else: ?><div class="py-16 text-center"><div class="w-12 h-12 mx-auto rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">◌</div><p class="font-semibold text-slate-700 mt-4">Seleccioná un prospecto</p><p class="text-sm text-slate-400 mt-1">Vas a poder completar y corregir toda su ficha comercial.</p></div><?php endif; ?>
    </aside>
  </div>
</div>
<?php $mainContent=ob_get_clean(); require __DIR__ . '/includes/layout_tailwind.php';
