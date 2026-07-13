<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Database.php';
requireLogin();
requireAdmin();

$user = getUsuarioActual();
$db = Database::getConnection();
$activePage = 'widget';
$pageTitle = 'Widget de Chat';

$db->exec("CREATE TABLE IF NOT EXISTS widget_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  api_key VARCHAR(64) NOT NULL UNIQUE,
  enabled TINYINT(1) DEFAULT 1,
  position VARCHAR(10) DEFAULT 'right',
  primary_color VARCHAR(7) DEFAULT '#2F63E9',
  secondary_color VARCHAR(7) DEFAULT '#F3F4F6',
  welcome_title VARCHAR(255) DEFAULT '¡Hola!',
  welcome_subtitle VARCHAR(255) DEFAULT 'En qué podemos ayudarte?',
  whatsapp_number VARCHAR(20) DEFAULT '',
  license_key VARCHAR(64) DEFAULT '',
  response_mode ENUM('ai','human') DEFAULT 'ai',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
try { $db->exec("ALTER TABLE widget_config ADD COLUMN license_key VARCHAR(64) DEFAULT ''"); } catch (Exception $e) {}

$config = null;
$stmt = $db->query("SELECT * FROM widget_config ORDER BY id DESC LIMIT 1");
$config = $stmt->fetch();

function updateEnvVar($key, $value) {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) return false;
    $content = file_get_contents($envFile);
    $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
    $replacement = $key . '=' . $value;
    if (preg_match($pattern, $content)) {
        $content = preg_replace($pattern, $replacement, $content);
    } else {
        $content .= "\n" . $replacement;
    }
    return file_put_contents($envFile, $content) !== false;
}

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_config') {
        $apiKey = trim($_POST['api_key'] ?? '');
        $licenseKey = trim($_POST['license_key'] ?? '');
        $primaryColor = trim($_POST['primary_color'] ?? '#2F63E9');
        $welcomeTitle = trim($_POST['welcome_title'] ?? 'Asistente');
        $welcomeSubtitle = trim($_POST['welcome_subtitle'] ?? 'Online');

        if ($config) {
            $stmt = $db->prepare("UPDATE widget_config SET api_key=COALESCE(NULLIF(?,''), api_key), license_key=COALESCE(NULLIF(?,''), license_key), primary_color=?, welcome_title=?, welcome_subtitle=? WHERE id=?");
            $stmt->execute([$apiKey, $licenseKey, $primaryColor, $welcomeTitle, $welcomeSubtitle, $config['id']]);
        } else {
            if (!$apiKey) $apiKey = 'wgt_' . bin2hex(random_bytes(16));
            $stmt = $db->prepare("INSERT INTO widget_config (api_key, license_key, primary_color, welcome_title, welcome_subtitle) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$apiKey, $licenseKey, $primaryColor, $welcomeTitle, $welcomeSubtitle]);
        }

        if ($licenseKey) updateEnvVar('LICENSE_KEY', $licenseKey);

        $msg = 'Configuración guardada.';
        $msgType = 'success';
        $stmt = $db->query("SELECT * FROM widget_config ORDER BY id DESC LIMIT 1");
        $config = $stmt->fetch();
    }
}

$apiBase = 'https://wabot-cdn.vercel.app';
$apiKey = $config ? $config['api_key'] : '';
$licenseKey = $config ? ($config['license_key'] ?? '') : '';

ob_start();
?>
<div class="max-w-3xl mx-auto">
  <h1 class="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2">
    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
    Widget de Chat
  </h1>

  <?php if ($msg): ?>
  <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium <?= $msgType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- Config Form -->
  <div class="bg-white border border-slate-100 rounded-2xl p-6 mb-5">
    <h5 class="text-sm font-semibold text-slate-700 mb-4">Configuración del Widget</h5>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="save_config">

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">API Key</label>
          <input type="text" name="api_key" value="<?= htmlspecialchars($config['api_key'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-xs">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">License Key</label>
          <input type="text" name="license_key" value="<?= htmlspecialchars($config['license_key'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-xs">
        </div>
      </div>

      <div class="flex items-center gap-3">
        <input type="checkbox" name="enabled" id="enabled" value="1" <?= ($config && $config['enabled']) ? 'checked' : '' ?> class="w-4 h-4 text-emerald-600 rounded border-slate-300">
        <label for="enabled" class="text-sm font-medium text-slate-700">Widget habilitado</label>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Color primario</label>
        <div class="flex gap-2 max-w-xs">
          <input type="color" name="primary_color" value="<?= htmlspecialchars($config['primary_color'] ?? '#2F63E9') ?>" class="w-10 h-10 rounded cursor-pointer border border-slate-200">
          <input type="text" name="primary_color_text" value="<?= htmlspecialchars($config['primary_color'] ?? '#2F63E9') ?>" class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-xs" oninput="this.previousElementSibling.value=this.value" onchange="this.previousElementSibling.value=this.value">
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



      <div class="flex justify-start pt-2">
        <button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition shadow-lg">Guardar</button>
      </div>
    </form>
  </div>

  <!-- Embed Code -->
  <div class="bg-white border border-slate-100 rounded-2xl p-6 mb-5">
    <h5 class="text-sm font-semibold text-slate-700 mb-3">Código para incrustar</h5>
    <div class="bg-slate-900 rounded-xl overflow-hidden">
      <pre class="text-green-400 text-xs leading-relaxed px-4 py-3 m-0 overflow-x-auto" id="embed-code">&lt;script src="https://wabot-cdn.vercel.app/widget.js"
  data-api-key="<?= htmlspecialchars($apiKey) ?>"
  data-api-base="https://wabot-cdn.vercel.app"&gt;&lt;/script&gt;</pre>
    </div>
    <button id="copy-embed-btn" class="mt-3 px-5 py-2.5 bg-slate-800 text-white text-sm font-medium rounded-xl hover:bg-slate-900 transition">Copiar código</button>
  </div>


</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var copyBtn = document.getElementById('copy-embed-btn');
  if (copyBtn) {
    copyBtn.addEventListener('click', function() {
      var text = document.getElementById('embed-code').textContent;
      navigator.clipboard.writeText(text).then(function() {
        copyBtn.textContent = 'Copiado!';
        setTimeout(function() { copyBtn.textContent = 'Copiar código'; }, 1500);
      });
    });
  }
  var colorInputs = document.querySelectorAll('input[type="color"]');
  colorInputs.forEach(function(c) {
    c.addEventListener('input', function() {
      var textInput = this.parentElement.nextElementSibling;
      if (textInput) textInput.value = this.value;
    });
  });
  var textInputs = document.querySelectorAll('input[name="primary_color_text"]');
  textInputs.forEach(function(t) {
    t.addEventListener('input', function() {
      var colorInput = this.parentElement.previousElementSibling?.querySelector('input[type="color"]');
      if (colorInput) colorInput.value = this.value;
    });
  });
});
</script>
<?php
$mainContent = ob_get_clean();
require __DIR__ . '/includes/layout_tailwind.php';
