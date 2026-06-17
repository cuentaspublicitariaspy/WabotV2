<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Database.php';
requireLogin();
requireAdmin();

$user = getUsuarioActual();
$db = Database::getConnection();
$activePage = 'config_api';
$pageTitle = 'Configuración API';

// Get or create client record for this user
$clienteId = null;
$cliente = null;
if ($user['rol'] === 'admin') {
    $stmt = $db->prepare("SELECT id FROM clientes WHERE id = (SELECT cliente_id FROM usuarios WHERE id = ?)");
    $stmt->execute([(int)$user['id']]);
    $clienteId = $stmt->fetchColumn();
    if ($clienteId) {
        $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$clienteId]);
        $cliente = $stmt->fetch();
    }
}

$metaAppId = META_APP_ID ?: 'no-configurado';
$connectionError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_token') {
    $token = $_POST['whatsapp_token'] ?? '';
    $phoneNumberId = $_POST['whatsapp_phone_number_id'] ?? '';
    $verifyToken = $_POST['whatsapp_verify_token'] ?? 'wabotv2_verify_' . time();
    if ($token && $phoneNumberId) {
        if (!$clienteId) {
            $stmt = $db->prepare("INSERT INTO clientes (whatsapp_token, whatsapp_phone_number_id, whatsapp_verify_token) VALUES (?, ?, ?)");
            $stmt->execute([$token, $phoneNumberId, $verifyToken]);
            $clienteId = (int)$db->lastInsertId();
            $db->prepare("UPDATE usuarios SET cliente_id = ? WHERE id = ?")->execute([$clienteId, (int)$user['id']]);
        } else {
            $stmt = $db->prepare("UPDATE clientes SET whatsapp_token = ?, whatsapp_phone_number_id = ?, whatsapp_verify_token = ? WHERE id = ?");
            $stmt->execute([$token, $phoneNumberId, $verifyToken, $clienteId]);
        }
        $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$clienteId]);
        $cliente = $stmt->fetch();
    } else {
        $connectionError = 'Completá Token y Phone Number ID';
    }
}

ob_start();
?>
<div class="max-w-2xl mx-auto">
  <h1 class="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2">
    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
    Configuración de API
  </h1>

  <?php if ($connectionError): ?>
  <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium bg-red-50 text-red-700 border border-red-200"><?= htmlspecialchars($connectionError) ?></div>
  <?php endif; ?>

  <?php if (META_APP_ID === ''): ?>
  <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium bg-amber-50 text-amber-700 border border-amber-200">
    ⚠️ META_APP_ID no configurado en el .env. El Embedded Signup requiere este valor.
  </div>
  <?php endif; ?>

  <!-- Connection status -->
  <div class="bg-white border border-slate-100 rounded-2xl p-6 mb-5">
    <h5 class="text-sm font-semibold text-slate-700 mb-4">Estado de conexión</h5>
    <?php if ($cliente && $cliente['whatsapp_phone_number_id']): ?>
      <div class="flex items-center gap-3 mb-4">
        <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
        <span class="text-sm text-slate-600 font-medium">Conectado</span>
      </div>
      <div class="bg-slate-50 rounded-xl p-4 space-y-2 text-sm">
        <div class="flex justify-between"><span class="text-slate-500">Phone Number ID:</span><span class="text-slate-800 font-mono"><?= htmlspecialchars($cliente['whatsapp_phone_number_id']) ?></span></div>
        <div class="flex justify-between"><span class="text-slate-500">Business Account:</span><span class="text-slate-800 font-mono"><?= htmlspecialchars($cliente['whatsapp_business_account_id'] ?? '—') ?></span></div>
        <div class="flex justify-between"><span class="text-slate-500">Verify Token:</span><span class="text-slate-800 font-mono"><?= htmlspecialchars($cliente['whatsapp_verify_token'] ?? '—') ?></span></div>
        <div class="flex justify-between"><span class="text-slate-500">Token:</span><span class="text-slate-800 font-mono"><?= htmlspecialchars(substr($cliente['whatsapp_token'], 0, 30)) ?>...</span></div>
      </div>
    <?php else: ?>
      <div class="flex items-center gap-3 mb-4">
        <span class="w-3 h-3 rounded-full bg-slate-300"></span>
        <span class="text-sm text-slate-500">No conectado</span>
      </div>
    <?php endif; ?>
  </div>

  <!-- Embedded Signup -->
  <div class="bg-white border border-slate-100 rounded-2xl p-6 mb-5">
    <h5 class="text-sm font-semibold text-slate-700 mb-2">Conectar con Facebook (Embedded Signup)</h5>
    <p class="text-xs text-slate-500 mb-4">Iniciá sesión con Facebook y conectá tu WhatsApp Business en un solo clic.</p>
    <?php if (META_APP_ID !== ''): ?>
    <div id="fb-root"></div>
    <button id="fb-login-btn" onclick="connectWhatsApp()" class="px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition shadow-lg flex items-center gap-2">
      <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
      Conectar WhatsApp Business
    </button>
    <div id="fb-connect-msg" class="hidden mt-3 text-sm font-medium px-3 py-2 rounded-lg"></div>
    <?php else: ?>
      <p class="text-sm text-amber-600">Configurá META_APP_ID en el .env para habilitar Embedded Signup.</p>
    <?php endif; ?>
  </div>

  <!-- Manual connection (fallback) -->
  <div class="bg-white border border-slate-100 rounded-2xl p-6">
    <h5 class="text-sm font-semibold text-slate-700 mb-2">Conexión manual (alternativa)</h5>
    <p class="text-xs text-slate-500 mb-4">Copiá estos datos desde tu app de Meta Developers.</p>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="save_token">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">WhatsApp Token</label>
        <input type="text" name="whatsapp_token" value="<?= htmlspecialchars($cliente['whatsapp_token'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono" placeholder="EAATuTokenDeAcceso...">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number ID</label>
        <input type="text" name="whatsapp_phone_number_id" value="<?= htmlspecialchars($cliente['whatsapp_phone_number_id'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="123456789012345">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Verify Token (para webhook)</label>
        <input type="text" name="whatsapp_verify_token" value="<?= htmlspecialchars($cliente['whatsapp_verify_token'] ?? 'wabotv2_verify_' . time()) ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
        <p class="text-xs text-slate-400 mt-1">Usá este mismo valor al configurar el webhook en Meta Developers.</p>
      </div>
      <div class="flex justify-end">
        <button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition shadow-lg">Guardar conexión</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScripts = '';
if (META_APP_ID !== ''):
$extraScripts = <<<'EOS'
<script>
  window.fbAsyncInit = function() {
    FB.init({ appId: 'META_APP_ID_PLACEHOLDER', version: 'v18.0', cookie: true });
  };
  (function(d, s, id) {
    var js, fjs = d.getElementsByTagName(s)[0];
    if (d.getElementById(id)) return;
    js = d.createElement(s); js.id = id;
    js.src = 'https://connect.facebook.net/es_ES/sdk.js';
    fjs.parentNode.insertBefore(js, fjs);
  }(document, 'script', 'facebook-jssdk'));

  function connectWhatsApp() {
    var btn = document.getElementById('fb-login-btn');
    btn.disabled = true; btn.textContent = 'Conectando...';
    FB.login(function(res) {
      if (res.authResponse) {
        var accessToken = res.authResponse.accessToken;
        fetch('ajax/connect_whatsapp.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ access_token: accessToken })
        }).then(function(r) { return r.json(); }).then(function(data) {
          var msg = document.getElementById('fb-connect-msg');
          msg.classList.remove('hidden');
          if (data.success) {
            msg.className = 'mt-3 text-sm font-medium px-3 py-2 rounded-lg text-green-800 bg-green-100';
            msg.textContent = 'Conectado exitosamente. Recargando...';
            setTimeout(function(){ location.reload(); }, 1500);
          } else {
            msg.className = 'mt-3 text-sm font-medium px-3 py-2 rounded-lg text-red-800 bg-red-100';
            msg.textContent = 'Error: ' + (data.error || data.errors?.join(', ') || 'desconocido');
            btn.disabled = false; btn.innerHTML = '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg> Conectar WhatsApp Business';
          }
        }).catch(function() {
          document.getElementById('fb-connect-msg').className = 'mt-3 text-sm font-medium px-3 py-2 rounded-lg text-red-800 bg-red-100';
          document.getElementById('fb-connect-msg').textContent = 'Error de conexión con el servidor';
          btn.disabled = false; btn.innerHTML = '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg> Conectar WhatsApp Business';
        });
      } else {
        document.getElementById('fb-connect-msg').className = 'mt-3 text-sm font-medium px-3 py-2 rounded-lg text-amber-800 bg-amber-100';
        document.getElementById('fb-connect-msg').textContent = 'Inicio de sesión cancelado o no autorizado';
        btn.disabled = false; btn.innerHTML = '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg> Conectar WhatsApp Business';
      }
    }, { config_id: null, response_type: 'token,granted_scopes', override_default_response_type: true });
  }
</script>
EOS;
$extraScripts = str_replace('META_APP_ID_PLACEHOLDER', META_APP_ID, $extraScripts);
endif;

$mainContent = ob_get_clean();
require __DIR__ . '/includes/layout_tailwind.php';
