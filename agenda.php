<?php
require_once __DIR__.'/includes/Auth.php';
require_once __DIR__.'/includes/AppointmentManager.php';
requireLogin();

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
$tab = $_GET['tab'] ?? 'citas';
if (!in_array($tab, ['citas', 'configuracion'], true)) $tab = 'citas';
$user = getUsuarioActual();
$agendaInitError = '';
try {
    $agenda = new AppointmentManager();
    $overview = $agenda->dayOverview($date);
    $services = $agenda->list('servicios');
    $agendas = $agenda->list('agendas');
    $branches = $agenda->list('sucursales');
    $hours = $agenda->hours();
    $settings = $agenda->settings();
} catch (Throwable $e) {
    // El diagnóstico se muestra únicamente en el WC ya autenticado. Evita un
    // 500 opaco sin exponer este detalle al visitante del sitio público.
    error_log('[Wabot Agenda] '.$e->getMessage());
    $agendaInitError = $e->getMessage();
    $overview = ['appointments'=>[],'blocks'=>[],'total'=>0,'pending'=>0,'occupied_minutes'=>0,'ready_profiles'=>0];
    $services = $agendas = $branches = $hours = [];
    $settings = ['buffer_minutes'=>0,'min_notice_hours'=>2,'max_advance_days'=>90];
}
$activePage='agenda'; $pageTitle='Agenda';
$previous = date('Y-m-d', strtotime($date.' -1 day'));
$next = date('Y-m-d', strtotime($date.' +1 day'));
$dateLabel = (new DateTimeImmutable($date))->format('d/m/Y');
ob_start();
?>
<?php if ($agendaInitError): ?>
<div class="max-w-3xl mx-auto bg-white border border-red-200 rounded-3xl p-8 shadow-sm">
  <p class="text-sm font-semibold text-red-700">Agenda no pudo inicializarse</p>
  <h1 class="text-2xl font-bold text-slate-900 mt-2">Necesitamos corregir la migración de agenda.</h1>
  <p class="text-sm text-slate-600 mt-3">Este detalle solo se muestra al administrador para diagnosticar el problema:</p>
  <pre class="mt-4 whitespace-pre-wrap break-words rounded-2xl bg-slate-950 text-slate-100 p-4 text-xs leading-relaxed"><?= htmlspecialchars($agendaInitError) ?></pre>
  <p class="text-xs text-slate-500 mt-4">Copiá este mensaje y enviámelo. No incluye contraseñas.</p>
</div>
<?php else: ?>
<div class="max-w-7xl mx-auto space-y-6">
  <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Agenda</h1>
      <p class="text-sm text-slate-500 mt-1"><?= $tab === 'citas' ? 'Una agenda compartida por Chatbot, WhatsApp y tu equipo.' : 'Definí qué se puede reservar y bajo qué reglas.' ?></p>
    </div>
    <?php if ($tab === 'citas'): ?>
    <div class="flex items-center gap-2 flex-wrap">
      <a href="agenda.php?tab=citas&amp;date=<?= $previous ?>" class="w-10 h-10 border border-slate-200 rounded-xl grid place-items-center hover:bg-slate-50" aria-label="Día anterior">‹</a>
      <input id="agenda-date" type="date" value="<?= htmlspecialchars($date) ?>" onchange="goToDate(this.value)" class="h-10 border border-slate-200 rounded-xl px-3 text-sm font-semibold text-slate-700">
      <a href="agenda.php?tab=citas&amp;date=<?= $next ?>" class="w-10 h-10 border border-slate-200 rounded-xl grid place-items-center hover:bg-slate-50" aria-label="Día siguiente">›</a>
      <button type="button" onclick="openBooking(event)" class="h-10 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl px-4 text-sm font-semibold">+ Nueva cita</button>
    </div>
    <?php endif; ?>
  </header>

  <nav class="inline-flex items-center gap-1 rounded-2xl bg-slate-100 p-1" aria-label="Secciones de agenda">
    <a href="agenda.php?tab=citas&amp;date=<?= htmlspecialchars($date) ?>" class="rounded-xl px-4 py-2 text-sm font-semibold transition <?= $tab === 'citas' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800' ?>">Citas</a>
    <a href="agenda.php?tab=configuracion" class="rounded-xl px-4 py-2 text-sm font-semibold transition <?= $tab === 'configuracion' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800' ?>">Configuración</a>
  </nav>

  <?php if ($tab === 'citas'): ?>
  <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
    <section class="xl:col-span-7 bg-white border border-slate-200 rounded-3xl p-5 md:p-6 shadow-sm">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div><h2 class="font-bold text-slate-900">Cronograma del día</h2><p class="text-xs text-slate-400 mt-1"><?= $dateLabel ?> · las colisiones y bloqueos se controlan automáticamente.</p></div>
        <button type="button" onclick="openBlock(event)" class="px-3.5 py-2 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-100 rounded-xl">+ Crear bloqueo</button>
      </div>
      <div id="timeline" class="space-y-3 relative before:absolute before:inset-y-0 before:left-11 before:w-px before:bg-slate-100">
        <?php foreach ($overview['blocks'] as $b): ?>
          <div class="flex gap-4 relative"><div class="w-11 text-right text-[11px] text-slate-400 pt-3"><?= date('H:i',strtotime($b['inicio'])) ?></div><div class="flex-1 border border-indigo-100 bg-indigo-50/60 border-l-4 border-l-indigo-400 rounded-2xl p-4"><div class="flex justify-between gap-3"><div><b class="text-sm text-indigo-950"><?= htmlspecialchars($b['motivo']) ?></b><p class="text-xs text-indigo-600 mt-1">Bloqueo <?= htmlspecialchars($b['agenda'] ?: $b['sucursal'] ?: 'general') ?></p></div><span class="text-xs font-semibold text-indigo-600 whitespace-nowrap"><?= date('H:i',strtotime($b['inicio'])) ?>–<?= date('H:i',strtotime($b['fin'])) ?></span></div></div></div>
        <?php endforeach; ?>
        <?php foreach ($overview['appointments'] as $a): $isPending=$a['estado']==='pendiente_confirmacion'; ?>
          <div class="flex gap-4 relative"><div class="w-11 text-right text-[11px] text-slate-400 pt-3"><?= date('H:i',strtotime($a['inicio'])) ?></div><button type="button" onclick="selectAppointment(<?= (int)$a['id'] ?>, event)" class="appointment-card text-left flex-1 bg-white border border-slate-200 hover:border-emerald-500 rounded-2xl p-4 transition shadow-sm hover:shadow-md border-l-4 <?= $isPending ? 'border-l-amber-500' : 'border-l-emerald-500' ?>" data-id="<?= (int)$a['id'] ?>"><div class="flex items-start justify-between gap-4"><div><div class="flex items-center gap-2 flex-wrap"><b class="text-sm text-slate-900"><?= htmlspecialchars($a['nombre_cliente'] ?: $a['telefono'] ?: 'Sin identificar') ?></b><span class="px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $isPending ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' ?>"><?= htmlspecialchars(str_replace('_',' ',$a['estado'])) ?></span></div><p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($a['servicio'] ?: 'Servicio sin definir') ?></p><p class="text-[11px] text-slate-400 mt-2"><?= date('H:i',strtotime($a['inicio'])) ?>–<?= date('H:i',strtotime($a['fin'])) ?> · <?= htmlspecialchars($a['agenda'] ?: $a['sucursal'] ?: 'Sin asignar') ?> · <?= htmlspecialchars($a['canal']) ?></p></div><span class="text-slate-400">›</span></div></button></div>
        <?php endforeach; ?>
        <?php if (!$overview['appointments'] && !$overview['blocks']): ?><div class="ml-16 p-10 text-center border border-dashed border-slate-200 rounded-2xl text-sm text-slate-400">No hay citas ni bloqueos para este día.</div><?php endif; ?>
      </div>
    </section>

    <aside class="xl:col-span-5 bg-white border border-slate-200 rounded-3xl p-6 shadow-sm min-h-[430px]" id="appointment-detail">
      <div id="detail-empty" class="h-full min-h-[360px] grid place-items-center text-center"><div><div class="w-14 h-14 mx-auto rounded-2xl bg-slate-100 text-slate-400 grid place-items-center text-2xl">◌</div><h2 class="font-bold mt-4">Seleccioná una cita</h2><p class="text-sm text-slate-400 mt-2 max-w-xs">Vas a ver los datos del prospecto, contexto y las acciones operativas.</p></div></div>
      <div id="detail-content" class="hidden"><div class="flex items-center justify-between pb-4 border-b"><span class="text-xs font-semibold text-slate-400">Ficha de la cita</span><span id="detail-state" class="rounded-full px-2.5 py-1 text-[10px] font-semibold"></span></div><div class="flex gap-3 mt-5"><div id="detail-avatar" class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 grid place-items-center font-bold"></div><div><h2 id="detail-name" class="font-bold text-slate-900"></h2><p id="detail-contact" class="text-xs text-slate-500 mt-1"></p></div></div><dl class="space-y-4 mt-6 text-sm"><div><dt class="text-[10px] font-semibold text-slate-400">HORARIO</dt><dd id="detail-time" class="font-medium text-slate-700 mt-1"></dd></div><div><dt class="text-[10px] font-semibold text-slate-400">SERVICIO Y RESPONSABLE</dt><dd id="detail-service" class="font-medium text-slate-700 mt-1"></dd></div><div><dt class="text-[10px] font-semibold text-slate-400">MOTIVO / CONTEXTO</dt><dd id="detail-notes" class="text-slate-600 mt-1 leading-relaxed"></dd></div></dl><div class="mt-7 pt-5 border-t grid grid-cols-2 gap-2"><button type="button" onclick="setStatus('confirmada')" class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 text-xs font-semibold">Confirmar</button><button type="button" onclick="setStatus('cancelada_negocio')" class="rounded-xl bg-red-50 hover:bg-red-100 text-red-700 py-2.5 text-xs font-semibold">Cancelar</button></div><button type="button" onclick="openBooking(event, selectedAppointment)" class="w-full mt-2 rounded-xl border border-slate-200 hover:bg-slate-50 py-2.5 text-xs font-semibold">Crear nueva cita para este cliente</button></div>
    </aside>
  </div>
  <?php else: ?>

  <section class="bg-white border border-slate-200 rounded-3xl p-5 md:p-6 shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-5">
      <div><h2 class="font-bold text-slate-900">Estructura de disponibilidad</h2><p class="text-xs text-slate-400 mt-1">Sucursal → agenda → horarios. Esto es lo que la IA consulta antes de ofrecer o confirmar una cita.</p></div>
      <div class="flex gap-2 flex-wrap"><button type="button" onclick="openEntity('sucursales')" class="text-xs font-semibold text-slate-700 border border-slate-200 rounded-xl px-3 py-2 hover:bg-slate-50">+ Sucursal</button><button type="button" onclick="openEntity('agendas')" class="text-xs font-semibold text-emerald-700 border border-emerald-100 bg-emerald-50 rounded-xl px-3 py-2 hover:bg-emerald-100">+ Agenda</button><button type="button" onclick="openEntity('servicios')" class="text-xs font-semibold text-indigo-700 border border-indigo-100 bg-indigo-50 rounded-xl px-3 py-2 hover:bg-indigo-100">+ Servicio</button></div>
    </div>
    <?php $dayNames=['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado']; ?>
    <?php if (!$branches): ?>
      <div class="border border-dashed border-slate-200 rounded-2xl p-6 text-sm text-slate-500">Todavía no hay sucursales. Creá una para luego asignarle una o más agendas.</div>
    <?php else: ?>
      <div class="grid lg:grid-cols-2 gap-4">
        <?php foreach ($branches as $branch): ?>
          <?php $branchAgendas=array_values(array_filter($agendas, fn($item)=>(int)$item['sucursal_id']===(int)$branch['id'])); ?>
          <article class="rounded-2xl border border-slate-200 p-4">
            <div class="flex items-start justify-between gap-3"><div><h3 class="font-semibold text-slate-900"><?= htmlspecialchars($branch['nombre']) ?></h3><?php if (!empty($branch['direccion'])): ?><p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($branch['direccion']) ?></p><?php endif; ?></div><span class="text-[11px] font-semibold text-slate-500 bg-slate-100 rounded-full px-2.5 py-1"><?= count($branchAgendas) ?> <?= count($branchAgendas)===1?'agenda':'agendas' ?></span></div>
            <div class="mt-4 space-y-2">
              <?php if (!$branchAgendas): ?><p class="text-xs text-amber-700 bg-amber-50 rounded-xl p-3">Esta sucursal aún no tiene agenda reservable.</p><?php endif; ?>
              <?php foreach ($branchAgendas as $item): ?>
                <?php $agendaHours=array_values(array_filter($hours, fn($hour)=>(int)$hour['agenda_id']===(int)$item['id'])); ?>
                <div class="rounded-xl bg-slate-50 border border-slate-100 p-3"><div class="flex justify-between gap-3"><div><p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($item['nombre']) ?></p><?php if (!empty($item['descripcion'])): ?><p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($item['descripcion']) ?></p><?php endif; ?></div><span class="text-[11px] text-slate-500 whitespace-nowrap">buffer <?= (int)$item['buffer_minutes'] ?> min</span></div><p class="text-xs text-slate-500 mt-2"><?php if ($agendaHours): ?><?php foreach ($agendaHours as $i=>$hour): ?><?= $i ? ' · ' : '' ?><?= $dayNames[(int)$hour['dia_semana']] ?> <?= substr($hour['hora_inicio'],0,5) ?>–<?= substr($hour['hora_fin'],0,5) ?><?php endforeach; ?><?php else: ?><span class="text-amber-700">Sin horarios: la IA no ofrecerá turnos para esta agenda.</span><?php endif; ?></p></div>
              <?php endforeach; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="bg-white border border-slate-200 rounded-3xl p-5 md:p-6 shadow-sm">
    <div class="flex items-center justify-between gap-3 mb-5"><div><h2 class="font-bold text-slate-900">Servicios</h2><p class="text-xs text-slate-400 mt-1">Definen duración y condiciones de lo que se puede reservar.</p></div><button type="button" onclick="openEntity('servicios')" class="text-xs font-semibold text-indigo-700">+ Añadir servicio</button></div>
    <?php if (!$services): ?>
      <div class="border border-dashed border-slate-200 rounded-2xl p-6 text-sm text-slate-500">Todavía no hay servicios. La IA no podrá confirmar una cita hasta que exista al menos uno.</div>
    <?php else: ?>
      <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3"><?php foreach ($services as $service): ?><article class="rounded-2xl border border-slate-100 bg-slate-50 p-4"><div class="flex items-start justify-between gap-3"><div><h3 class="font-semibold text-sm text-slate-800"><?= htmlspecialchars($service['nombre']) ?></h3><p class="text-xs text-slate-500 mt-1"><?= (int)$service['duracion_minutos'] ?> minutos</p></div><span class="text-[10px] font-semibold rounded-full px-2 py-1 <?= !empty($service['activo']) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-500' ?>"><?= !empty($service['activo']) ? 'Activo' : 'Inactivo' ?></span></div></article><?php endforeach; ?></div>
    <?php endif; ?>
  </section>

  <section class="bg-white border border-slate-200 rounded-3xl p-5 md:p-6 shadow-sm"><div class="flex items-center justify-between mb-5"><div><h2 class="font-bold">Reglas generales</h2><p class="text-xs text-slate-400 mt-1">Anticipación y granularidad de los turnos. El buffer operativo se define por agenda.</p></div><button type="button" onclick="openHours(event)" class="text-xs font-semibold text-emerald-700">+ Añadir horario</button></div><form onsubmit="saveSettings(event)" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4"><label class="text-xs text-slate-500">Intervalo de turnos (min)<input name="slot_minutes" type="number" min="5" value="<?= (int)$settings['slot_minutes'] ?>" class="mt-1 w-full border border-slate-200 rounded-xl p-2.5 text-sm"></label><label class="text-xs text-slate-500">Anticipación mínima (h)<input name="min_notice_hours" type="number" min="0" value="<?= (int)$settings['min_notice_hours'] ?>" class="mt-1 w-full border border-slate-200 rounded-xl p-2.5 text-sm"></label><label class="text-xs text-slate-500">Anticipación máxima (días)<input name="max_advance_days" type="number" min="1" value="<?= (int)$settings['max_advance_days'] ?>" class="mt-1 w-full border border-slate-200 rounded-xl p-2.5 text-sm"></label><div class="flex items-end"><button class="w-full bg-slate-900 hover:bg-slate-800 text-white rounded-xl py-2.5 text-sm font-semibold">Guardar reglas</button></div></form></section>
  <?php endif; ?>
</div>
<?php endif; ?>

<div id="agenda-modal" class="hidden fixed inset-0 z-50 bg-slate-950/40 p-4 overflow-auto"><div class="bg-white max-w-lg mx-auto mt-10 rounded-3xl p-6 shadow-xl"><div class="flex justify-between gap-4"><h2 id="agenda-modal-title" class="font-bold text-lg"></h2><button type="button" onclick="closeAgendaModal()" class="text-slate-400">✕</button></div><div id="agenda-modal-body" class="mt-5"></div></div></div>

<?php if (!$agendaInitError): ?>
<script>
const agendaDate=<?= json_encode($date) ?>;
const agendaTab=<?= json_encode($tab) ?>;
const services=<?= json_encode($services, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
const agendas=<?= json_encode($agendas, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
const branches=<?= json_encode($branches, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
const appointments=<?= json_encode($overview['appointments'], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
let selectedAppointment=null;
const agendaModal=document.getElementById('agenda-modal'), agendaModalTitle=document.getElementById('agenda-modal-title'), agendaModalBody=document.getElementById('agenda-modal-body');
const formDataObject=form=>Object.fromEntries(new FormData(form));
const esc=value=>String(value??'').replace(/[&<>"']/g, x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[x]));
async function api(payload){const r=await fetch('ajax/agenda.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});const j=await r.json();if(!j.success)throw new Error(j.error||'No se pudo completar la operación.');return j}
function stop(e){if(e){e.preventDefault();e.stopPropagation()}}
function goToDate(value){if(value) location.href='agenda.php?tab='+encodeURIComponent(agendaTab)+'&date='+encodeURIComponent(value)}
function openModal(title, body){agendaModalTitle.textContent=title;agendaModalBody.innerHTML=body;agendaModal.classList.remove('hidden')}
function closeAgendaModal(){agendaModal.classList.add('hidden')}
function selectAppointment(id,e){stop(e);selectedAppointment=appointments.find(x=>Number(x.id)===Number(id));if(!selectedAppointment)return;document.querySelectorAll('.appointment-card').forEach(x=>x.classList.toggle('ring-2',Number(x.dataset.id)===Number(id)));document.getElementById('detail-empty').classList.add('hidden');document.getElementById('detail-content').classList.remove('hidden');const a=selectedAppointment;const label=String(a.estado).replaceAll('_',' ');const pending=a.estado==='pendiente_confirmacion';document.getElementById('detail-state').textContent=label;document.getElementById('detail-state').className='rounded-full px-2.5 py-1 text-[10px] font-semibold '+(pending?'bg-amber-50 text-amber-700':'bg-emerald-50 text-emerald-700');const name=a.nombre_cliente||a.telefono||'Sin identificar';document.getElementById('detail-name').textContent=name;document.getElementById('detail-avatar').textContent=name.split(' ').map(x=>x[0]).join('').slice(0,2).toUpperCase();document.getElementById('detail-contact').textContent=[a.telefono,a.email,a.canal].filter(Boolean).join(' · ');document.getElementById('detail-time').textContent=a.inicio+' — '+a.fin;document.getElementById('detail-service').textContent=[a.servicio,a.agenda||a.sucursal||'Sin asignar'].filter(Boolean).join(' · ');document.getElementById('detail-notes').textContent=a.motivo||a.observaciones||'Sin contexto adicional registrado.'}
async function setStatus(status){if(!selectedAppointment)return;try{await api({action:'status',id:selectedAppointment.id,status});location.reload()}catch(err){alert(err.message)}}
function openBooking(e, prefill=null){stop(e);const a=prefill||{};openModal('Nueva cita',`<form class="space-y-3" onsubmit="book(event)"><label class="block text-sm">Cliente<input required name="nombre_cliente" value="${esc(a.nombre_cliente||'')}" class="mt-1 border rounded-xl p-2.5 w-full"></label><label class="block text-sm">Teléfono<input name="telefono" value="${esc(a.telefono||'')}" class="mt-1 border rounded-xl p-2.5 w-full"></label><label class="block text-sm">Servicio<select required name="servicio_id" class="mt-1 border rounded-xl p-2.5 w-full">${services.filter(s=>Number(s.activo)).map(s=>`<option value="${s.id}">${esc(s.nombre)} · ${s.duracion_minutos} min</option>`).join('')}</select></label><label class="block text-sm">Agenda<select required name="agenda_id" class="mt-1 border rounded-xl p-2.5 w-full"><option value="">Elegí una agenda</option>${agendas.filter(a=>Number(a.activo)).map(a=>`<option value="${a.id}" ${Number(a.id)===Number(prefill?.agenda_id)?'selected':''}>${esc(a.sucursal)} · ${esc(a.nombre)} · buffer ${a.buffer_minutes} min</option>`).join('')}</select></label><label class="block text-sm">Fecha y hora<input required type="datetime-local" name="inicio" value="${agendaDate}T09:00" class="mt-1 border rounded-xl p-2.5 w-full"></label><label class="block text-sm">Motivo<textarea name="motivo" class="mt-1 border rounded-xl p-2.5 w-full"></textarea></label><button class="w-full bg-emerald-600 text-white rounded-xl p-3 font-semibold">Crear cita</button></form>`)}
async function book(e){e.preventDefault();try{await api({...formDataObject(e.target),action:'create',canal:'manual'});location.reload()}catch(err){alert(err.message)}}
function openBlock(e){stop(e);openModal('Bloquear agenda',`<form class="space-y-3" onsubmit="saveBlock(event)"><label class="block text-sm">Motivo<input required name="motivo" placeholder="Reunión interna, vacaciones, descanso…" class="mt-1 border rounded-xl p-2.5 w-full"></label><label class="block text-sm">Inicio<input required type="datetime-local" name="inicio" value="${agendaDate}T12:00" class="mt-1 border rounded-xl p-2.5 w-full"></label><label class="block text-sm">Fin<input required type="datetime-local" name="fin" value="${agendaDate}T13:00" class="mt-1 border rounded-xl p-2.5 w-full"></label><label class="block text-sm">Agenda<select required name="agenda_id" class="mt-1 border rounded-xl p-2.5 w-full">${agendas.filter(a=>Number(a.activo)).map(a=>`<option value="${a.id}">${esc(a.sucursal)} · ${esc(a.nombre)}</option>`).join('')}</select></label><button class="w-full bg-slate-900 text-white rounded-xl p-3 font-semibold">Bloquear horario</button></form>`)}
async function saveBlock(e){e.preventDefault();try{await api({...formDataObject(e.target),action:'block'});location.reload()}catch(err){alert(err.message)}}
function openHours(e){stop(e);openModal('Añadir horario',`<form class="space-y-3" onsubmit="saveHours(event)"><label class="block text-sm">Agenda<select required name="agenda_id" class="mt-1 border rounded-xl p-2.5 w-full"><option value="">Elegí una agenda</option>${agendas.map(a=>`<option value="${a.id}">${esc(a.sucursal)} · ${esc(a.nombre)}</option>`).join('')}</select></label><label class="block text-sm">Día<select name="dia_semana" class="mt-1 border rounded-xl p-2.5 w-full">${['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'].map((d,i)=>`<option value="${i}">${d}</option>`).join('')}</select></label><div class="grid grid-cols-2 gap-3"><label class="text-sm">Desde<input required type="time" name="hora_inicio" class="mt-1 border rounded-xl p-2.5 w-full"></label><label class="text-sm">Hasta<input required type="time" name="hora_fin" class="mt-1 border rounded-xl p-2.5 w-full"></label></div><button class="w-full bg-emerald-600 text-white rounded-xl p-3 font-semibold">Guardar horario</button></form>`)}
async function saveHours(e){e.preventDefault();try{await api({...formDataObject(e.target),action:'save_hours'});location.reload()}catch(err){alert(err.message)}}
function openSetup(e){stop(e);openModal('Configurar operación',`<div class="space-y-3"><p class="text-sm text-slate-500">Primero definí sucursales; dentro de cada una creá las agendas que se pueden reservar.</p><button type="button" onclick="openEntity('sucursales')" class="w-full text-left border rounded-xl p-4 hover:border-emerald-500"><b>Sucursales</b><span class="block text-xs text-slate-500 mt-1">Dónde se atiende.</span></button><button type="button" onclick="openEntity('agendas')" class="w-full text-left border rounded-xl p-4 hover:border-emerald-500"><b>Agendas</b><span class="block text-xs text-slate-500 mt-1">Persona, sala o recurso que no puede superponerse.</span></button><button type="button" onclick="openEntity('servicios')" class="w-full text-left border rounded-xl p-4 hover:border-emerald-500"><b>Servicios</b><span class="block text-xs text-slate-500 mt-1">Duración de cada atención.</span></button></div>`)}
function openEntity(type){if(type==='sucursales'){openModal('Nueva sucursal',`<form class="space-y-3" onsubmit="saveEntity(event,'sucursales')"><label class="block text-sm">Nombre<input required name="nombre" class="mt-1 border rounded-xl p-2.5 w-full"></label><label class="block text-sm">Dirección<input name="direccion" class="mt-1 border rounded-xl p-2.5 w-full"></label><input type="hidden" name="activo" value="1"><button class="w-full bg-emerald-600 text-white rounded-xl p-3 font-semibold">Guardar sucursal</button></form>`);return}if(type==='agendas'){openModal('Nueva agenda',`<form class="space-y-3" onsubmit="saveEntity(event,'agendas')"><label class="block text-sm">Sucursal<select required name="sucursal_id" class="mt-1 border rounded-xl p-2.5 w-full"><option value="">Elegí una sucursal</option>${branches.filter(b=>Number(b.activo)).map(b=>`<option value="${b.id}">${esc(b.nombre)}</option>`).join('')}</select></label><label class="block text-sm">Nombre de la agenda<input required name="nombre" placeholder="Dra. Martínez, Cabina 1, Sala de reuniones…" class="mt-1 border rounded-xl p-2.5 w-full"></label><label class="block text-sm">Descripción<input name="descripcion" class="mt-1 border rounded-xl p-2.5 w-full"></label><label class="block text-sm">Buffer entre citas (min)<input required type="number" min="0" name="buffer_minutes" value="0" class="mt-1 border rounded-xl p-2.5 w-full"></label><input type="hidden" name="activo" value="1"><button class="w-full bg-emerald-600 text-white rounded-xl p-3 font-semibold">Guardar agenda</button></form>`);return}openModal('Nuevo servicio',`<form class="space-y-3" onsubmit="saveEntity(event,'servicios')"><label class="block text-sm">Nombre<input required name="nombre" class="mt-1 border rounded-xl p-2.5 w-full"></label><label class="block text-sm">Duración (min)<input required type="number" min="5" name="duracion_minutos" value="30" class="mt-1 border rounded-xl p-2.5 w-full"></label><input type="hidden" name="activo" value="1"><button class="w-full bg-emerald-600 text-white rounded-xl p-3 font-semibold">Guardar servicio</button></form>`)}
async function saveEntity(e,entity){e.preventDefault();try{await api({...formDataObject(e.target),action:'save_entity',entity});location.reload()}catch(err){alert(err.message)}}
async function saveSettings(e){e.preventDefault();try{await api({...formDataObject(e.target),action:'save_settings'});alert('Reglas actualizadas.')}catch(err){alert(err.message)}}
</script>
<?php endif; ?>
<?php $mainContent=ob_get_clean(); require __DIR__.'/includes/layout_tailwind.php';
