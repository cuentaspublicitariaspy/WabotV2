(function() {
  var script = document.currentScript;
  if (!script) return;
  var apiKey = script.getAttribute('data-api-key');
  var apiBase = script.getAttribute('data-api-base') || window.location.origin;
  if (!apiKey) return;

  var storeUrl = script.getAttribute('data-store') || '';
  var visitorId = apiKey + '_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);

  function storeMessage(role, content) {
    if (!storeUrl) return;
    var payload = JSON.stringify({
      api_key: apiKey,
      visitor_id: visitorId,
      role: role,
      content: content,
      page_url: window.location.href
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

  function escapeHtml(text) {
    var d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
  }

  function timeStr() {
    var d = new Date();
    return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
  }

  var panelW = 382;
  var panelH = 497;
  var rootId = 'wabot-widget-root';
  var html =
    '<div id="' + rootId + '">' +
    '<style>' +
    '#' + rootId + ' * { box-sizing:border-box; margin:0; padding:0; }' +
    '#' + rootId + ' { font-family:Inter,Roboto,Arial,sans-serif; }' +
    '.ww-launcher { position:fixed; bottom:18px; right:18px; z-index:2147483646; width:58px; height:58px; border-radius:50%; background:' + primaryColor + '; color:#fff; border:none; cursor:pointer; box-shadow:0 10px 24px rgba(47,99,233,0.35); display:flex; align-items:center; justify-content:center; transition:transform .15s; }' +
    '.ww-launcher:hover { transform:scale(1.05); }' +
    '.ww-launcher svg { transition:transform .2s; }' +
    '.ww-launcher.open svg { transform:rotate(45deg); }' +
    '.ww-panel { position:fixed; bottom:94px; right:18px; z-index:2147483646; width:' + panelW + 'px; max-width:calc(100vw - 36px); height:' + panelH + 'px; max-height:calc(100vh - 140px); background:#fff; border-radius:20px; box-shadow:0 12px 35px rgba(0,0,0,0.18); display:none; flex-direction:column; overflow:hidden; }' +
    '.ww-panel.open { display:flex; }' +
    '.ww-header { background:' + primaryColor + '; height:70px; border-radius:20px 20px 0 0; padding:16px 18px 16px 22px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }' +
    '.ww-header-info { display:flex; flex-direction:column; }' +
    '.ww-header-title { font-size:20px; font-weight:600; color:#fff; line-height:1.2; }' +
    '.ww-header-status { font-size:13px; color:rgba(255,255,255,0.7); margin-top:1px; }' +
    '.ww-header-close { background:none; border:none; color:#fff; font-size:22px; cursor:pointer; padding:4px; line-height:1; opacity:0.8; }' +
    '.ww-header-close:hover { opacity:1; }' +
    '.ww-body { flex:1; overflow-y:auto; padding:18px; background:#fff; }' +
    '.ww-body::-webkit-scrollbar { width:4px; }' +
    '.ww-body::-webkit-scrollbar-thumb { background:#d0d5dd; border-radius:4px; }' +
    '.ww-msg { margin-bottom:12px; display:flex; flex-direction:column; }' +
    '.ww-msg.visitor { align-items:flex-end; }' +
    '.ww-msg.assistant { align-items:flex-start; }' +
    '.ww-msg-bubble { max-width:82%; padding:14px 16px; border-radius:14px; font-size:15px; line-height:1.45; word-wrap:break-word; }' +
    '.ww-msg.visitor .ww-msg-bubble { background:' + primaryColor + '; color:#fff; border-bottom-right-radius:4px; }' +
    '.ww-msg.assistant .ww-msg-bubble { background:#fff; color:#222; border:1px solid #e2e8f0; border-bottom-left-radius:4px; }' +
    '.ww-msg-time { font-size:10px; color:#9aa5b1; margin-top:4px; padding:0 4px; }' +
    '.ww-empty { text-align:center; color:#9aa5b1; font-size:13px; padding:40px 20px; }' +
    '.ww-typing { display:flex; align-items:center; gap:6px; padding:12px 16px; background:#fff; border:1px solid #e2e8f0; border-radius:14px; margin-bottom:12px; width:fit-content; }' +
    '.ww-typing span { width:8px; height:8px; border-radius:50%; background:#9aa5b1; animation:ww-pulse 1.2s infinite; }' +
    '.ww-typing span:nth-child(2) { animation-delay:.2s; }' +
    '.ww-typing span:nth-child(3) { animation-delay:.4s; }' +
    '@keyframes ww-pulse { 0%,100% { opacity:.3; } 50% { opacity:1; } }' +
    '.ww-footer { border-top:1px solid #e8edf4; padding:14px 18px; background:#fff; display:flex; gap:10px; align-items:center; flex-shrink:0; }' +
    '.ww-input { flex:1; height:42px; border:1px solid #d9e2ec; border-radius:12px; padding:0 14px; font-size:14px; outline:none; }' +
    '.ww-input:focus { border-color:' + primaryColor + '; }' +
    '.ww-input::placeholder { color:#9aa5b1; }' +
    '.ww-send { width:42px; height:42px; border-radius:50%; background:' + primaryColor + '; color:#fff; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:opacity .15s; }' +
    '.ww-send:disabled { opacity:0.4; cursor:not-allowed; }' +
    '.ww-branding { text-align:center; font-size:11px; color:#9aa5b1; padding:6px 0; background:#fff; border-top:1px solid #e8edf4; flex-shrink:0; }' +
    '</style>' +
    '<button class="ww-launcher" id="ww-launcher">' +
    '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="26" height="26"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>' +
    '</button>' +
    '<div class="ww-panel" id="ww-panel">' +
      '<div class="ww-header">' +
        '<div class="ww-header-info">' +
          '<span class="ww-header-title" id="ww-title">' + welcomeTitle + '</span>' +
          '<span class="ww-header-status" id="ww-status">' + welcomeSubtitle + '</span>' +
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

  function applyColor(color) {
    if (!color) return;
    primaryColor = color;
    var els = rootEl.querySelectorAll('.ww-launcher,.ww-header,.ww-send');
    for (var i = 0; i < els.length; i++) { els[i].style.background = color; }
    var visitorBubbles = rootEl.querySelectorAll('.ww-msg.visitor .ww-msg-bubble');
    for (var i = 0; i < visitorBubbles.length; i++) { visitorBubbles[i].style.background = color; }
    var inputFocus = rootEl.querySelector('.ww-input');
    if (inputFocus) inputFocus.style.setProperty('--focus-color', color);
    var style = rootEl.querySelector('style');
    if (style) {
      style.textContent = style.textContent.replace(/\.ww-input:focus\s*\{[^}]*\}/g, '.ww-input:focus{border-color:' + color + '}');
    }
  }

  function fetchConfig() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', apiUrl('config') + '?key=' + encodeURIComponent(apiKey) + '&origin=' + encodeURIComponent(window.location.origin), true);
    xhr.onreadystatechange = function() {
      if (xhr.readyState === 4 && xhr.status === 200) {
        try {
          var data = JSON.parse(xhr.responseText);
          if (data.success && data.config) {
            var cfg = data.config;
            if (cfg.welcome_title) titleEl.textContent = cfg.welcome_title;
            if (cfg.welcome_subtitle) statusEl.textContent = cfg.welcome_subtitle;
            if (cfg.primary_color) applyColor(cfg.primary_color);
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
        }
      }
    };
    xhr.send('key=' + encodeURIComponent(apiKey) + '&message=' + encodeURIComponent(msg) + '&history=' + encodeURIComponent(JSON.stringify(historyForApi)));
  }
})();
