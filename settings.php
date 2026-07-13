<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/EnvWriter.php';
requireLogin();
requireSuperAdmin();
$user = getUsuarioActual();

$activePage = 'settings';
$pageTitle = 'Configuración del sistema';

$msg = '';
$msgType = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';

    if ($section === 'smtp') {
        EnvWriter::set('SMTP_HOST', trim($_POST['smtp_host'] ?? ''));
        EnvWriter::set('SMTP_PORT', trim($_POST['smtp_port'] ?? '587'));
        EnvWriter::set('SMTP_USER', trim($_POST['smtp_user'] ?? ''));
        EnvWriter::set('SMTP_PASS', $_POST['smtp_pass'] ?? '');
        EnvWriter::set('SMTP_FROM_EMAIL', trim($_POST['smtp_from_email'] ?? ''));
        EnvWriter::set('SMTP_FROM_NAME', trim($_POST['smtp_from_name'] ?? ''));
        $msg = 'Configuración SMTP guardada';
        $msgType = 'success';
    }

    if ($section === 'whatsapp') {
        $action = $_POST['action'] ?? '';
        if ($action === 'regenerate_verify_token') {
            $newToken = 'wabotv2_verify_' . bin2hex(random_bytes(8));
            EnvWriter::set('WHATSAPP_VERIFY_TOKEN', $newToken);
            $msg = 'Verify Token regenerado';
            $msgType = 'success';
        }
        if ($action === 'save_connection') {
            $token = trim($_POST['whatsapp_token'] ?? '');
            $phoneId = trim($_POST['whatsapp_phone_number_id'] ?? '');
            $appSecret = trim($_POST['whatsapp_app_secret'] ?? '');
            if ($token && $phoneId) {
                EnvWriter::set('WHATSAPP_TOKEN', $token);
                EnvWriter::set('WHATSAPP_PHONE_NUMBER_ID', $phoneId);
                if ($appSecret) EnvWriter::set('WHATSAPP_APP_SECRET', $appSecret);
                $waToken = $token;
                $waPhoneId = $phoneId;
                $waConnected = true;
                if ($appSecret) $waAppSecret = $appSecret;
                $msg = 'Conexión WhatsApp guardada';
                $msgType = 'success';
            } else {
                $msg = 'Completá Token y Phone Number ID';
                $msgType = 'error';
            }
        }
    }

    if ($section === 'license') {
        $licenseKey = trim($_POST['license_key'] ?? '');
        if ($licenseKey) {
            EnvWriter::set('LICENSE_KEY', $licenseKey);
            $msg = 'License Key guardada';
            $msgType = 'success';
        }
    }
}

// Load current values
$smtpHost = EnvWriter::get('SMTP_HOST');
$smtpPort = EnvWriter::get('SMTP_PORT') ?: '587';
$smtpUser = EnvWriter::get('SMTP_USER');
$smtpPass = EnvWriter::get('SMTP_PASS');
$smtpFromEmail = EnvWriter::get('SMTP_FROM_EMAIL') ?: 'no-reply@wabot.app';
$smtpFromName = EnvWriter::get('SMTP_FROM_NAME') ?: 'Wabot';

$waToken = EnvWriter::get('WHATSAPP_TOKEN');
$waPhoneId = EnvWriter::get('WHATSAPP_PHONE_NUMBER_ID');
$waVerifyToken = EnvWriter::get('WHATSAPP_VERIFY_TOKEN');
$waAppSecret = EnvWriter::get('WHATSAPP_APP_SECRET');
if ($waVerifyToken === '') {
    $waVerifyToken = 'wabotv2_verify_' . bin2hex(random_bytes(8));
    EnvWriter::set('WHATSAPP_VERIFY_TOKEN', $waVerifyToken);
}
$waConnected = $waToken !== '' && $waPhoneId !== '';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$callbackUrl = "$scheme://{$_SERVER['HTTP_HOST']}/webhook.php";

$licenseDisplay = EnvWriter::get('LICENSE_KEY');

ob_start();
?>
<style>
.tab-btn { padding: 10px 20px; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s; font-size: 14px; font-weight: 500; color: #64748b; }
.tab-btn:hover { color: #0f172a; }
.tab-btn.active { color: #059669; border-bottom-color: #059669; }
.tab-content { display: none; }
.tab-content.active { display: block; }
</style>

<div class="max-w-3xl mx-auto">
  <h1 class="text-2xl font-bold text-slate-800 mb-6">Configuración del Sistema</h1>

  <?php if ($msg): ?>
  <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium <?= $msgType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="flex gap-1 border-b border-slate-200 mb-6">
    <div class="tab-btn active" onclick="switchTab('license')" data-tab="license">Licencia</div>
    <div class="tab-btn" onclick="switchTab('whatsapp')" data-tab="whatsapp">WhatsApp</div>
    <div class="tab-btn" onclick="switchTab('smtp')" data-tab="smtp">SMTP</div>
  </div>

  <!-- TAB: Licencia -->
  <div id="tab-license" class="tab-content active">
    <div class="bg-white border border-slate-100 rounded-2xl p-6">
      <h2 class="text-lg font-bold text-slate-700 mb-1">Licencia</h2>
      <p class="text-sm text-slate-500 mb-5">License Key del sistema. Sin esto, el webhook no procesa mensajes.</p>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="section" value="license">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">License Key</label>
          <input type="text" name="license_key" value="<?= htmlspecialchars($licenseDisplay) ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-xs" placeholder="Ingresá la License Key">
        </div>
        <div class="flex justify-end"><button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition shadow-lg">Guardar License Key</button></div>
      </form>
    </div>
  </div>

  <!-- TAB: WhatsApp -->
  <div id="tab-whatsapp" class="tab-content">
    <div class="bg-white border border-slate-100 rounded-2xl p-6">
      <h2 class="text-lg font-bold text-slate-700 mb-1">WhatsApp API</h2>
      <p class="text-sm text-slate-500 mb-5">Configuración de conexión con Meta WhatsApp API.</p>

      <?php if ($waConnected): ?>
      <div class="p-6 bg-emerald-50 border border-emerald-200 rounded-xl mb-5 text-center">
        <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m9.364-7.364A9 9 0 1112 3a9 9 0 017.364 4.636z"/></svg>
        </div>
        <h3 class="text-lg font-semibold text-slate-700 mb-2">WhatsApp ya está conectado</h3>
        <p class="text-sm text-slate-500 mb-4">La conexión con WhatsApp Business ya fue configurada.</p>
        <div class="bg-white rounded-xl p-4 max-w-sm mx-auto space-y-2 text-sm border border-emerald-100">
          <div class="flex justify-between"><span class="text-slate-500">Phone Number ID:</span><span class="text-slate-800 font-mono text-xs"><?= htmlspecialchars($waPhoneId) ?></span></div>
        </div>
      </div>
      <?php else: ?>
      <div class="flex items-center gap-3 p-4 bg-slate-50 rounded-xl mb-5">
        <span class="w-3 h-3 rounded-full bg-slate-300"></span>
        <span class="text-sm text-slate-500">No conectado</span>
      </div>
      <p class="text-xs text-slate-400 mb-5">Seguí los pasos de abajo para conectar WhatsApp.</p>
      <?php endif; ?>

      <!-- Step 1: Webhook -->
      <div class="flex items-center gap-3 mb-4">
        <span class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center">1</span>
        <h5 class="text-sm font-semibold text-slate-700">Configurar Webhook en Meta Developers</h5>
      </div>
      <p class="text-xs text-slate-500 mb-4">Copiá estos datos en tu app de Meta Developers → WhatsApp → Configuration → Webhook.</p>

      <div class="space-y-3 mb-5">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Callback URL</label>
          <div class="flex gap-2">
            <input type="text" readonly value="<?= htmlspecialchars($callbackUrl) ?>" class="flex-1 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none bg-slate-50 font-mono text-xs" id="cb-url">
            <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($callbackUrl) ?>'); this.textContent='Copiado!'; setTimeout(()=>this.textContent='Copiar',1500)" class="px-4 py-2.5 bg-slate-100 text-slate-700 text-sm rounded-xl hover:bg-slate-200 transition shrink-0">Copiar</button>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Verify Token</label>
          <div class="flex gap-2">
            <input type="text" readonly value="<?= htmlspecialchars($waVerifyToken) ?>" class="flex-1 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none bg-slate-50 font-mono text-xs" id="vt">
            <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($waVerifyToken) ?>'); this.textContent='Copiado!'; setTimeout(()=>this.textContent='Copiar',1500)" class="px-4 py-2.5 bg-slate-100 text-slate-700 text-sm rounded-xl hover:bg-slate-200 transition shrink-0">Copiar</button>
          </div>
          <p class="text-xs text-slate-400 mt-1">Usá este token al configurar el webhook.</p>
        </div>
      </div>

      <form method="POST" class="mb-5">
        <input type="hidden" name="section" value="whatsapp">
        <input type="hidden" name="action" value="regenerate_verify_token">
        <button type="submit" class="text-xs text-emerald-600 hover:text-emerald-700">Regenerar Verify Token</button>
      </form>

      <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl mb-5">
        <p class="text-xs text-amber-700 font-medium mb-1">✅ Después de configurar el webhook en Meta:</p>
        <p class="text-xs text-amber-600">Meta va a verificar el webhook automáticamente. Si ves "Conectado", pasá al Paso 2.</p>
      </div>

      <!-- Step 2: Connection data -->
      <div class="flex items-center gap-3 mb-4">
        <span class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold flex items-center justify-center">2</span>
        <h5 class="text-sm font-semibold text-slate-700">Ingresar datos de la app de Meta</h5>
      </div>
      <p class="text-xs text-slate-500 mb-4">En tu app de Meta Developers → WhatsApp → API Setup, copiá y pegá estos datos.</p>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="section" value="whatsapp">
        <input type="hidden" name="action" value="save_connection">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">WhatsApp Token</label>
          <input type="text" name="whatsapp_token" value="" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-xs" placeholder="EAATuTokenDeAcceso..." autocomplete="off">
          <p class="text-xs text-slate-400 mt-1">Token de acceso permanente del sistema o de la app.</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number ID</label>
          <input type="text" name="whatsapp_phone_number_id" value="" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="123456789012345">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">App Secret (opcional)</label>
          <input type="text" name="whatsapp_app_secret" value="" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-xs" placeholder="App Secret de Meta Developers" autocomplete="off">
          <p class="text-xs text-slate-400 mt-1">Para verificar firmas de webhook (recomendado).</p>
        </div>
        <div class="flex justify-end"><button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition shadow-lg">Guardar conexión</button></div>
      </form>
    </div>
  </div>

  <!-- TAB: SMTP -->
  <div id="tab-smtp" class="tab-content">
    <div class="bg-white border border-slate-100 rounded-2xl p-6">
      <h2 class="text-lg font-bold text-slate-700 mb-1">SMTP</h2>
      <p class="text-sm text-slate-500 mb-5">Para envío de correos de recuperación de contraseña.</p>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="section" value="smtp">
        <div class="grid grid-cols-2 gap-4">
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Host</label><input type="text" name="smtp_host" value="<?= htmlspecialchars($smtpHost) ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="smtp.gmail.com"></div>
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Puerto</label><input type="text" name="smtp_port" value="<?= htmlspecialchars($smtpPort) ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="587"></div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Usuario</label><input type="text" name="smtp_user" value="<?= htmlspecialchars($smtpUser) ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="tu@email.com" autocomplete="off"></div>
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Contraseña</label><input type="password" name="smtp_pass" value="<?= htmlspecialchars($smtpPass) ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="••••••••" autocomplete="off"></div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="block text-sm font-medium text-slate-700 mb-1">From Email</label><input type="email" name="smtp_from_email" value="<?= htmlspecialchars($smtpFromEmail) ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="no-reply@wabot.app"></div>
          <div><label class="block text-sm font-medium text-slate-700 mb-1">From Name</label><input type="text" name="smtp_from_name" value="<?= htmlspecialchars($smtpFromName) ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Wabot"></div>
        </div>
        <div class="flex justify-end"><button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition shadow-lg">Guardar SMTP</button></div>
      </form>
    </div>
  </div>
</div>

<script>
function switchTab(name) {
  document.querySelectorAll('.tab-content').forEach(function(el) { el.classList.remove('active'); });
  document.querySelectorAll('.tab-btn').forEach(function(el) { el.classList.remove('active'); });
  document.getElementById('tab-' + name).classList.add('active');
  document.querySelector('[data-tab="' + name + '"]').classList.add('active');
}
</script>

<?php
$mainContent = ob_get_clean();
require __DIR__ . '/includes/layout_tailwind.php';
