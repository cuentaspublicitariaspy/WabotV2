<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/EnvWriter.php';
requireLogin();
requireSuperAdmin();
$user = getUsuarioActual();

$db = Database::getConnection();

// Ensure widget_config table exists
$db->exec("CREATE TABLE IF NOT EXISTS widget_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  api_key VARCHAR(64) NOT NULL UNIQUE,
  enabled TINYINT(1) DEFAULT 1,
  position VARCHAR(10) DEFAULT 'right',
  primary_color VARCHAR(7) DEFAULT '#2F63E9',
  secondary_color VARCHAR(7) DEFAULT '#F3F4F6',
  welcome_title VARCHAR(255) DEFAULT 'Asistente',
  welcome_subtitle VARCHAR(255) DEFAULT 'Online',
  license_key VARCHAR(64) DEFAULT '',
  response_mode ENUM('ai','human') DEFAULT 'ai',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Get or create widget config
$widget = $db->query("SELECT * FROM widget_config ORDER BY id DESC LIMIT 1")->fetch();
if (!$widget) {
    $apiKey = 'wgt_' . bin2hex(random_bytes(16));
    $db->prepare("INSERT INTO widget_config (api_key) VALUES (?)")->execute([$apiKey]);
    $widget = $db->query("SELECT * FROM widget_config ORDER BY id DESC LIMIT 1")->fetch();
}

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
                $msg = 'Conexión WhatsApp guardada';
                $msgType = 'success';
            } else {
                $msg = 'Completá Token y Phone Number ID';
                $msgType = 'error';
            }
        }
    }

    if ($section === 'widget') {
        $licenseKey = trim($_POST['license_key'] ?? '');
        $primaryColor = trim($_POST['primary_color'] ?? '#2F63E9');
        $welcomeTitle = trim($_POST['welcome_title'] ?? 'Asistente');
        $welcomeSubtitle = trim($_POST['welcome_subtitle'] ?? 'Online');

        $stmt = $db->prepare("UPDATE widget_config SET primary_color=?, welcome_title=?, welcome_subtitle=? WHERE id=?");
        $stmt->execute([$primaryColor, $welcomeTitle, $welcomeSubtitle, $widget['id']]);

        if ($licenseKey) {
            EnvWriter::set('LICENSE_KEY', $licenseKey);
            $db->prepare("UPDATE widget_config SET license_key=? WHERE id=?")->execute([$licenseKey, $widget['id']]);
        }

        $widget = $db->query("SELECT * FROM widget_config ORDER BY id DESC LIMIT 1")->fetch();
        $msg = 'Configuración del widget guardada';
        $msgType = 'success';
    }

    if ($section === 'license') {
        $licenseKey = trim($_POST['license_key'] ?? '');
        if ($licenseKey) {
            EnvWriter::set('LICENSE_KEY', $licenseKey);
            $db->prepare("UPDATE widget_config SET license_key=? WHERE id=?")->execute([$licenseKey, $widget['id']]);
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

$envLicenseKey = EnvWriter::get('LICENSE_KEY');
$widgetLicenseKey = $widget['license_key'] ?? '';
$licenseDisplay = $widgetLicenseKey ?: $envLicenseKey;
$apiBase = 'https://wabot-cdn.vercel.app';

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
    <div class="tab-btn active" onclick="switchTab('smtp')" data-tab="smtp">SMTP</div>
    <div class="tab-btn" onclick="switchTab('whatsapp')" data-tab="whatsapp">WhatsApp</div>
    <div class="tab-btn" onclick="switchTab('widget')" data-tab="widget">Widget Web</div>
    <div class="tab-btn" onclick="switchTab('license')" data-tab="license">Licencia</div>
  </div>

  <!-- TAB: SMTP -->
  <div id="tab-smtp" class="tab-content active">
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

  <!-- TAB: WhatsApp -->
  <div id="tab-whatsapp" class="tab-content">
    <div class="bg-white border border-slate-100 rounded-2xl p-6">
      <h2 class="text-lg font-bold text-slate-700 mb-1">WhatsApp API</h2>
      <p class="text-sm text-slate-500 mb-5">Configuración de conexión con Meta WhatsApp API.</p>

      <?php if ($waConnected): ?>
      <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl mb-5">
        <p class="text-sm font-medium text-emerald-700">✓ WhatsApp conectado</p>
        <p class="text-xs text-emerald-600 mt-1">Phone Number ID: <?= htmlspecialchars($waPhoneId) ?></p>
      </div>
      <?php else: ?>
      <div class="flex items-center gap-3 p-4 bg-slate-50 rounded-xl mb-5">
        <span class="w-3 h-3 rounded-full bg-slate-300"></span>
        <span class="text-sm text-slate-500">No conectado</span>
      </div>
      <?php endif; ?>

      <div class="space-y-3 mb-5">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Callback URL</label>
          <div class="flex gap-2">
            <input type="text" readonly value="<?= htmlspecialchars($callbackUrl) ?>" class="flex-1 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none bg-slate-50 font-mono text-xs" id="cb-url">
            <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($callbackUrl) ?>'); this.textContent='Copiado!'; setTimeout(()=>this.textContent='Copiar',1000)" class="px-4 py-2.5 bg-slate-100 text-slate-700 text-sm rounded-xl hover:bg-slate-200 transition shrink-0">Copiar</button>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Verify Token</label>
          <div class="flex gap-2">
            <input type="text" readonly value="<?= htmlspecialchars($waVerifyToken) ?>" class="flex-1 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none bg-slate-50 font-mono text-xs" id="vt">
            <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($waVerifyToken) ?>'); this.textContent='Copiado!'; setTimeout(()=>this.textContent='Copiar',1000)" class="px-4 py-2.5 bg-slate-100 text-slate-700 text-sm rounded-xl hover:bg-slate-200 transition shrink-0">Copiar</button>
          </div>
        </div>
        <form method="POST">
          <input type="hidden" name="section" value="whatsapp">
          <input type="hidden" name="action" value="regenerate_verify_token">
          <button type="submit" class="text-xs text-emerald-600 hover:text-emerald-700">Regenerar Verify Token</button>
        </form>
      </div>

      <h5 class="text-sm font-semibold text-slate-700 mb-3">Datos de la app de Meta</h5>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="section" value="whatsapp">
        <input type="hidden" name="action" value="save_connection">
        <div><label class="block text-sm font-medium text-slate-700 mb-1">Token</label><input type="text" name="whatsapp_token" value="" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-xs" placeholder="EAATuToken..." autocomplete="off"></div>
        <div><label class="block text-sm font-medium text-slate-700 mb-1">Phone Number ID</label><input type="text" name="whatsapp_phone_number_id" value="" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="123456789012345"></div>
        <div><label class="block text-sm font-medium text-slate-700 mb-1">App Secret (opcional)</label><input type="text" name="whatsapp_app_secret" value="" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-xs" placeholder="App Secret de Meta" autocomplete="off"></div>
        <div class="flex justify-end"><button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition shadow-lg">Guardar WhatsApp</button></div>
      </form>
    </div>
  </div>

  <!-- TAB: Widget Web -->
  <div id="tab-widget" class="tab-content">
    <div class="bg-white border border-slate-100 rounded-2xl p-6 mb-5">
      <h2 class="text-lg font-bold text-slate-700 mb-1">Widget Web</h2>
      <p class="text-sm text-slate-500 mb-5">Configuración del widget de chat para incrustar en sitios web.</p>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="section" value="widget">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Color primario</label>
          <div class="flex gap-2 max-w-xs">
            <input type="color" name="primary_color" value="<?= htmlspecialchars($widget['primary_color'] ?? '#2F63E9') ?>" class="w-10 h-10 rounded cursor-pointer border border-slate-200">
            <input type="text" name="primary_color_text" value="<?= htmlspecialchars($widget['primary_color'] ?? '#2F63E9') ?>" class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-xs" oninput="this.previousElementSibling.value=this.value">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Título de bienvenida</label><input type="text" name="welcome_title" value="<?= htmlspecialchars($widget['welcome_title'] ?? 'Asistente') ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500"></div>
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Subtítulo</label><input type="text" name="welcome_subtitle" value="<?= htmlspecialchars($widget['welcome_subtitle'] ?? 'Online') ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500"></div>
        </div>
        <div><label class="block text-sm font-medium text-slate-700 mb-1">License Key</label><input type="text" name="license_key" value="<?= htmlspecialchars($licenseDisplay) ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-xs" placeholder="Ingresá la License Key"></div>
        <div class="flex justify-start pt-2"><button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition shadow-lg">Guardar Widget</button></div>
      </form>
    </div>

    <div class="bg-white border border-slate-100 rounded-2xl p-6">
      <h5 class="text-sm font-semibold text-slate-700 mb-3">Código para incrustar</h5>
      <div class="bg-slate-900 rounded-xl overflow-hidden">
        <pre class="text-green-400 text-xs leading-relaxed px-4 py-3 m-0 overflow-x-auto" id="embed-code">&lt;script src="<?= $apiBase ?>/widget.js"
  data-api-key="<?= htmlspecialchars($widget['api_key'] ?? '') ?>"
  data-api-base="<?= $apiBase ?>"&gt;&lt;/script&gt;</pre>
      </div>
      <button onclick="navigator.clipboard.writeText(document.getElementById('embed-code').textContent); this.textContent='Copiado!'; setTimeout(()=>this.textContent='Copiar código',1500)" class="mt-3 px-5 py-2.5 bg-slate-800 text-white text-sm font-medium rounded-xl hover:bg-slate-900 transition">Copiar código</button>
    </div>
  </div>

  <!-- TAB: Licencia -->
  <div id="tab-license" class="tab-content">
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
</div>

<script>
function switchTab(name) {
  document.querySelectorAll('.tab-content').forEach(function(el) { el.classList.remove('active'); });
  document.querySelectorAll('.tab-btn').forEach(function(el) { el.classList.remove('active'); });
  document.getElementById('tab-' + name).classList.add('active');
  document.querySelector('[data-tab="' + name + '"]').classList.add('active');
}
// Sync color inputs
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('input[type="color"]').forEach(function(c) {
    c.addEventListener('input', function() {
      var textInput = this.parentElement.nextElementSibling;
      if (textInput) textInput.value = this.value;
    });
  });
  document.querySelectorAll('input[name="primary_color_text"]').forEach(function(t) {
    t.addEventListener('input', function() {
      var colorInput = this.parentElement.previousElementSibling.querySelector('input[type="color"]');
      if (colorInput) colorInput.value = this.value;
    });
  });
});
</script>

<?php
$mainContent = ob_get_clean();
require __DIR__ . '/includes/layout_tailwind.php';
