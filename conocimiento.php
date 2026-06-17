<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/KnowledgeManager.php';
requireLogin();
$user = getUsuarioActual();

$knowledge = new KnowledgeManager();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $_POST['content'] ?? '';
    if ($knowledge->save($content)) { $message = 'Guardado correctamente'; $messageType = 'success'; }
    else { $message = 'Error al guardar'; $messageType = 'danger'; }
}

$content = $knowledge->getContent();
$stats = $knowledge->getStats();
$activePage = 'conocimiento';
$pageTitle = 'Conocimiento';
ob_start();
?>
<?php if ($message): ?>
<div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium <?= $messageType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
  <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-5">
  <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
    Base de conocimiento
  </h1>
  <div class="flex items-center gap-3 text-xs text-slate-400">
    <span class="flex items-center gap-1"><?= $stats['lines'] ?> líneas</span>
    <span class="flex items-center gap-1"><?= number_format($stats['size']) ?> bytes</span>
  </div>
</div>

<form method="POST">
  <div class="bg-white border border-slate-100 rounded-2xl overflow-hidden">
    <textarea name="content" class="w-full border-0 p-5 font-mono text-sm outline-none resize-y" style="min-height:400px;" rows="25"><?= htmlspecialchars($content) ?></textarea>
    <div class="px-5 py-3 border-t border-slate-100 flex justify-end">
      <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700 transition shadow-lg shadow-emerald-200">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
        Guardar conocimiento
      </button>
    </div>
  </div>
</form>

<?php
$mainContent = ob_get_clean();
require __DIR__ . '/includes/layout_tailwind.php';
