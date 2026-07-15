<?php
require_once __DIR__ . '/includes/Auth.php';
requireAdmin();
$user = getUsuarioActual();

if (empty($_SESSION['wabot_cleanup_csrf'])) {
    $_SESSION['wabot_cleanup_csrf'] = bin2hex(random_bytes(24));
}
$cleanupToken = $_SESSION['wabot_cleanup_csrf'];
$activePage = 'pruebas';
$pageTitle = 'Pruebas';
ob_start();
?>
<div class="max-w-4xl mx-auto py-4">
    <div class="mb-8">
        <p class="text-sm font-medium text-amber-600 mb-1">Herramientas temporales de corrección</p>
        <h1 class="text-3xl font-bold text-slate-900">Pruebas</h1>
        <p class="text-slate-500 mt-2">Disponible durante esta etapa. Se retirará antes de entregar WC.</p>
    </div>

    <section class="rounded-2xl border border-amber-200 bg-amber-50/50 p-6 shadow-sm">
        <div class="flex items-start gap-4">
            <div class="w-11 h-11 shrink-0 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center">
                <i class="bi bi-eraser text-xl"></i>
            </div>
            <div class="flex-1">
                <h2 class="font-bold text-slate-900 text-lg">Limpiar datos de prueba</h2>
                <p class="text-sm text-slate-600 mt-1">Elimina únicamente filas de interacción: conversaciones, mensajes de WhatsApp, mensajes del Chatbot, prospectos, métricas e IDs procesados.</p>
                <p class="text-sm text-emerald-700 font-medium mt-3">Se conservan usuarios, Base de Conocimiento, configuración, sucursales, agendas, servicios, horarios, bloqueos y citas.</p>
                <button id="open-cleanup" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600">
                    <i class="bi bi-eraser"></i> Limpiar datos de prueba
                </button>
            </div>
        </div>
    </section>
    <p id="cleanup-result" class="hidden mt-5 rounded-lg px-4 py-3 text-sm font-medium"></p>
</div>

<div id="cleanup-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 modal-overlay">
    <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
        <div class="p-6 border-b border-slate-100">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Acción de pruebas</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-900">¿Limpiar datos de interacción?</h2>
                </div>
                <button id="close-cleanup" class="text-slate-400 hover:text-slate-700 text-2xl leading-none" aria-label="Cerrar">&times;</button>
            </div>
            <p class="mt-3 text-sm leading-6 text-slate-600">No borra tablas ni estructura. Tampoco toca agendas, servicios, sucursales, horarios, bloqueos ni citas.</p>
        </div>
        <div class="p-6">
            <label class="block text-sm font-medium text-slate-700 mb-2" for="cleanup-confirmation">Escribí <strong>LIMPIAR</strong> para continuar</label>
            <input id="cleanup-confirmation" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200" autocomplete="off">
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-100 p-4">
            <button id="cancel-cleanup" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancelar</button>
            <button id="run-cleanup" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Limpiar datos</button>
        </div>
    </div>
</div>

<script>
(() => {
    const modal = document.getElementById('cleanup-modal');
    const input = document.getElementById('cleanup-confirmation');
    const result = document.getElementById('cleanup-result');
    const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); input.value = ''; input.focus(); };
    const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
    document.getElementById('open-cleanup').addEventListener('click', open);
    document.getElementById('close-cleanup').addEventListener('click', close);
    document.getElementById('cancel-cleanup').addEventListener('click', close);
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    document.getElementById('run-cleanup').addEventListener('click', async () => {
        if (input.value.trim().toUpperCase() !== 'LIMPIAR') { input.focus(); return; }
        const button = document.getElementById('run-cleanup');
        button.disabled = true; button.textContent = 'Limpiando…';
        try {
            const response = await fetch('ajax/limpiar_datos_prueba.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf: <?= json_encode($cleanupToken) ?>, confirmacion: input.value })
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.error || 'No se pudo completar la limpieza.');
            const total = Object.values(data.eliminados || {}).reduce((sum, value) => sum + Number(value || 0), 0);
            result.textContent = `Listo: se eliminaron ${total} registros de prueba. La estructura y la agenda se conservaron.`;
            result.className = 'mt-5 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800';
            close();
        } catch (error) {
            result.textContent = error.message;
            result.className = 'mt-5 rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-800';
        } finally {
            button.disabled = false; button.textContent = 'Limpiar datos';
        }
    });
})();
</script>
<?php
$mainContent = ob_get_clean();
require __DIR__ . '/includes/layout_tailwind.php';
