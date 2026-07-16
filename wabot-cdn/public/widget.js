(function () {
  var script = document.currentScript;
  if (!script) return;
  var apiKey = script.getAttribute('data-api-key');
  var apiBase = script.getAttribute('data-api-base') || 'https://wabot-cdn.vercel.app';
  var storeUrl = script.getAttribute('data-store') || '';
  var historyUrl = script.getAttribute('data-history') || '';
  if (!apiKey) return;

  var storageKey = 'wabot_messages_' + apiKey;
  var sessionKey = 'wabot_session_' + apiKey;
  var outboxKey = 'wabot_outbox_' + apiKey;
  var flushingOutbox = false;
  var sessionId = localStorage.getItem(sessionKey);
  if (!sessionId) {
    var bytes = new Uint8Array(32);
    if (window.crypto && window.crypto.getRandomValues) window.crypto.getRandomValues(bytes);
    else for (var b = 0; b < bytes.length; b++) bytes[b] = Math.floor(Math.random() * 256);
    sessionId = Array.prototype.map.call(bytes, function (byte) { return ('0' + byte.toString(16)).slice(-2); }).join('');
    localStorage.setItem(sessionKey, sessionId);
  }

  var color = '#4f46e5';
  var title = 'Asistente virtual';
  var subtitle = 'En línea para ayudarte';
  var agentPhoto = '';
  var rootId = 'wabot-chatbot-root';

  function messages() { try { return JSON.parse(localStorage.getItem(storageKey)) || []; } catch (e) { return []; } }
  function save(list) { try { localStorage.setItem(storageKey, JSON.stringify(list)); } catch (e) {} }
  function now() { return new Date().toLocaleTimeString('es-PY', { hour: '2-digit', minute: '2-digit' }); }
  function esc(value) { var el = document.createElement('div'); el.textContent = value || ''; return el.innerHTML; }
  function endpoint(name) { return apiBase.replace(/\/+$/, '') + '/api/widget/' + name; }

  function outbox() { try { return JSON.parse(localStorage.getItem(outboxKey)) || []; } catch (e) { return []; } }
  function saveOutbox(items) { try { localStorage.setItem(outboxKey, JSON.stringify(items)); } catch (e) {} }
  function persist(role, content) {
    var id = Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
    var items = outbox();
    items.push({ id: id, role: role, content: content });
    saveOutbox(items);
    flushOutbox();
  }
  function flushOutbox() {
    if (flushingOutbox || !storeUrl) return;
    var items = outbox();
    if (!items.length) return;
    flushingOutbox = true;
    var item = items[0];
    var request = new XMLHttpRequest();
    request.open('POST', storeUrl, true);
    request.setRequestHeader('Content-Type', 'application/json');
    request.onreadystatechange = function () {
      if (request.readyState !== 4) return;
      flushingOutbox = false;
      if (request.status >= 200 && request.status < 300) {
        var pending = outbox();
        if (pending.length && pending[0].id === item.id) pending.shift();
        else pending = pending.filter(function (entry) { return entry.id !== item.id; });
        saveOutbox(pending);
        flushOutbox();
      } else {
        window.setTimeout(flushOutbox, 2000);
      }
    };
    request.onerror = function () { flushingOutbox = false; window.setTimeout(flushOutbox, 2000); };
    request.send(JSON.stringify({ api_key: apiKey, session_id: sessionId, role: item.role, content: item.content, client_message_id: item.id }));
  }

  var html = '' +
    '<style>' +
    ':host{all:initial;display:block!important;position:fixed!important;inset:0!important;max-width:100%!important;max-height:100%!important;overflow:hidden!important;pointer-events:none!important;z-index:2147483647!important;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;isolation:isolate!important;contain:layout paint!important}' +
    '*,*:before,*:after{box-sizing:border-box;font-family:inherit;min-width:0}' +
    '.wc-shell{position:absolute;right:24px;bottom:24px;max-width:calc(100% - 48px);z-index:2147483646;display:flex;flex-direction:column;align-items:flex-end;pointer-events:none;overflow:visible}' +
    '.wc-panel{width:min(380px,calc(100vw - 48px));max-width:100%;height:550px;max-height:calc(100vh - 116px);margin-bottom:18px;display:none;flex-direction:column;background:#fff;border:1px solid #e8edf5;border-radius:24px;overflow:hidden;box-shadow:0 22px 55px rgba(15,23,42,.28);transform:translateY(18px) scale(.96);opacity:0;transition:transform .22s ease,opacity .22s ease;pointer-events:auto}' +
    '.wc-panel.open{display:flex;transform:translateY(0) scale(1);opacity:1}' +
    '.wc-head{min-height:82px;padding:15px 16px;color:#fff;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(105deg,var(--wc-color),color-mix(in srgb,var(--wc-color) 78%,#111827));box-shadow:0 2px 8px rgba(15,23,42,.12)}' +
    '.wc-agent{display:flex;align-items:center;gap:12px;min-width:0}.wc-avatar{width:43px;height:43px;display:grid;place-items:center;border-radius:50%;overflow:visible;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);position:relative;flex:0 0 auto}.wc-avatar:after{content:"";position:absolute;right:-1px;bottom:-1px;width:11px;height:11px;border-radius:50%;background:#4ade80;border:2px solid #fff;z-index:2}.wc-avatar svg{width:20px;height:20px}.wc-avatar img,.wc-mini-avatar img{width:100%;height:100%;border-radius:50%;object-fit:cover;display:block}.wc-avatar img{display:none}.wc-avatar.has-photo img{display:block}.wc-avatar.has-photo svg{display:none}.wc-name{display:flex;align-items:center;gap:6px;font-size:14px;font-weight:750;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.wc-bot{font-size:9px;letter-spacing:.07em;font-weight:800;text-transform:uppercase;background:rgba(255,255,255,.22);padding:3px 5px;border-radius:5px}.wc-sub{margin-top:4px;font-size:11px;color:rgba(255,255,255,.86);font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.wc-controls{display:flex;gap:4px}.wc-icon{width:32px;height:32px;padding:0;border:0;border-radius:50%;background:transparent;color:#fff;cursor:pointer;display:grid;place-items:center;opacity:.84}.wc-icon:hover{background:rgba(255,255,255,.14);opacity:1}.wc-icon svg{width:18px;height:18px}' +
    '.wc-content{flex:1;min-height:0;max-width:100%;display:flex;flex-direction:column;background:#f8fafc;overflow:hidden}.wc-messages{flex:1;max-width:100%;overflow-y:auto;overflow-x:hidden;padding:17px 16px;scroll-behavior:smooth}.wc-messages::-webkit-scrollbar{width:5px}.wc-messages::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:99px}.wc-day{text-align:center;margin:1px 0 15px}.wc-day span{display:inline-block;padding:4px 10px;border-radius:99px;background:#e2e8f0;color:#64748b;font-size:9px;font-weight:750;letter-spacing:.09em;text-transform:uppercase}.wc-row{display:flex;max-width:100%;align-items:flex-start;gap:10px;margin:0 0 15px;overflow:hidden}.wc-row.visitor{justify-content:flex-end}.wc-mini-avatar{width:29px;height:29px;border-radius:50%;overflow:hidden;display:grid;place-items:center;background:color-mix(in srgb,var(--wc-color) 12%,#fff);color:var(--wc-color);border:1px solid color-mix(in srgb,var(--wc-color) 22%,#fff);flex:0 0 auto;margin-top:2px}.wc-mini-avatar svg{width:14px;height:14px}.wc-message{max-width:78%;min-width:0;overflow:hidden}.wc-bubble{max-width:100%;overflow-wrap:anywhere;word-break:break-word;padding:12px 13px;border-radius:17px;border-top-left-radius:4px;background:#fff;border:1px solid #edf1f6;box-shadow:0 2px 5px rgba(15,23,42,.05);color:#1e293b;font-size:13px;line-height:1.5;white-space:pre-wrap}.visitor .wc-bubble{background:var(--wc-color);color:#fff;border-color:transparent;border-radius:17px;border-bottom-right-radius:4px;box-shadow:0 5px 13px color-mix(in srgb,var(--wc-color) 24%,transparent)}.wc-time{display:block;margin:4px 3px 0;color:#94a3b8;font-size:9px}.visitor .wc-time{text-align:right}.wc-typing{display:none}.wc-typing.show{display:flex}.wc-dots{padding:13px 15px;display:flex;gap:4px}.wc-dots i{display:block;width:6px;height:6px;border-radius:50%;background:#94a3b8;animation:wc-bounce 1.1s infinite}.wc-dots i:nth-child(2){animation-delay:.15s}.wc-dots i:nth-child(3){animation-delay:.3s}@keyframes wc-bounce{0%,80%,100%{transform:translateY(0);opacity:.45}40%{transform:translateY(-4px);opacity:1}}' +
    '.wc-inputbar{max-width:100%;padding:12px;background:#fff;border-top:1px solid #eef2f6;display:flex;gap:9px;align-items:center;overflow:hidden}.wc-inputwrap{height:42px;min-width:0;flex:1;display:flex;align-items:center;gap:6px;padding:0 11px 0 14px;border:1px solid transparent;background:#f1f5f9;border-radius:99px;transition:.15s}.wc-inputwrap:focus-within{background:#fff;border-color:var(--wc-color);box-shadow:0 0 0 3px color-mix(in srgb,var(--wc-color) 14%,transparent)}.wc-input{width:100%;min-width:0;border:0;outline:0;background:transparent;color:#1e293b;font-size:13px}.wc-input::placeholder{color:#94a3b8}.wc-send{width:42px;height:42px;border:0;border-radius:50%;background:var(--wc-color);color:#fff;display:grid;place-items:center;cursor:pointer;box-shadow:0 6px 15px color-mix(in srgb,var(--wc-color) 28%,transparent);transition:.15s;flex:0 0 auto}.wc-send:hover{filter:brightness(.92);transform:translateY(-1px)}.wc-send:disabled{opacity:.42;cursor:not-allowed;transform:none}.wc-send svg{width:17px;height:17px;margin-left:2px;margin-top:-1px}.wc-footer{text-align:center;padding:8px;background:#fff;border-top:1px solid #f1f5f9;color:#94a3b8;font-size:10px}.wc-footer strong{color:var(--wc-color);font-weight:750}.wc-launch{width:62px;height:62px;border:0;border-radius:50%;background:var(--wc-color);color:#fff;box-shadow:0 12px 30px color-mix(in srgb,var(--wc-color) 38%,transparent);display:grid;place-items:center;cursor:pointer;pointer-events:auto;position:relative;transition:.18s}.wc-launch:hover{transform:scale(1.06)}.wc-launch:before{content:"";position:absolute;inset:-5px;border:2px solid color-mix(in srgb,var(--wc-color) 50%,transparent);border-radius:50%;animation:wc-ring 2s infinite}@keyframes wc-ring{0%{transform:scale(.9);opacity:.7}100%{transform:scale(1.22);opacity:0}}.wc-launch svg{width:27px;height:27px}.wc-launch .close{display:none}.wc-launch.open .chat{display:none}.wc-launch.open .close{display:block}@media(max-width:480px){.wc-shell{right:14px;bottom:14px;max-width:calc(100% - 28px)}.wc-panel{width:calc(100vw - 28px);max-width:100%;height:min(550px,calc(100vh - 104px));margin-bottom:14px}}' +
    '.wc-shell{position:absolute!important;right:24px!important;bottom:24px!important;z-index:2147483647!important;isolation:isolate!important}' +
    '.wc-panel{position:relative!important;z-index:2147483647!important;background:#fff!important;overflow:hidden!important}' +
    '.wc-content{display:flex!important;flex-direction:column!important;background:#f8fafc!important;overflow:hidden!important;position:relative!important}' +
    '.wc-messages{display:block!important;background:#f8fafc!important;overflow-y:auto!important;min-height:0!important}' +
    '.wc-inputbar,.wc-footer{position:relative!important;z-index:2!important;background:#fff!important}' +
    '</style>' +
    '<div class="wc-shell" style="--wc-color:' + color + '">' +
      '<div class="wc-panel" id="wc-panel">' +
        '<div class="wc-head"><div class="wc-agent"><div class="wc-avatar" id="wc-avatar"><img id="wc-avatar-photo" alt=""><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><path d="M18 19c0 1-1 2-2 2h-3"/><path d="M4 14h3v5H5a1 1 0 0 1-1-1zM20 14h-3v5h2a1 1 0 0 0 1-1z"/></svg></div><div style="min-width:0"><div class="wc-name" id="wc-title">' + esc(title) + '<span class="wc-bot">IA</span></div><div class="wc-sub" id="wc-sub">' + esc(subtitle) + '</div></div></div><div class="wc-controls"><button class="wc-icon" id="wc-close" aria-label="Cerrar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 6-12 12M6 6l12 12"/></svg></button></div></div>' +
        '<div class="wc-content"><div class="wc-messages" id="wc-messages"><div class="wc-day"><span>Hoy</span></div><div id="wc-list"></div><div class="wc-row wc-typing" id="wc-typing"><div class="wc-mini-avatar" id="wc-typing-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="6" width="16" height="12" rx="4"/><path d="M9 11h.01M15 11h.01M9 15h6"/></svg></div><div class="wc-dots"><i></i><i></i><i></i></div></div></div></div>' +
        '<div class="wc-inputbar"><div class="wc-inputwrap"><input class="wc-input" id="wc-input" placeholder="Escribe tu mensaje..." autocomplete="off"></div><button class="wc-send" id="wc-send" disabled aria-label="Enviar"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 3.7 21.2 12 3 20.3V14l12-2-12-2z"/></svg></button></div><div class="wc-footer">⚡ Desarrollado por <strong>Rodas AI</strong></div>' +
      '</div>' +
      '<button class="wc-launch" id="wc-launch" aria-label="Abrir Chatbot"><svg class="chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.4-4 8-9 8-1.5 0-3-.3-4.3-.9L3 20l1.4-3.7A8 8 0 0 1 3 12c0-4.4 4-8 9-8s9 3.6 9 8Z"/></svg><svg class="close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 6-12 12M6 6l12 12"/></svg></button>' +
    '</div>';
  var previousHost = document.getElementById(rootId);
  if (previousHost) previousHost.remove();
  var host = document.createElement('div');
  host.id = rootId;
  // Se monta como hijo directo de <html>, no dentro del flujo ni del
  // contenedor con scroll del sitio anfitrión.
  document.documentElement.appendChild(host);
  var root = host.attachShadow({ mode: 'open' });
  root.innerHTML = html;

  var panel = root.querySelector('#wc-panel'), launch = root.querySelector('#wc-launch'), close = root.querySelector('#wc-close'), list = root.querySelector('#wc-list'), box = root.querySelector('#wc-messages'), input = root.querySelector('#wc-input'), send = root.querySelector('#wc-send'), typing = root.querySelector('#wc-typing'), shell = root.querySelector('.wc-shell');
  var historyLoading = false, historyLoaded = false;
  function unlockChat() { historyLoaded = true; input.disabled = false; send.disabled = !input.value.trim(); }
  function assistantAvatar() { return agentPhoto ? '<div class="wc-mini-avatar"><img src="' + esc(agentPhoto) + '" alt=""></div>' : '<div class="wc-mini-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="6" width="16" height="12" rx="4"/><path d="M9 11h.01M15 11h.01M9 15h6"/></svg></div>'; }
  function applyAgentPhoto() { var avatar = root.querySelector('#wc-avatar'), photo = root.querySelector('#wc-avatar-photo'), typingAvatar = root.querySelector('#wc-typing-avatar'); if (!avatar || !photo) return; if (agentPhoto) { photo.src = agentPhoto; avatar.classList.add('has-photo'); if (typingAvatar) typingAvatar.innerHTML = '<img src="' + esc(agentPhoto) + '" alt="">'; } else { photo.removeAttribute('src'); avatar.classList.remove('has-photo'); } }
  function render(listData) { var output = ''; listData.forEach(function (item) { var visitor = item.role === 'visitor'; output += '<div class="wc-row ' + (visitor ? 'visitor' : 'assistant') + '">' + (visitor ? '' : assistantAvatar()) + '<div class="wc-message"><div class="wc-bubble">' + esc(item.content) + '</div><span class="wc-time">' + esc(item.time || now()) + '</span></div></div>'; }); list.innerHTML = output; box.scrollTop = box.scrollHeight; }
  function setOpen(state) { panel.classList.toggle('open', state); launch.classList.toggle('open', state); if (state) { if (!historyLoaded) { input.disabled = true; send.disabled = true; } else { unlockChat(); } render(messages()); fetchConfig(); if (historyUrl) loadHistory(); } }
  launch.addEventListener('click', function () { setOpen(!panel.classList.contains('open')); }); close.addEventListener('click', function () { setOpen(false); });
  input.addEventListener('input', function () { send.disabled = !input.value.trim(); }); input.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); submit(input.value); } }); send.addEventListener('click', function () { submit(input.value); });
  function submit(text) { text = (text || '').trim(); if (!text || !historyLoaded) return; input.value = ''; send.disabled = true; var all = messages(); all.push({ role: 'visitor', content: text, time: now() }); save(all); render(all); persist('visitor', text); typing.classList.add('show'); box.scrollTop = box.scrollHeight; var history = all.slice(0, -1).map(function (item) { return { role: item.role === 'visitor' ? 'user' : 'assistant', content: item.content }; }); var request = new XMLHttpRequest(); request.open('POST', endpoint('send'), true); request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded'); request.onreadystatechange = function () { if (request.readyState !== 4) return; typing.classList.remove('show'); send.disabled = false; if (request.status === 200) { try { var response = JSON.parse(request.responseText); if (response.success && response.message && response.message.content) { var updated = messages(); updated.push({ role: 'assistant', content: response.message.content, time: now() }); save(updated); render(updated); persist('assistant', response.message.content); } } catch (e) {} } }; request.send('key=' + encodeURIComponent(apiKey) + '&message=' + encodeURIComponent(text) + '&history=' + encodeURIComponent(JSON.stringify(history))); }
  function loadHistory() { if (!historyUrl || historyLoading || historyLoaded) return; historyLoading = true; var request = new XMLHttpRequest(); request.open('GET', historyUrl + '?key=' + encodeURIComponent(apiKey) + '&session_id=' + encodeURIComponent(sessionId), true); request.onreadystatechange = function () { if (request.readyState !== 4) return; historyLoading = false; if (request.status === 200) { try { var data = JSON.parse(request.responseText); if (data.success && Array.isArray(data.messages)) { var restored = data.messages.map(function (item) { return { role: item.role === 'visitor' ? 'visitor' : 'assistant', content: item.content, time: item.created_at ? item.created_at.slice(11, 16) : now() }; }); save(restored); render(restored); } } catch (e) {} } unlockChat(); }; request.onerror = function () { historyLoading = false; unlockChat(); }; request.send(); }
  function fetchConfig() { var request = new XMLHttpRequest(); request.open('GET', endpoint('config') + '?key=' + encodeURIComponent(apiKey) + '&origin=' + encodeURIComponent(window.location.origin), true); request.onreadystatechange = function () { if (request.readyState !== 4) return; if (request.status !== 200) { unlockChat(); return; } try { var data = JSON.parse(request.responseText); if (!data.success || !data.config) { unlockChat(); return; } var config = data.config; if (data.storage_base) { /* WS es la fuente canónica: corrige embeds antiguos con rutas obsoletas. */ storeUrl = data.storage_base + '/api/widget/store.php'; historyUrl = data.storage_base + '/api/widget/messages.php'; } if (config.primary_color) { color = config.primary_color; shell.style.setProperty('--wc-color', color); } var configuredName = config.agent_name || config.welcome_title; if (configuredName) root.querySelector('#wc-title').childNodes[0].nodeValue = configuredName; if (config.welcome_subtitle) root.querySelector('#wc-sub').textContent = config.welcome_subtitle; agentPhoto = config.agent_photo || ''; applyAgentPhoto(); render(messages()); flushOutbox(); if (historyLoaded) unlockChat(); else if (historyUrl) loadHistory(); else unlockChat(); } catch (e) { unlockChat(); } }; request.send(); }
  window.addEventListener('online', flushOutbox);
  flushOutbox();
})();
