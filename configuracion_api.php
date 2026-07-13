<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/EnvWriter.php';
requireLogin();
requireAdmin();

$user = getUsuarioActual();
$activePage = 'config_api';
$pageTitle = 'Configuración de WhatsApp';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$callbackUrl = "$scheme://$host/webhook.php";

$currentToken = EnvWriter::get('WHATSAPP_TOKEN');
$currentPhoneId = EnvWriter::get('WHATSAPP_PHONE_NUMBER_ID');
$currentVerifyToken = EnvWriter::get('WHATSAPP_VERIFY_TOKEN');
$currentAppSecret = EnvWriter::get('WHATSAPP_APP_SECRET');

if ($currentVerifyToken === '') {
    $currentVerifyToken = 'wabotv2_verify_' . bin2hex(random_bytes(8));
    EnvWriter::set('WHATSAPP_VERIFY_TOKEN', $currentVerifyToken);
}

$isConnected = $currentToken !== '' && $currentPhoneId !== '';

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'regenerate_verify_token') {
        $currentVerifyToken = 'wabotv2_verify_' . bin2hex(random_bytes(8));
        EnvWriter::set('WHATSAPP_VERIFY_TOKEN', $currentVerifyToken);
        $msg = 'Verify Token regenerado correctamente';
        $msgType = 'success';
    }

    if ($action === 'save_connection') {
        $token = trim($_POST['whatsapp_token'] ?? '');
        $phoneId = trim($_POST['whatsapp_phone_number_id'] ?? '');
        $appSecret = trim($_POST['whatsapp_app_secret'] ?? '');

        if ($token && $phoneId) {
            EnvWriter::set('WHATSAPP_TOKEN', $token);
            EnvWriter::set('WHATSAPP_PHONE_NUMBER_ID', $phoneId);
            if ($appSecret) {
                EnvWriter::set('WHATSAPP_APP_SECRET', $appSecret);
            }
            $currentToken = $token;
            $currentPhoneId = $phoneId;
            $currentAppSecret = $appSecret ?: $currentAppSecret;
            $isConnected = true;
            $msg = 'Conexión guardada correctamente';
            $msgType = 'success';
        } else {
            $msg = 'Completá Token y Phone Number ID';
            $msgType = 'error';
        }
    }
}

ob_start();
?>
<div class="max-w-2xl mx-auto">
  <h1 class="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2">
    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
    Configuración de WhatsApp
  </h1>

  <?php if ($msg): ?>
  <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium <?= $msgType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <?php if ($isConnected): ?>

  <div class="bg-white border border-slate-100 rounded-2xl p-8 text-center">
    <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-4">
      <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m9.364-7.364A9 9 0 1112 3a9 9 0 017.364 4.636z"/></svg>
    </div>
    <h2 class="text-lg font-semibold text-slate-700 mb-2">WhatsApp ya está conectado</h2>
    <p class="text-sm text-slate-500 mb-4">La conexión con WhatsApp Business ya fue configurada.</p>
    <div class="bg-slate-50 rounded-xl p-4 max-w-sm mx-auto space-y-2 text-sm">
      <div class="flex justify-between"><span class="text-slate-500">Phone Number ID:</span><span class="text-slate-800 font-mono text-xs"><?= htmlspecialchars($currentPhoneId) ?></span></div>
    </div>
  </div>

  <?php else: ?>

  <div class="bg-white border border-slate-100 rounded-2xl p-6 mb-5">
    <div class="flex items-center gap-3">
      <span class="w-3 h-3 rounded-full bg-slate-300"></span>
      <span class="text-sm text-slate-500">No conectado</span>
    </div>
    <p class="text-xs text-slate-400 mt-3">Seguí los pasos de abajo para conectar WhatsApp.</p>
  </div>

  <div class="bg-white border border-slate-100 rounded-2xl p-6 mb-5">
    <div class="flex items-center gap-3 mb-4">
      <span class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center">1</span>
      <h5 class="text-sm font-semibold text-slate-700">Configurar Webhook en Meta Developers</h5>
    </div>
    <p class="text-xs text-slate-500 mb-4">Copiá estos datos en tu app de Meta Developers &rarr; WhatsApp &rarr; Configuration &rarr; Webhook.</p>

    <div class="space-y-3">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Callback URL</label>
        <div class="flex gap-2">
          <input type="text" readonly value="<?= htmlspecialchars($callbackUrl) ?>" class="flex-1 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none bg-slate-50 font-mono text-xs" id="callback-url">
          <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($callbackUrl) ?>'); this.textContent='Copiado!'; setTimeout(()=>this.textContent='Copiar',1500)" class="px-4 py-2.5 bg-slate-100 text-slate-700 text-sm rounded-xl hover:bg-slate-200 transition shrink-0">Copiar</button>
        </div>
      </div>

      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Verify Token</label>
        <div class="flex gap-2">
          <input type="text" readonly value="<?= htmlspecialchars($currentVerifyToken) ?>" class="flex-1 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none bg-slate-50 font-mono text-xs" id="verify-token">
          <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($currentVerifyToken) ?>'); this.textContent='Copiado!'; setTimeout(()=>this.textContent='Copiar',1500)" class="px-4 py-2.5 bg-slate-100 text-slate-700 text-sm rounded-xl hover:bg-slate-200 transition shrink-0">Copiar</button>
        </div>
        <p class="text-xs text-slate-400 mt-1">Usá este token al configurar el webhook.</p>
      </div>
    </div>

    <form method="POST" class="mt-3">
      <input type="hidden" name="action" value="regenerate_verify_token">
      <button type="submit" class="text-xs text-emerald-600 hover:text-emerald-700">Regenerar Verify Token</button>
    </form>

    <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-xl">
      <p class="text-xs text-amber-700 font-medium mb-1">✅ Después de configurar el webhook en Meta:</p>
      <p class="text-xs text-amber-600">Meta va a verificar el webhook automáticamente. Si ves "Conectado", pasá al Paso 2.</p>
    </div>
  </div>

  <div class="bg-white border border-slate-100 rounded-2xl p-6 mb-5">
    <div class="flex items-center gap-3 mb-4">
      <span class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold flex items-center justify-center">2</span>
      <h5 class="text-sm font-semibold text-slate-700">Ingresar datos de la app de Meta</h5>
    </div>
    <p class="text-xs text-slate-500 mb-4">En tu app de Meta Developers &rarr; WhatsApp &rarr; API Setup, copiá estos datos y pegalos abajo.</p>

    <form method="POST" class="space-y-4">
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
        <input type="text" name="whatsapp_app_secret" value="" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-xs" placeholder="App Secret de Meta Developers">
        <p class="text-xs text-slate-400 mt-1">Para verificar firmas de webhook (recomendado).</p>
      </div>

      <div class="flex justify-end">
        <button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition shadow-lg">Guardar conexión</button>
      </div>
    </form>
  </div>

  <?php endif; ?>
</div>
<?php
$mainContent = ob_get_clean();
require __DIR__ . '/includes/layout_tailwind.php';
