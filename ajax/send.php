<?php
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ChatManager.php';
require_once __DIR__ . '/../includes/WebhookHandler.php';
require_once __DIR__ . '/../includes/AgentRouter.php';
require_once __DIR__ . '/../includes/MetricsCollector.php';
require_once __DIR__ . '/../includes/TemplateManager.php';
requireLogin();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }
$conversacionId = (int)($_POST['conversacion_id'] ?? 0);
$mensaje = trim($_POST['mensaje'] ?? '');
if ($conversacionId <= 0 || $mensaje === '') { http_response_code(400); echo json_encode(['error'=>'Parámetros incompletos']); exit; }
$user = getUsuarioActual();
$chatManager = new ChatManager();
$conversacion = $chatManager->getConversacion($conversacionId);
if (!$conversacion) { http_response_code(404); echo json_encode(['error'=>'Conversación no encontrada']); exit; }

$ultimoIn = $chatManager->getUltimoMensajeInTime($conversacionId);
if ($ultimoIn !== null) {
    $ultimoTimestamp = strtotime($ultimoIn);
    $horasPasadas = (time() - $ultimoTimestamp) / 3600;
    if ($horasPasadas >= 24) {
        $tm = new TemplateManager();
        $plantillas = $tm->getActivas();
        http_response_code(403);
        echo json_encode([
            'error' => 'ventana_24h_expirada',
            'mensaje' => 'La ventana de 24 horas ha expirado. Usá una plantilla para contactar a este cliente.',
            'plantillas' => $plantillas,
        ]);
        exit;
    }
}

$router = new AgentRouter();
$router->asignarConversacion($conversacionId, $user['id']);
$chatManager->marcarLeido($conversacionId, $user['id']);
$mensajeInId = $chatManager->getUltimoMensajeInId($conversacionId);
$handler = new WebhookHandler();
$mensajeOutId = $handler->sendMessage($conversacion['wa_phone'], $mensaje, WHATSAPP_PHONE_NUMBER_ID, $user['id']);
if ($mensajeOutId !== null) {
    if ($mensajeInId) {
        $metrics = new MetricsCollector();
        $metrics->registrarRespuesta($conversacionId, $mensajeInId, $mensajeOutId, $user['id']);
    }
    echo json_encode(['success'=>true]);
} else { http_response_code(500); echo json_encode(['error'=>'Error al enviar mensaje']); }
