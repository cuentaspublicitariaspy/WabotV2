<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Database.php';
requireLogin();
$user = getUsuarioActual();
$activePage = 'estadisticas';
$pageTitle = 'Estadísticas';
ob_start();
?>
<div class="flex items-center justify-between mb-5">
  <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
    Estadísticas
  </h1>
</div>
<div id="metrics-cards" class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6"></div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
  <div class="bg-white border border-slate-100 rounded-2xl overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-100"><h5 class="text-sm font-semibold text-slate-700">Rendimiento por agente</h5></div>
    <div id="agent-table"></div>
  </div>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <div class="bg-white border border-slate-100 rounded-2xl overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100"><h5 class="text-sm font-semibold text-slate-700">Actividad por hora</h5></div>
      <div id="hour-table"></div>
    </div>
    <div class="bg-white border border-slate-100 rounded-2xl overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100"><h5 class="text-sm font-semibold text-slate-700">Por departamento</h5></div>
      <div id="dept-table"></div>
    </div>
  </div>
</div>

<?php $extraScripts = <<<EOS
<script>
function card(label,val){return'<div class="bg-slate-50 rounded-2xl p-4 border border-slate-100"><p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">'+label+'</p><p class="text-2xl font-bold text-slate-900">'+val+'</p></div>';}
function loadMetrics(){fetch('ajax/metrics.php').then(r=>r.json()).then(d=>{document.getElementById('metrics-cards').innerHTML=card('Total respuestas',d.total)+card('Tiempo promedio',d.promedio_seg+'s')+card('Más rápida',d.minimo_seg+'s')+card('Más lenta',d.maximo_seg+'s')+card('Por humanos',d.humano_count)+card('Por IA',d.ia_count);let h='<table class="w-full text-sm"><thead class="bg-slate-50 text-left text-xs text-slate-500 uppercase tracking-wider"><tr><th class="px-4 py-3 font-medium">Agente</th><th class="px-4 py-3 font-medium">Total</th><th class="px-4 py-3 font-medium">Promedio</th></tr></thead><tbody class="divide-y divide-slate-50">';if(d.por_agente.length===0)h+='<tr><td colspan="3" class="px-4 py-8 text-center text-slate-400">Sin datos</td></tr>';else d.por_agente.forEach(a=>{h+='<tr class="hover:bg-slate-50"><td class="px-4 py-3 font-medium text-slate-800">'+esc(a.nombre)+'</td><td class="px-4 py-3 text-slate-500">'+a.total+'</td><td class="px-4 py-3 text-slate-500">'+Math.round(a.promedio)+'s</td></tr>';});document.getElementById('agent-table').innerHTML=h+'</tbody></table>';let h2='<table class="w-full text-sm"><thead class="bg-slate-50 text-left text-xs text-slate-500 uppercase tracking-wider"><tr><th class="px-4 py-3 font-medium">Hora</th><th class="px-4 py-3 font-medium">Msjs</th></tr></thead><tbody class="divide-y divide-slate-50">';if(d.por_hora.length===0)h2+='<tr><td colspan="2" class="px-4 py-8 text-center text-slate-400">Sin datos</td></tr>';else d.por_hora.forEach(h=>{h2+='<tr class="hover:bg-slate-50"><td class="px-4 py-3 text-slate-800">'+h.hora+':00</td><td class="px-4 py-3 text-slate-500">'+h.total+'</td></tr>';});document.getElementById('hour-table').innerHTML=h2+'</tbody></table>';let h3='<table class="w-full text-sm"><thead class="bg-slate-50 text-left text-xs text-slate-500 uppercase tracking-wider"><tr><th class="px-4 py-3 font-medium">Depto</th><th class="px-4 py-3 font-medium">Conv</th></tr></thead><tbody class="divide-y divide-slate-50">';if(d.por_departamento.length===0)h3+='<tr><td colspan="2" class="px-4 py-8 text-center text-slate-400">Sin datos</td></tr>';else d.por_departamento.forEach(d=>{h3+='<tr class="hover:bg-slate-50"><td class="px-4 py-3 text-slate-800">'+esc(d.departamento)+'</td><td class="px-4 py-3 text-slate-500">'+d.total+'</td></tr>';});document.getElementById('dept-table').innerHTML=h3+'</tbody></table>';});}
function esc(s){const e=document.createElement('div');e.textContent=s||'';return e.innerHTML;}
loadMetrics();setInterval(loadMetrics,15000);
</script>
EOS;
$mainContent = ob_get_clean();
require __DIR__ . '/includes/layout_tailwind.php';
