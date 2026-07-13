<?php
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ChatManager.php';
require_once __DIR__ . '/../includes/AgentRouter.php';
requireLogin();
header('Content-Type: application/json');
$chatManager = new ChatManager();
$user = getUsuarioActual();
$conversacionId = isset($_GET['conversacion_id']) ? (int)$_GET['conversacion_id'] : 0;
if ($conversacionId > 0) {
    echo json_encode(['conversacion' => $chatManager->getConversacion($conversacionId), 'mensajes' => $chatManager->getMensajes($conversacionId)]);
    exit;
}
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';
$esAdmin = in_array($user['rol'], ['super_admin', 'admin']);
$conversaciones = $chatManager->getConversaciones($search, $filter, (int)$user['id'], $esAdmin);
$router = new AgentRouter();
echo json_encode(['conversaciones' => $conversaciones, 'nuevosMensajes' => true, 'agentes_activos' => $router->getAgentesActivos()]);
