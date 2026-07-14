(function() {
  var script = document.currentScript;
  if (!script) return;
  var apiKey = script.getAttribute('data-api-key');
  var apiBase = script.getAttribute('data-api-base') || window.location.origin;
  if (!apiKey) return;

  var storeUrl = script.getAttribute('data-store') || '';
  var historyUrl = script.getAttribute('data-history') || '';
  var sessionStorageKey = 'wabot_session_' + apiKey;
  var visitorId = localStorage.getItem(sessionStorageKey);
  if (!visitorId) {
    // Es un token de sesión opaco, no un identificador adivinable. WC lo usa
    // como llave de acceso a la conversación de este navegador.
    if (window.crypto && window.crypto.getRandomValues) {
      var randomBytes = new Uint8Array(32);
      window.crypto.getRandomValues(randomBytes);
      visitorId = Array.prototype.map.call(randomBytes, function(byte) {
        return ('0' + byte.toString(16)).slice(-2);
      }).join('');
    } else {
      visitorId = String(Date.now()) + '_' + Math.random().toString(36).slice(2) + '_' + Math.random().toString(36).slice(2);
    }
    try { localStorage.setItem(sessionStorageKey, visitorId); } catch(e) {}
  }

  function storeMessage(role, content) {
    if (!storeUrl) return;
    var payload = JSON.stringify({
      api_key: apiKey,
      session_id: visitorId,
      role: role,
      content: content
    });
    var xhr = new XMLHttpRequest();
    xhr.open('POST', storeUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.send(payload);
  }

  var STORAGE_KEY = 'wabot_messages_' + apiKey;
  var primaryColor = '#2F63E9';
  var welcomeTitle = 'Asistente';
  var welcomeSubtitle = 'Online';

  function apiUrl(endpoint) {
    return apiBase.replace(/\/+$/, '') + '/api/widget/' + endpoint;
  }

  function getMessages() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; } catch(e) { return []; }
  }

  function saveMessages(msgs) {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(msgs)); } catch(e) {}
  }

  function loadPersistedMessages() {
    if (!historyUrl) return;
    var xhr = new XMLHttpRequest();
    xhr.open('GET', historyUrl + '?key=' + encodeURIComponent(apiKey) + '&session_id=' + encodeURIComponent(visitorId), true);
    xhr.onreadystatechange = function() {
      if (xhr.readyState !== 4 || xhr.status !== 200) return;
      try {
        var data = JSON.parse(xhr.responseText || '{}');
        if (!data.success || !Array.isArray(data.messages)) return;
        var messages = data.messages.map(function(m) {
          return { role: m.role === 'visitor' ? 'visitor' : 'assistant', content: m.content, time: m.created_at ? m.created_at.slice(11,16) : timeStr() };
        });
        saveMessages(messages);
        renderMessages(messages);
      } catch(e) {}
    };
    xhr.send();
  }

  function escapeHtml(text) {
    var d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
  }

  function timeStr() {
    var d = new Date();
    return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
  }

  var panelW = 420;
  var panelH = 590;
  var rootId = 'wabot-widget-root';
  var html =
    '<div id="' + rootId + '">' +
    '<style>' +
    '#' + rootId + ' { all:initial; font-family:Inter,Roboto,Arial,sans-serif; line-height:normal; text-align:left; isolation:isolate; }' +
    '#' + rootId + ',#' + rootId + ' * { box-sizing:border-box; margin:0; padding:0; font-family:inherit; }' +
    '#' + rootId + ' button,#' + rootId + ' input { font:inherit; }' +
    '.ww-launcher { position:fixed; bottom:20px; right:20px; z-index:2147483646; width:62px; height:62px; border-radius:50%; background:' + primaryColor + '; color:#fff; border:2px solid rgba(255,255,255,.75); cursor:pointer; box-shadow:0 12px 34px rgba(2,6,23,.48),0 0 0 5px color-mix(in srgb,' + primaryColor + ' 18%,transparent); display:flex; align-items:center; justify-content:center; transition:transform .15s; }' +
    '.ww-launcher:hover { transform:scale(1.05); }' +
    '.ww-launcher svg { transition:transform .2s; }' +
    '.ww-launcher.open svg { transform:rotate(45deg); }' +
    '.ww-panel { position:fixed; bottom:100px; right:20px; z-index:2147483646; width:' + panelW + 'px; max-width:calc(100vw - 32px); height:' + panelH + 'px; max-height:calc(100vh - 124px); background:#09131f !important; border:1px solid rgba(148,163,184,.28); border-radius:24px; box-shadow:0 26px 70px rgba(2,6,23,.56); display:none; flex-direction:column; overflow:hidden; }' +
    '.ww-panel.open { display:flex; }' +
    '.ww-header { background:linear-gradient(135deg,#0f1b28,#09131f 72%); min-height:88px; padding:16px 18px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0; color:#fff; border-bottom:1px solid rgba(148,163,184,.2); }' +
    '.ww-header-info { display:flex; align-items:center; gap:10px; min-width:0; }' +
    '.ww-header-avatar { width:44px; height:44px; border-radius:50%; border:1px solid ' + primaryColor + '; color:' + primaryColor + '; background:rgba(255,255,255,.04); display:flex; align-items:center; justify-content:center; font-size:22px; flex:0 0 auto; }' +
    '.ww-header-copy { display:flex; flex-direction:column; min-width:0; }' +
    '.ww-header-title { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:16px; font-weight:700; color:#f8fafc !important; line-height:1.25; }' +
    '.ww-header-status { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12px; color:#cbd5e1 !important; margin-top:4px; }' +
    '.ww-header-status:before { content:""; display:inline-block; width:7px; height:7px; background:#22c55e; border-radius:50%; margin:0 6px 1px 0; }' +
    '.ww-header-close { width:34px; height:34px; border-radius:50%; background:transparent; border:1px solid rgba(148,163,184,.28); color:#cbd5e1; font-size:24px; cursor:pointer; padding:0; line-height:1; opacity:0.9; flex:0 0 auto; }' +
    '.ww-header-close:hover { opacity:1; }' +
    '.ww-body { flex:1; overflow-y:auto; padding:18px; background:radial-gradient(circle at top right,rgba(255,255,255,.035),transparent 34%),#09131f; }' +
    '.ww-body::-webkit-scrollbar { width:4px; }' +
    '.ww-body::-webkit-scrollbar-thumb { background:#334155; border-radius:4px; }' +
    '.ww-msg { margin-bottom:12px; display:flex; flex-direction:column; }' +
    '.ww-msg.visitor { align-items:flex-end; }' +
    '.ww-msg.assistant { align-items:flex-start; }' +
    '.ww-msg-bubble { max-width:84%; padding:12px 14px; border-radius:18px; font-size:14px; line-height:1.55; word-wrap:break-word; white-space:pre-wrap; }' +
    '.ww-msg.visitor .ww-msg-bubble { background:linear-gradient(135deg,' + primaryColor + ',color-mix(in srgb,' + primaryColor + ' 72%,#020617)); color:#fff; border-bottom-right-radius:5px; box-shadow:0 5px 15px rgba(0,0,0,.15); }' +
    '.ww-msg.assistant .ww-msg-bubble { background:rgba(255,255,255,.07); color:#f1f5f9; border:1px solid rgba(148,163,184,.16); border-bottom-left-radius:5px; }' +
    '.ww-msg-time { font-size:10px; color:#94a3b8; margin-top:5px; padding:0 5px; }' +
    '.ww-empty { text-align:center; color:#94a3b8; font-size:13px; line-height:1.5; padding:64px 28px; }' +
    '.ww-domain-error { text-align:center; padding:34px 22px; color:#334155; }' +
    '.ww-domain-error-icon { width:48px; height:48px; margin:0 auto 14px; border-radius:50%; background:#fff7ed; color:#ea580c; display:flex; align-items:center; justify-content:center; font-size:24px; }' +
    '.ww-domain-error h3 { font-size:16px; margin-bottom:8px; color:#1e293b; }' +
    '.ww-domain-error p { font-size:13px; line-height:1.5; color:#64748b; }' +
    '.ww-typing { display:flex; align-items:center; gap:6px; padding:12px 16px; background:rgba(255,255,255,.07); border:1px solid rgba(148,163,184,.16); border-radius:14px; margin-bottom:12px; width:fit-content; }' +
    '.ww-typing span { width:8px; height:8px; border-radius:50%; background:#9aa5b1; animation:ww-pulse 1.2s infinite; }' +
    '.ww-typing span:nth-child(2) { animation-delay:.2s; }' +
    '.ww-typing span:nth-child(3) { animation-delay:.4s; }' +
    '@keyframes ww-pulse { 0%,100% { opacity:.3; } 50% { opacity:1; } }' +
    '.ww-footer { border-top:1px solid rgba(148,163,184,.16); padding:14px 16px; background:#09131f; display:flex; gap:10px; align-items:center; flex-shrink:0; }' +
    '.ww-input { flex:1; height:46px; border:1px solid rgba(148,163,184,.3); background:#0d1b29; color:#f8fafc; border-radius:14px; padding:0 14px; font-size:14px; outline:none; }' +
    '.ww-input:focus { border-color:' + primaryColor + '; }' +
    '.ww-input::placeholder { color:#94a3b8; }' +
    '.ww-send { width:46px; height:46px; border-radius:14px; background:' + primaryColor + '; color:#fff; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:opacity .15s,transform .15s; }' +
    '.ww-send:disabled { opacity:0.4; cursor:not-allowed; }' +
    '.ww-branding { text-align:center; font-size:11px; color:#64748b; padding:8px 0; background:#09131f; border-top:1px solid rgba(148,163,184,.12); flex-shrink:0; }' +
    '@media(max-width:480px){.ww-launcher{bottom:14px;right:14px}.ww-panel{right:12px;bottom:86px;max-width:calc(100vw - 24px);height:min(590px,calc(100vh - 104px));border-radius:20px}}' +
    '</style>' +
    '<button class="ww-launcher" id="ww-launcher">' +
    '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="26" height="26"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>' +
    '</button>' +
    '<div class="ww-panel" id="ww-panel">' +
      '<div class="ww-header">' +
        '<div class="ww-header-info">' +
          '<span class="ww-header-avatar" aria-hidden="true">◌</span>' +
          '<span class="ww-header-copy">' +
            '<span class="ww-header-title" id="ww-title">' + welcomeTitle + '</span>' +
            '<span class="ww-header-status" id="ww-status">' + welcomeSubtitle + '</span>' +
          '</span>' +
        '</div>' +
        '<button class="ww-header-close" id="ww-close">&times;</button>' +
      '</div>' +
      '<div class="ww-body" id="ww-body">' +
        '<div class="ww-empty" id="ww-empty">Escribí tu consulta y te responderemos al instante</div>' +
        '<div id="ww-msgs"></div>' +
        '<div class="ww-typing" id="ww-typing" style="display:none"><span></span><span></span><span></span></div>' +
      '</div>' +
      '<div class="ww-footer">' +
        '<input type="text" class="ww-input" id="ww-input" placeholder="Escribe tu mensaje..." autocomplete="off">' +
        '<button class="ww-send" id="ww-send" disabled>' +
          '<svg fill="currentColor" viewBox="0 0 24 24" width="18" height="18"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>' +
        '</button>' +
      '</div>' +
      '<div class="ww-branding">Asistente IA</div>' +
    '</div>' +
    '</div>';

  document.body.insertAdjacentHTML('beforeend', html);

  var rootEl = document.getElementById(rootId);
  var launcher = document.getElementById('ww-launcher');
  var panel = document.getElementById('ww-panel');
  var closeBtn = document.getElementById('ww-close');
  var body = document.getElementById('ww-body');
  var msgContainer = document.getElementById('ww-msgs');
  var emptyMsg = document.getElementById('ww-empty');
  var typingEl = document.getElementById('ww-typing');
  var inputEl = document.getElementById('ww-input');
  var sendBtn = document.getElementById('ww-send');
  var titleEl = document.getElementById('ww-title');
  var statusEl = document.getElementById('ww-status');
  var domainAuthorized = true;

  function showDomainError() {
    if (!domainAuthorized) return;
    domainAuthorized = false;
    titleEl.textContent = 'Chatbot no autorizado';
    statusEl.textContent = 'Dominio no habilitado';
    msgContainer.innerHTML = '<div class="ww-domain-error"><div class="ww-domain-error-icon">!</div><h3>Chatbot no autorizado</h3><p>Este Chatbot no está autorizado para funcionar en este dominio.</p></div>';
    emptyMsg.style.display = 'none';
    inputEl.disabled = true;
    inputEl.placeholder = 'Chatbot no disponible en este dominio';
    sendBtn.disabled = true;
  }

  function applyColor(color) {
    if (!color) return;
    primaryColor = color;
    var els = rootEl.querySelectorAll('.ww-launcher,.ww-send');
    for (var i = 0; i < els.length; i++) { els[i].style.background = color; }
    var visitorBubbles = rootEl.querySelectorAll('.ww-msg.visitor .ww-msg-bubble');
    for (var i = 0; i < visitorBubbles.length; i++) { visitorBubbles[i].style.background = color; }
    var inputFocus = rootEl.querySelector('.ww-input');
    if (inputFocus) inputFocus.style.setProperty('--focus-color', color);
    var style = rootEl.querySelector('style');
    if (style) {
      style.textContent = style.textContent.replace(/\.ww-input:focus\s*\{[^}]*\}/g, '.ww-input:focus{border-color:' + color + '}');
    }
    var avatar = rootEl.querySelector('.ww-header-avatar');
    if (avatar) { avatar.style.borderColor = color; avatar.style.color = color; }
  }

  function fetchConfig() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', apiUrl('config') + '?key=' + encodeURIComponent(apiKey) + '&origin=' + encodeURIComponent(window.location.origin), true);
    xhr.onreadystatechange = function() {
      if (xhr.readyState === 4) {
        try {
          var data = JSON.parse(xhr.responseText || '{}');
          if (xhr.status === 403 && data.code === 'DOMAIN_NOT_AUTHORIZED') {
            showDomainError();
            return;
          }
          if (xhr.status === 200) {
          if (data.success && data.config) {
            var cfg = data.config;
            if (cfg.welcome_title) titleEl.textContent = cfg.welcome_title;
            if (cfg.welcome_subtitle) statusEl.textContent = cfg.welcome_subtitle;
            if (cfg.primary_color) applyColor(cfg.primary_color);
          }
          }
        } catch(e) {}
      }
    };
    xhr.send();
  }

  var isOpen = false;
  launcher.addEventListener('click', function() {
    isOpen = !isOpen;
    panel.classList.toggle('open', isOpen);
    launcher.classList.toggle('open', isOpen);
    if (isOpen) {
      renderMessages(getMessages());
      loadPersistedMessages();
      fetchConfig();
    }
  });
  closeBtn.addEventListener('click', function() {
    isOpen = false;
    panel.classList.remove('open');
    launcher.classList.remove('open');
  });

  function renderMessages(messages) {
    if (!messages || messages.length === 0) {
      emptyMsg.style.display = 'block';
      msgContainer.innerHTML = '';
      return;
    }
    emptyMsg.style.display = 'none';
    var h = '';
    for (var i = 0; i < messages.length; i++) {
      var m = messages[i];
      h += '<div class="ww-msg ' + m.role + '">' +
        '<div class="ww-msg-bubble">' + escapeHtml(m.content) + '</div>' +
        '<div class="ww-msg-time">' + (m.time || timeStr()) + '</div>' +
        '</div>';
    }
    msgContainer.innerHTML = h;
    body.scrollTop = body.scrollHeight;
  }

  inputEl.addEventListener('input', function() {
    sendBtn.disabled = !this.value.trim();
  });

  inputEl.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
  });

  sendBtn.addEventListener('click', sendMessage);

  function sendMessage() {
    if (!domainAuthorized) return;
    var msg = inputEl.value.trim();
    if (!msg) return;
    inputEl.value = '';
    sendBtn.disabled = true;

    var messages = getMessages();
    messages.push({ role: 'visitor', content: msg, time: timeStr() });
    saveMessages(messages);
    renderMessages(messages);
    typingEl.style.display = 'flex';
    emptyMsg.style.display = 'none';
    storeMessage('visitor', msg);

    var historyForApi = messages.map(function(m) { return { role: m.role === 'visitor' ? 'user' : 'assistant', content: m.content }; });
    historyForApi.pop();

    var xhr = new XMLHttpRequest();
    xhr.open('POST', apiUrl('send'), true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
      if (xhr.readyState === 4) {
        typingEl.style.display = 'none';
        sendBtn.disabled = false;
        if (xhr.status === 200) {
          try {
            var data = JSON.parse(xhr.responseText);
            if (data.success && data.message) {
              var msgs = getMessages();
              msgs.push({ role: 'assistant', content: data.message.content, time: timeStr() });
              saveMessages(msgs);
              renderMessages(msgs);
              storeMessage('assistant', data.message.content);
            }
          } catch(e) {}
        } else if (xhr.status === 403) {
          try {
            var errorData = JSON.parse(xhr.responseText || '{}');
            if (errorData.code === 'DOMAIN_NOT_AUTHORIZED') showDomainError();
          } catch(e) {}
        }
      }
    };
    xhr.send('key=' + encodeURIComponent(apiKey) + '&message=' + encodeURIComponent(msg) + '&history=' + encodeURIComponent(JSON.stringify(historyForApi)));
  }
})();
