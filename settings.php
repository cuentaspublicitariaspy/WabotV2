<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/EnvWriter.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/KnowledgeManager.php';
requireLogin();
$user = getUsuarioActual();
$userRol = $user['rol'] ?? '';
if (!in_array($userRol, ['super_admin', 'admin'])) {
    header('Location: index.php');
    exit;
}
$isSuperAdmin = $userRol === 'super_admin';

$db = Database::getConnection();
$knowledge = new KnowledgeManager();

$mainTabs = [
    'credenciales' => ['label' => 'Credenciales', 'super' => true, 'admin' => false],
    'comunicacion' => ['label' => 'Comunicaci&oacute;n Inteligente', 'super' => true, 'admin' => true],
    'smtp' => ['label' => 'SMTP', 'super' => true, 'admin' => false],
];
$visibleMain = array_filter($mainTabs, fn($t) => $isSuperAdmin ? $t['super'] : $t['admin']);
$visibleMainKeys = array_keys($visibleMain);
$firstMain = $visibleMainKeys[0] ?? 'credenciales';

$subTabs = [
    'whatsapp' => ['label' => 'WhatsApp', 'super' => true, 'admin' => false],
    'chatbot' => ['label' => 'Chatbot', 'super' => true, 'admin' => true],
    'knowledge' => ['label' => 'Conocimiento', 'super' => true, 'admin' => true],
];
$visibleSub = array_filter($subTabs, fn($t) => $isSuperAdmin ? $t['super'] : $t['admin']);
$visibleSubKeys = array_keys($visibleSub);
$firstSub = $visibleSubKeys[0] ?? 'whatsapp';

$db->exec("CREATE TABLE IF NOT EXISTS widget_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  api_key VARCHAR(64) NULL UNIQUE,
  enabled TINYINT(1) DEFAULT 1,
  position VARCHAR(10) DEFAULT 'right',
  primary_color VARCHAR(7) DEFAULT '#2F63E9',
  secondary_color VARCHAR(7) DEFAULT '#F3F4F6',
  welcome_title VARCHAR(255) DEFAULT 'Asistente',
  welcome_subtitle VARCHAR(255) DEFAULT 'Online',
  whatsapp_number VARCHAR(30) DEFAULT '',
  license_key VARCHAR(64) DEFAULT '',
  response_mode ENUM('ai','human') DEFAULT 'ai',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
try {
    // Las versiones anteriores creaban una clave temporal wgt_. WC debe esperar la wak_ emitida por WS.
    $db->exec("ALTER TABLE widget_config MODIFY api_key VARCHAR(64) NULL");
} catch (PDOException $e) {
    // La instalación nueva ya nace con la estructura correcta.
}

$config = $db->query("SELECT * FROM widget_config ORDER BY id DESC LIMIT 1")->fetch();
if (!$config) {
    $db->prepare("INSERT INTO widget_config (api_key) VALUES (NULL)")->execute();
    $config = $db->query("SELECT * FROM widget_config ORDER BY id DESC LIMIT 1")->fetch();
}
$chatbotApiKey = EnvWriter::get('CHATBOT_API_KEY');
if ($chatbotApiKey === '' && !empty($config['api_key']) && str_starts_with($config['api_key'], 'wgt_')) {
    $db->prepare("UPDATE widget_config SET api_key=NULL WHERE id=?")->execute([$config['id']]);
    $config = $db->query("SELECT * FROM widget_config ORDER BY id DESC LIMIT 1")->fetch();
}
if ($chatbotApiKey !== '' && $chatbotApiKey !== $config['api_key']) {
    $db->prepare("UPDATE widget_config SET api_key=? WHERE id=?")->execute([$chatbotApiKey, $config['id']]);
    $config = $db->query("SELECT * FROM widget_config ORDER BY id DESC LIMIT 1")->fetch();
}

$activePage = 'settings';
$pageTitle = 'Configuración del sistema';

$msg = '';
$msgType = '';

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
        $chatbotApiKey = trim($_POST['chatbot_api_key'] ?? '');
        if ($licenseKey === '' && $chatbotApiKey === '') {
            $msg = 'Ingresá al menos una clave para guardar';
            $msgType = 'error';
        }
        if ($licenseKey !== '') {
            EnvWriter::set('LICENSE_KEY', $licenseKey);
            $db->prepare("UPDATE widget_config SET license_key=? WHERE id=?")->execute([$licenseKey, $config['id']]);
            $msg = 'License Key guardada';
            $msgType = 'success';
        }
        if ($chatbotApiKey !== '') {
            EnvWriter::set('CHATBOT_API_KEY', $chatbotApiKey);
            $db->prepare("UPDATE widget_config SET api_key=? WHERE id=?")->execute([$chatbotApiKey, $config['id']]);
            $config = $db->query("SELECT * FROM widget_config ORDER BY id DESC LIMIT 1")->fetch();
            $msg = $msg ? 'Credenciales guardadas' : 'API Key del Chatbot guardada';
            $msgType = 'success';
        }
    }

    if ($section === 'widget') {
        $licenseKey = trim($_POST['license_key'] ?? '');
        $primaryColor = trim($_POST['primary_color'] ?? '#2F63E9');
        $welcomeTitle = trim($_POST['welcome_title'] ?? 'Asistente');
        $welcomeSubtitle = trim($_POST['welcome_subtitle'] ?? 'Online');
        $stmt = $db->prepare("UPDATE widget_config SET primary_color=?, welcome_title=?, welcome_subtitle=? WHERE id=?");
        $stmt->execute([$primaryColor, $welcomeTitle, $welcomeSubtitle, $config['id']]);
        if ($licenseKey) {
            EnvWriter::set('LICENSE_KEY', $licenseKey);
            $db->prepare("UPDATE widget_config SET license_key=? WHERE id=?")->execute([$licenseKey, $config['id']]);
        }
        $config = $db->query("SELECT * FROM widget_config ORDER BY id DESC LIMIT 1")->fetch();
        $msg = 'Chatbot guardado';
        $msgType = 'success';
    }

    if ($section === 'knowledge') {
        $action = $_POST['action'] ?? '';
        if ($action === 'add_text') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            if ($title && $content) {
                if ($knowledge->add($title, $content)) {
                    $msg = 'Fuente agregada';
                    $msgType = 'success';
                } else {
                    $msg = 'Límite de 5 fuentes alcanzado o datos incompletos';
                    $msgType = 'error';
                }
            } else {
                $msg = 'Completá título y contenido';
                $msgType = 'error';
            }
        }
        if ($action === 'add_file') {
            $title = trim($_POST['title'] ?? '');
            if ($title && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $content = file_get_contents($_FILES['file']['tmp_name']);
                if ($knowledge->add($title, $content)) {
                    $msg = 'Archivo subido como fuente';
                    $msgType = 'success';
                } else {
                    $msg = 'Límite de 5 fuentes alcanzado';
                    $msgType = 'error';
                }
            } else {
                $msg = 'Completá título y seleccioná un archivo';
                $msgType = 'error';
            }
        }
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id && $knowledge->delete($id)) {
                $msg = 'Fuente eliminada';
                $msgType = 'success';
            }
        }
    }
}

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
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$callbackUrl = "$scheme://{$_SERVER['HTTP_HOST']}" . ($basePath === '' || $basePath === '/' ? '' : $basePath) . '/webhook.php';

$licenseConfigured = (EnvWriter::get('LICENSE_KEY') ?: ($config['license_key'] ?? '')) !== '';
$chatbotConfigured = EnvWriter::get('CHATBOT_API_KEY') !== '';
$apiBase = 'https://wabot-cdn.vercel.app';

$knowledgeSources = $knowledge->getAll();
$knowledgeCount = $knowledge->count();

ob_start();
?>
<style>
.tab-btn, .sub-tab-btn { padding: 10px 20px; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s; font-size: 14px; font-weight: 500; color: #64748b; }
.tab-btn:hover, .sub-tab-btn:hover { color: #0f172a; }
.tab-btn.active, .sub-tab-btn.active { color: #059669; border-bottom-color: #059669; }
.main-tab-content, .sub-tab-content { display: none; }
.main-tab-content.active, .sub-tab-content.active { display: block; }
</style>

<div class="max-w-3xl mx-auto">
  <h1 class="text-2xl font-bold text-slate-800 mb-6">Configuración del Sistema</h1>

  <?php if ($msg): ?>
  <div id="save-notice" role="status" class="mb-4 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2 shadow-sm <?= $msgType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
    <span class="w-5 h-5 rounded-full flex items-center justify-center <?= $msgType === 'success' ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white' ?>"><?= $msgType === 'success' ? '✓' : '!' ?></span>
    <?= htmlspecialchars($msg) ?>
  </div>
  <?php endif; ?>

  <div class="flex gap-1 border-b border-slate-200 mb-6 overflow-x-auto">
    <?php $first = true; foreach ($visibleMain as $key => $tab): ?>
    <div class="tab-btn <?= $first ? 'active' : '' ?>" onclick="switchMain('<?= $key ?>')" data-main="<?= $key ?>"><?= $tab['label'] ?></div>
    <?php $first = false; endforeach; ?>
  </div>

  <!-- ===== CREDENCIALES ===== -->
  <?php if ($isSuperAdmin): ?>
  <div id="main-credenciales" class="main-tab-content <?= $firstMain === 'credenciales' ? 'active' : '' ?>">
    <div class="bg-white border border-slate-100 rounded-2xl p-6">
      <h2 class="text-lg font-bold text-slate-700 mb-1">Credenciales</h2>
      <p class="text-sm text-slate-500 mb-5">Claves entregadas por WS para habilitar este WC y su Chatbot.</p>
      <form method="POST" class="space-y-4 mb-6">
        <input type="hidden" name="section" value="license">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">License Key</label>
          <input type="password" name="license_key" value="" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-xs" placeholder="<?= $licenseConfigured ? 'Guardada. Pegá una nueva clave para reemplazarla' : 'Ingresá la License Key' ?>" autocomplete="new-password">
          <p class="text-xs mt-1 <?= $licenseConfigured ? 'text-emerald-600' : 'text-slate-400' ?>"><?= $licenseConfigured ? '✓ License Key configurada y oculta.' : 'Pendiente de configuración.' ?></p>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">API Key del Chatbot</label>
          <input type="password" name="chatbot_api_key" value="" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-xs" placeholder="<?= $chatbotConfigured ? 'Guardada. Pegá una nueva clave para reemplazarla' : 'wak_...' ?>" autocomplete="new-password">
          <p class="text-xs mt-1 <?= $chatbotConfigured ? 'text-emerald-600' : 'text-slate-400' ?>"><?= $chatbotConfigured ? '✓ API Key del Chatbot configurada y oculta.' : 'Debe coincidir con la API Key generada para este cliente en WS.' ?></p>
        </div>
        <div class="flex justify-end"><button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition shadow-lg">Guardar credenciales</button></div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- ===== COMUNICACIÓN INTELIGENTE ===== -->
  <div id="main-comunicacion" class="main-tab-content <?= $firstMain === 'comunicacion' ? 'active' : '' ?>">
    <h2 class="text-lg font-bold text-slate-700">Comunicación Inteligente</h2>
    <p class="text-sm text-slate-500 mb-5">Configuración de canales de comunicación y conocimiento de la IA.</p>

    <div class="flex gap-1 border-b border-slate-200 mb-5 overflow-x-auto">
      <?php $subFirst = true; foreach ($visibleSub as $key => $tab): ?>
      <div class="sub-tab-btn <?= $subFirst ? 'active' : '' ?>" onclick="switchSub('<?= $key ?>')" data-sub="<?= $key ?>"><?= $tab['label'] ?></div>
      <?php $subFirst = false; endforeach; ?>
    </div>

    <!-- SUB: WhatsApp -->
    <?php if ($isSuperAdmin): ?>
    <div id="sub-whatsapp" class="sub-tab-content <?= $firstSub === 'whatsapp' ? 'active' : '' ?>">
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
            <input type="password" name="whatsapp_token" value="" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-xs" placeholder="EAATuTokenDeAcceso..." autocomplete="new-password">
            <p class="text-xs text-slate-400 mt-1">Token de acceso permanente del sistema o de la app.</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number ID</label>
            <input type="text" name="whatsapp_phone_number_id" value="" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="123456789012345">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">App Secret (opcional)</label>
            <input type="password" name="whatsapp_app_secret" value="" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-xs" placeholder="App Secret de Meta Developers" autocomplete="new-password">
            <p class="text-xs text-slate-400 mt-1">Para verificar firmas de webhook (recomendado).</p>
          </div>
          <div class="flex justify-end"><button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition shadow-lg">Guardar conexión</button></div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- SUB: Chatbot -->
    <div id="sub-chatbot" class="sub-tab-content <?= $firstSub === 'chatbot' ? 'active' : '' ?>">
      <div class="bg-white border border-slate-100 rounded-2xl p-6 mb-5">
        <h2 class="text-lg font-bold text-slate-700 mb-1">Chatbot</h2>
        <p class="text-sm text-slate-500 mb-5">Configuración del chatbot para incrustar en sitios web.</p>
        <form method="POST" class="space-y-4">
          <input type="hidden" name="section" value="widget">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Color primario</label>
              <div class="flex gap-2 max-w-xs">
                <input type="color" name="primary_color" value="<?= htmlspecialchars($config['primary_color'] ?? '#2F63E9') ?>" class="w-10 h-10 rounded cursor-pointer border border-slate-200">
                <input type="text" name="primary_color_text" value="<?= htmlspecialchars($config['primary_color'] ?? '#2F63E9') ?>" class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-xs" oninput="this.previousElementSibling.value=this.value">
              </div>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Título de bienvenida</label>
              <input type="text" name="welcome_title" value="<?= htmlspecialchars($config['welcome_title'] ?? 'Asistente') ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Subtítulo</label>
              <input type="text" name="welcome_subtitle" value="<?= htmlspecialchars($config['welcome_subtitle'] ?? 'Online') ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
          </div>
          <div class="flex justify-start pt-2"><button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition shadow-lg">Guardar chatbot</button></div>
        </form>
      </div>

      <div class="bg-white border border-slate-100 rounded-2xl p-6">
        <h5 class="text-sm font-semibold text-slate-700 mb-3">Código para incrustar</h5>
        <div class="bg-slate-900 rounded-xl overflow-hidden">
          <pre class="text-green-400 text-xs leading-relaxed px-4 py-3 m-0 overflow-x-auto" id="embed-code">&lt;script src="<?= $apiBase ?>/widget.js"
  data-api-key="<?= htmlspecialchars($config['api_key'] ?? '') ?>"
  data-api-base="<?= $apiBase ?>"&gt;&lt;/script&gt;</pre>
        </div>
        <button onclick="navigator.clipboard.writeText(document.getElementById('embed-code').textContent); this.textContent='Copiado!'; setTimeout(()=>this.textContent='Copiar c\u00f3digo',1500)" class="mt-3 px-5 py-2.5 bg-slate-800 text-white text-sm font-medium rounded-xl hover:bg-slate-900 transition">Copiar código</button>
      </div>
    </div>

    <!-- SUB: Conocimiento -->
    <div id="sub-knowledge" class="sub-tab-content <?= $firstSub === 'knowledge' ? 'active' : '' ?>">
      <div class="flex items-center justify-between mb-5">
        <h2 class="text-lg font-bold text-slate-700">Base de conocimiento</h2>
        <span class="text-sm font-medium <?= $knowledgeCount >= 5 ? 'text-amber-600' : 'text-slate-500' ?>"><?= $knowledgeCount ?>/5 fuentes</span>
      </div>

      <?php if ($knowledgeCount < 5): ?>
      <div class="bg-white border border-slate-100 rounded-2xl p-5 mb-6">
        <h5 class="text-sm font-semibold text-slate-700 mb-3">Agregar fuente</h5>
        <div class="flex gap-2 mb-4">
          <button type="button" onclick="toggleKnowledgeMode('text')" id="kbtn-text" class="px-4 py-1.5 text-sm rounded-lg font-medium transition bg-emerald-600 text-white">Pegar texto</button>
          <button type="button" onclick="toggleKnowledgeMode('file')" id="kbtn-file" class="px-4 py-1.5 text-sm rounded-lg font-medium transition bg-slate-100 text-slate-600 hover:bg-slate-200">Subir archivo</button>
        </div>

        <form method="POST" id="kform-text" class="space-y-3">
          <input type="hidden" name="section" value="knowledge">
          <input type="hidden" name="action" value="add_text">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Título</label>
            <input type="text" name="title" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Ej: Productos, Envíos, Horarios..." required>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Contenido</label>
            <textarea name="content" rows="6" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 resize-y" placeholder="Escribí acá el contenido de la fuente..." required></textarea>
          </div>
          <div class="flex justify-end"><button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition">Agregar fuente</button></div>
        </form>

        <form method="POST" enctype="multipart/form-data" id="kform-file" class="space-y-3 hidden">
          <input type="hidden" name="section" value="knowledge">
          <input type="hidden" name="action" value="add_file">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Título</label>
            <input type="text" name="title" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Ej: Política de devoluciones" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Archivo .txt</label>
            <input type="file" name="file" accept=".txt" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100" required>
          </div>
          <div class="flex justify-end"><button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition">Subir archivo</button></div>
        </form>
      </div>
      <?php endif; ?>

      <?php if (empty($knowledgeSources)): ?>
      <div class="text-center py-12 text-slate-400">
        <p class="text-sm">No hay fuentes de conocimiento. Agregá una usando el formulario de arriba.</p>
      </div>
      <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($knowledgeSources as $i => $src): ?>
        <div class="bg-white border border-slate-100 rounded-2xl p-5 flex items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold flex items-center justify-center shrink-0"><?= $i + 1 ?></span>
              <h5 class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($src['title']) ?></h5>
            </div>
            <p class="text-xs text-slate-400 ml-8 truncate"><?= htmlspecialchars($src['preview']) ?></p>
            <p class="text-xs text-slate-400 ml-8 mt-0.5"><?= number_format($src['size']) ?> caracteres</p>
          </div>
          <form method="POST" onsubmit="return confirm('Eliminar esta fuente?')">
            <input type="hidden" name="section" value="knowledge">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $src['id'] ?>">
            <button type="submit" class="text-red-400 hover:text-red-600 transition p-1" title="Eliminar">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            </button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ===== SMTP ===== -->
  <?php if ($isSuperAdmin): ?>
  <div id="main-smtp" class="main-tab-content <?= $firstMain === 'smtp' ? 'active' : '' ?>">
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
  <?php endif; ?>
</div>
<script>
function switchMain(name) {
  document.querySelectorAll('.main-tab-content').forEach(function(el) { el.classList.remove('active'); });
  document.querySelectorAll('.tab-btn').forEach(function(el) { el.classList.remove('active'); });
  document.getElementById('main-' + name).classList.add('active');
  document.querySelector('[data-main="' + name + '"]').classList.add('active');
}
function switchSub(name) {
  document.querySelectorAll('.sub-tab-content').forEach(function(el) { el.classList.remove('active'); });
  document.querySelectorAll('.sub-tab-btn').forEach(function(el) { el.classList.remove('active'); });
  document.getElementById('sub-' + name).classList.add('active');
  document.querySelector('[data-sub="' + name + '"]').classList.add('active');
}
document.addEventListener('DOMContentLoaded', function() {
  var saveNotice = document.getElementById('save-notice');
  if (saveNotice) {
    setTimeout(function() { saveNotice.style.transition = 'opacity .35s'; saveNotice.style.opacity = '0'; }, 5000);
    setTimeout(function() { saveNotice.remove(); }, 5400);
  }
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
function toggleKnowledgeMode(mode) {
  document.getElementById('kform-text').classList.toggle('hidden', mode !== 'text');
  document.getElementById('kform-file').classList.toggle('hidden', mode !== 'file');
  document.getElementById('kbtn-text').className = 'px-4 py-1.5 text-sm rounded-lg font-medium transition ' + (mode === 'text' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200');
  document.getElementById('kbtn-file').className = 'px-4 py-1.5 text-sm rounded-lg font-medium transition ' + (mode === 'file' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200');
}
</script>

<?php
$mainContent = ob_get_clean();
require __DIR__ . '/includes/layout_tailwind.php';
