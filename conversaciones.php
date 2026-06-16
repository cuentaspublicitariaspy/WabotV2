<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Database.php';
requireLogin();

$user = getUsuarioActual();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversaciones - Wabot</title>
    <link rel="stylesheet" href="style.css">
    <style>.main-content.chat-page{overflow:hidden}.chat-layout{flex:1}</style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-brand">
            <h2>&#128172; Wabot</h2>
            <span>WhatsApp Multiagente</span>
        </div>
        <div class="sidebar-user">
            <div class="avatar"><?= strtoupper(substr($user['nombre'], 0, 1)) ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($user['nombre']) ?></div>
                <div class="user-rol"><?= $user['rol'] ?></div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php"><span class="nav-icon">&#128202;</span> Dashboard</a>
            <a href="conversaciones.php" class="active"><span class="nav-icon">&#128172;</span> Conversaciones</a>
            <a href="conocimiento.php"><span class="nav-icon">&#128196;</span> Conocimiento</a>
            <?php if ($user['rol'] === 'admin'): ?>
                <div class="nav-section">Administración</div>
                <a href="estadisticas.php"><span class="nav-icon">&#128200;</span> Estadísticas</a>
                <a href="plantillas.php"><span class="nav-icon">&#128233;</span> Plantillas</a>
                <a href="usuarios.php"><span class="nav-icon">&#128101;</span> Usuarios</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php">&#128682; Cerrar sesión</a>
        </div>
    </aside>
    <div class="main-content chat-page">
        <div class="chat-layout" id="chat-layout">
            <div class="chat-sidebar">
                <div class="sidebar-header">
                    <input type="text" id="search-input" placeholder="Buscar conversación..." oninput="loadConversations()">
                </div>
                <div class="sidebar-filters">
                    <button class="filter-btn active" data-filter="" onclick="setFilter(this, '')">Todas</button>
                    <button class="filter-btn" data-filter="pendiente" onclick="setFilter(this, 'pendiente')">Pendientes</button>
                    <button class="filter-btn" data-filter="respondido" onclick="setFilter(this, 'respondido')">Respondidas</button>
                </div>
                <div class="agent-status" id="agent-status"></div>
                <div class="conversation-list" id="conversation-list">
                    <div class="loading-chats">Cargando conversaciones...</div>
                </div>
            </div>
            <div class="chat-main" id="chat-main">
                <div class="chat-placeholder" id="chat-placeholder">
                    <div class="placeholder-icon">&#128172;</div>
                    <h3>Seleccioná una conversación</h3>
                    <p>Hacé clic en un chat de la izquierda para ver los mensajes</p>
                </div>
                <div class="chat-view" id="chat-view" style="display:none;">
                    <div class="chat-header" id="chat-header"></div>
                    <div class="chat-messages" id="chat-messages"></div>
                    <div class="chat-input-area">
                        <textarea id="reply-input" rows="2" placeholder="Escribí tu respuesta..." onkeydown="handleKey(event)"></textarea>
                        <button class="send-btn" onclick="sendReply()">&#10148;</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        let currentConversacionId = null, currentFilter = '', currentSearch = '', pollInterval = null, sessionInterval = null;
        function loadConversations() {
            currentSearch = document.getElementById('search-input').value;
            fetch('ajax/poll.php?filter='+currentFilter+'&search='+currentSearch)
                .then(r=>r.json()).then(data=>{
                    const list = document.getElementById('conversation-list');
                    if (data.conversaciones.length === 0) { list.innerHTML = '<div class="empty-chats">No hay conversaciones aún</div>'; }
                    else {
                        let html = '';
                        data.conversaciones.forEach(c => {
                            const active = c.id === currentConversacionId ? 'conv-item-active' : '';
                            const estadoLabel = c.estado === 'pendiente' ? '<span class="badge-pendiente">Pendiente</span>' : '';
                            const lastMsg = c.ultimo_mensaje ? c.ultimo_mensaje.substring(0,60)+(c.ultimo_mensaje.length>60?'...':'') : '';
                            const time = c.ultimo_tiempo ? formatTime(c.ultimo_tiempo) : '';
                            const asignado = c.asignado_a_nombre ? `<span class="badge-asignado">${esc(c.asignado_a_nombre)}</span>` : '';
                            const depto = c.departamento ? `<span class="badge-depto">${esc(c.departamento)}</span>` : '';
                            html += `<div class="conv-item ${active}" onclick="selectConversacion(${c.id})">
                                <div class="conv-avatar">${getInitial(c.wa_name||c.wa_phone)}</div>
                                <div class="conv-info">
                                    <div class="conv-top"><span class="conv-name">${esc(c.wa_name||c.wa_phone)}</span><span class="conv-time">${time}</span></div>
                                    <div class="conv-bottom"><span class="conv-preview">${esc(lastMsg)}</span>${estadoLabel}</div>
                                    <div class="conv-meta">${asignado} ${depto}</div>
                                </div>
                            </div>`;
                        });
                        list.innerHTML = html;
                    }
                    if (data.nuevosMensajes && currentConversacionId) loadMessages(currentConversacionId);
                    const sd = document.getElementById('agent-status');
                    if (data.agentes_activos && data.agentes_activos.length > 0) {
                        let h = '<div class="agent-list">';
                        data.agentes_activos.forEach(a => { h += `<span class="agent-online"><span class="status-dot ok"></span> ${esc(a.nombre)}</span>`; });
                        sd.innerHTML = h + '</div>';
                    } else { sd.innerHTML = '<div class="agent-list"><span class="agent-offline">&#9203; Solo IA disponible</span></div>'; }
                });
        }
        function loadMessages(id) {
            fetch('ajax/poll.php?conversacion_id='+id).then(r=>r.json()).then(data=>{
                const c = document.getElementById('chat-messages'), h = document.getElementById('chat-header');
                const p = document.getElementById('chat-placeholder'), v = document.getElementById('chat-view');
                p.style.display='none'; v.style.display='flex';
                const conv = data.conversacion;
                const depto = conv.departamento ? ` · ${esc(conv.departamento)}` : '';
                const asig = conv.asignado_a_nombre ? ` · Asignado: ${esc(conv.asignado_a_nombre)}` : '';
                h.innerHTML = `<div class="chat-header-info"><span class="chat-header-name">${esc(conv.wa_name||conv.wa_phone)}</span><span class="chat-header-phone">${esc(conv.wa_phone)}${depto}${asig}</span></div>`;
                if (data.mensajes.length===0) { c.innerHTML='<div class="empty-msg">No hay mensajes</div>'; return; }
                let html='';
                data.mensajes.forEach(m=>{
                    const isIn=m.direccion==='in';
                    const r=m.respondido_por_nombre?`<div class="msg-responder">${esc(m.respondido_por_nombre)}</div>`:'';
                    html+=`<div class="msg ${isIn?'msg-in':'msg-out'}"><div class="msg-content">${esc(m.contenido)}</div>${r}<div class="msg-time">${formatTime(m.created_at)}</div></div>`;
                });
                c.innerHTML=html; c.scrollTop=c.scrollHeight;
            });
        }
        function selectConversacion(id) { currentConversacionId=id; loadMessages(id); loadConversations(); document.getElementById('reply-input').focus(); }
        function sendReply() {
            const i=document.getElementById('reply-input'), t=i.value.trim();
            if(!t||!currentConversacionId) return;
            i.value='';
            document.getElementById('chat-messages').innerHTML+='<div class="msg msg-out"><div class="msg-content">Enviando...</div></div>';
            const p=new URLSearchParams({conversacion_id:currentConversacionId,mensaje:t});
            fetch('ajax/send.php',{method:'POST',body:p}).then(()=>{loadMessages(currentConversacionId);loadConversations();});
        }
        function handleKey(e) { if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendReply();} }
        function setFilter(btn,f){ currentFilter=f; document.querySelectorAll('.filter-btn').forEach(b=>b.classList.remove('active')); btn.classList.add('active'); loadConversations(); }
        function getInitial(n){return n?n.charAt(0).toUpperCase():'?';}
        function formatTime(d){if(!d)return'';const o=new Date(d.replace(' ','T')+(d.includes('Z')?'':'Z'));return(Math.abs(new Date()-o)/1000<86400)?o.toLocaleTimeString('es',{hour:'2-digit',minute:'2-digit'}):o.toLocaleDateString('es',{day:'numeric',month:'short'});}
        function esc(s){const d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
        function heartbeat(){ fetch('ajax/session.php',{method:'POST'}).catch(()=>{}); }
        loadConversations(); pollInterval=setInterval(loadConversations,3000); sessionInterval=setInterval(heartbeat,30000); heartbeat();
    </script>
</body>
</html>
