<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/ChatManager.php';
require_once __DIR__ . '/includes/AgentRouter.php';
requireLogin();

$user = getUsuarioActual();
$activePage = 'conversaciones';
$pageTitle = 'Conversaciones';
$fullHeight = true;
ob_start();
?>
<div class="d-flex flex-fill h-100 overflow-hidden">
  <div class="w-[28rem] border-r border-slate-100 d-flex flex-column bg-white shrink-0">
    <div class="p-4 border-b border-slate-100">
      <input type="text" id="search-input" class="w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Buscar conversación..." oninput="loadConversations()">
    </div>
    <div class="px-4 py-2.5 border-b border-slate-100 d-flex gap-2">
      <button class="text-xs px-4 py-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 font-medium transition filter-btn active" data-filter="" onclick="setFilter(this, '')">Todas</button>
      <button class="text-xs px-4 py-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 font-medium transition filter-btn" data-filter="pendiente" onclick="setFilter(this, 'pendiente')">Pendientes</button>
      <button class="text-xs px-4 py-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 font-medium transition filter-btn" data-filter="respondido" onclick="setFilter(this, 'respondido')">Respondidas</button>
    </div>
    <div id="agent-status" class="px-4 py-2 border-b border-slate-100 text-xs" style="min-height:28px;"></div>
    <div id="conversation-list" class="overflow-auto" style="flex:1 1 0;min-height:0">
      <div class="text-center text-slate-400 p-4 text-sm">Cargando conversaciones...</div>
    </div>
  </div>
  <div class="flex-fill d-flex flex-column bg-white">
    <div id="chat-placeholder" class="d-flex flex-column align-items-center justify-content-center text-slate-400" style="flex:1 1 0;min-height:0">
      <div class="mb-3 w-16 h-16 bg-slate-100 rounded-full d-flex align-items-center justify-content-center">
        <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
      </div>
      <h5 class="text-slate-600 font-semibold">Seleccioná una conversación</h5>
      <p class="text-sm text-slate-400 mt-1">Hacé clic en un chat de la izquierda para ver los mensajes</p>
    </div>
    <div id="chat-view" class="d-none d-flex flex-column" style="flex:1 1 0;min-height:0">
      <div id="chat-header" class="px-4 py-3 border-b border-slate-100 d-flex align-items-center" style="min-height:50px;background:#fff;"></div>
      <div id="chat-messages" class="overflow-auto p-4" style="flex:1 1 0;min-height:0;background:#f8fafc" onscroll="maybeLoadMore(this)"></div>
      <div class="px-4 py-3 border-t border-slate-100 d-flex gap-2 align-items-center bg-white">
        <textarea id="reply-input" class="flex-1 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" rows="1" placeholder="Escribí tu respuesta..." style="resize:none;" onkeydown="handleKey(event)"></textarea>
        <button class="w-10 h-10 bg-emerald-600 text-white rounded-full d-flex align-items-center justify-content-center border-0 hover:bg-emerald-700 transition shadow-lg shadow-emerald-200" onclick="sendReply()">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"></path></svg>
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$userIdJs = (int)$user['id'];
$extraScripts = <<<'EOS'
<style>
#chat-messages .msg-in, #chat-messages .msg-out { max-width:75%; margin-bottom:4px; clear:both; }
#chat-messages .msg-in { float:left; }
#chat-messages .msg-out { float:right; }
#chat-messages .msg-content { padding:8px 14px; border-radius:12px; position:relative; word-wrap:break-word; }
#chat-messages .msg-in .msg-content { background:#f1f5f9; border-bottom-left-radius:2px; }
#chat-messages .msg-out .msg-content { background:#10b981; color:#fff; border-bottom-right-radius:2px; }
#chat-messages .msg-time { font-size:10px; color:#94a3b8; margin-top:2px; }
#chat-messages .msg-out .msg-time { color:#a7f3d0; }
#chat-messages .msg-responder { font-size:10px; color:#059669; font-weight:600; }
#chat-messages .msg-out .msg-responder { color:#a7f3d0; }
.conv-item { padding:12px 16px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; gap:12px; align-items:center; }
.conv-item:hover { background:#f8fafc; }
.conv-item-active { background:#f1f5f9; }
.conv-avatar { width:42px; height:42px; border-radius:50%; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-weight:600; color:#64748b; flex-shrink:0; }
.conv-info { flex:1; min-width:0; }
.conv-top { display:flex; justify-content:space-between; }
.conv-name { font-size:14px; font-weight:500; color:#1e293b; }
.conv-time { font-size:11px; color:#94a3b8; }
.conv-bottom { display:flex; gap:6px; align-items:center; }
.conv-preview { font-size:12px; color:#94a3b8; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.conv-meta { display:flex; gap:4px; margin-top:2px; }
.badge-pendiente { background:#f59e0b; color:#fff; font-size:10px; padding:1px 6px; border-radius:10px; }
.badge-asignado { background:#6366f1; color:#fff; font-size:10px; padding:1px 6px; border-radius:10px; }
.badge-depto { background:#f1f5f9; color:#64748b; font-size:10px; padding:1px 6px; border-radius:10px; }
.badge-canal { font-size:10px; padding:1px 6px; border-radius:10px; font-weight:600; }
.badge-whatsapp { background:#dcfce7; color:#15803d; }
.badge-chatbot { background:#ede9fe; color:#6d28d9; }
.filter-btn.active { background:#10b981; border-color:#10b981; color:#fff; }
.d-flex { display:flex; }
.d-none { display:none; }
.flex-fill { flex:1; }
.align-items-center { align-items:center; }
.justify-content-center { justify-content:center; }
.h-100 { height:100%; }
.shrink-0 { flex-shrink:0; }
</style>
<script>
var currentConversacionId = null, currentCanal = 'whatsapp', currentFilter = '', currentSearch = '', pollInterval = null, sessionInterval = null, currentUserId =
EOS;
$extraScripts .= $userIdJs;
$extraScripts .= <<<'EOS'
;
function loadConversations() {
    currentSearch = document.getElementById('search-input').value;
    fetch('ajax/poll.php?filter='+currentFilter+'&search='+currentSearch)
        .then(r=>r.json()).then(data=>{
            const list = document.getElementById('conversation-list');
            if (!data.conversaciones || data.conversaciones.length === 0) { list.innerHTML = '<div class="text-center text-slate-400 p-4 text-sm">No hay conversaciones a\u00fan</div>'; }
            else {
                list.innerHTML = '';
                data.conversaciones.forEach(function(c) {
                    var active = c.id == currentConversacionId && c.canal === currentCanal ? ' conv-item-active' : '';
                    var estadoLabel = c.estado === 'pendiente' ? '<span class="badge-pendiente">Pendiente</span>' : '';
                    var canal = c.canal === 'chatbot' ? '<span class="badge-canal badge-chatbot">Chatbot</span>' : '<span class="badge-canal badge-whatsapp">WhatsApp</span>';
                    var lastMsg = c.ultimo_mensaje ? c.ultimo_mensaje.substring(0,60)+(c.ultimo_mensaje.length>60?'...':'') : '';
                    var time = c.ultimo_tiempo ? formatTime(c.ultimo_tiempo) : '';
                    var tuyo = c.canal === 'chatbot' ? '' : (c.asignado_a == currentUserId ? '<span class="badge-asignado" style="background:#00a884;">Tuyo</span>' : (c.asignado_a ? '' : '<span class="badge-asignado" style="background:#5b5ea6;">Disponible</span>'));
                    var asignado = c.asignado_a_nombre ? '<span class="badge-asignado">'+esc(c.asignado_a_nombre)+'</span>' : '';
                    var depto = c.departamento ? '<span class="badge-depto">'+esc(c.departamento)+'</span>' : '';
                    var d = document.createElement('div');
                    d.className = 'conv-item'+active;
                    d.onclick = function(){selectConversacion(c.canal, c.id);};
                    d.innerHTML = '<div class="conv-avatar">'+getInitial(c.wa_name||c.wa_phone)+'</div>'
                        + '<div class="conv-info">'
                        + '<div class="conv-top"><span class="conv-name">'+esc(c.wa_name||c.wa_phone)+'</span><span class="conv-time">'+time+'</span></div>'
                        + '<div class="conv-bottom"><span class="conv-preview">'+esc(lastMsg)+'</span>'+estadoLabel+'</div>'
                        + '<div class="conv-meta">'+canal+tuyo+asignado+depto+'</div>'
                        + '</div>';
                    list.appendChild(d);
                });
            }
            if (data.nuevosMensajes && currentConversacionId) loadMessages(currentCanal, currentConversacionId);
            var sd = document.getElementById('agent-status');
            if (data.agentes_activos && data.agentes_activos.length > 0) {
                var h = '<div class="d-flex gap-1 flex-wrap">';
                data.agentes_activos.forEach(function(a) { h += '<span class="badge bg-success">'+esc(a.nombre)+'</span>'; });
                sd.innerHTML = h + '</div>';
            } else { sd.innerHTML = '<span class="text-slate-400">&#9203; Solo IA disponible</span>'; }
        }).catch(function(e){
            var list = document.getElementById('conversation-list');
            list.innerHTML = '<div class="text-center text-red-400 p-4 text-sm">Error al cargar conversaciones. Reintentando...</div>';
        });
}
function loadMessages(canal, id) {
    fetch('ajax/poll.php?canal='+encodeURIComponent(canal)+'&conversacion_id='+id).then(function(r){return r.json();}).then(function(data){
        var c = document.getElementById('chat-messages'), h = document.getElementById('chat-header');
        document.getElementById('chat-placeholder').classList.remove('d-flex');
        document.getElementById('chat-placeholder').classList.add('d-none');
        document.getElementById('chat-view').classList.remove('d-none');
        var conv = data.conversacion;
        if (!conv) return;
        var depto = conv.departamento ? ' &middot; '+esc(conv.departamento) : '';
        var asig = conv.asignado_a_nombre ? ' &middot; Asignado: '+esc(conv.asignado_a_nombre) : '';
        h.innerHTML = '<div><strong>'+esc(conv.wa_name||conv.wa_phone)+'</strong><br><span class="small text-secondary">'+esc(conv.wa_phone)+depto+asig+' &middot; '+(canal === 'chatbot' ? 'Chatbot' : 'WhatsApp')+'</span></div>';
        var input = document.getElementById('reply-input');
        var send = input.nextElementSibling;
        input.disabled = canal === 'chatbot'; send.disabled = canal === 'chatbot';
        input.placeholder = canal === 'chatbot' ? 'Conversación de Chatbot — respuesta automática IA' : 'Escribí tu respuesta...';
        if (data.mensajes.length===0) { c.innerHTML='<div class="text-center text-secondary p-4 small">No hay mensajes</div>'; return; }
        var html='';
        data.mensajes.forEach(function(m){
            var isIn=m.direccion==='in';
            var r=m.respondido_por_nombre?'<div class="msg-responder">'+esc(m.respondido_por_nombre)+'</div>':'';
            html+='<div class="msg '+(isIn?'msg-in':'msg-out')+'"><div class="msg-content">'+esc(m.contenido)+'</div>'+r+'<div class="msg-time">'+formatTime(m.created_at)+'</div></div>';
        });
            var wasNearBottom = c.scrollHeight - c.scrollTop - c.clientHeight < 80;
            c.innerHTML=html;
            if (wasNearBottom) c.scrollTop=c.scrollHeight;
    });
}
function selectConversacion(canal, id) { currentCanal=canal; currentConversacionId=id; loadMessages(canal,id); loadConversations(); if(canal==='whatsapp') document.getElementById('reply-input').focus(); }
function sendReply() {
    var i=document.getElementById('reply-input'), t=i.value.trim();
    if(!t||!currentConversacionId||currentCanal!=='whatsapp') return;
    i.value='';
    var p=new URLSearchParams({conversacion_id:currentConversacionId,mensaje:t});
    fetch('ajax/send.php',{method:'POST',body:p}).then(async function(r){
        if(!r.ok){
            var d=await r.json();
            if(d.error==='ventana_24h_expirada'){
                var tm='Plantillas disponibles:\n';
                (d.plantillas||[]).forEach(function(pt){tm+='\u2022 '+esc(pt.nombre)+'\n';});
                alert('Ventana de 24hs expirada.\nNo pod\u00e9s enviar mensajes libres.\nUs\u00e1 una plantilla desde la secci\u00f3n Plantillas.\n\n'+tm);
            } else {
                alert('Error: '+(d.error||'Error al enviar'));
            }
            loadMessages(currentCanal,currentConversacionId);loadConversations();return;
        }
        loadMessages(currentCanal,currentConversacionId);loadConversations();
    }).catch(function(){alert('Error de conexi\u00f3n');loadMessages(currentCanal,currentConversacionId);loadConversations();});
}
function handleKey(e) { if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendReply();} }
function setFilter(btn,f){ currentFilter=f; document.querySelectorAll('.filter-btn').forEach(function(b){b.classList.remove('active');}); btn.classList.add('active'); loadConversations(); }
function getInitial(n){return n?n.charAt(0).toUpperCase():'?';}
function formatTime(d){if(!d)return'';var o=new Date(d.replace(' ','T')+(d.includes('Z')?'':'Z'));return(Math.abs(new Date()-o)/1000<86400)?o.toLocaleTimeString('es',{hour:'2-digit',minute:'2-digit'}):o.toLocaleDateString('es',{day:'numeric',month:'short'});}
function esc(s){var e=document.createElement('div');e.textContent=s||'';return e.innerHTML;}
function heartbeat(){ fetch('ajax/session.php',{method:'POST'}).catch(function(){}); }
function maybeLoadMore(el){}
loadConversations(); pollInterval=setInterval(loadConversations,3000); sessionInterval=setInterval(heartbeat,30000); heartbeat();
</script>
EOS;
$mainContent = ob_get_clean();
require __DIR__ . '/includes/layout_tailwind.php';
