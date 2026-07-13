<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/KnowledgeManager.php';
require_once __DIR__ . '/../includes/License.php';

header('Content-Type: application/json');

$key = $_GET['key'] ?? '';
if (!$key) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'api_key requerida']);
    exit;
}

$db = Database::getConnection();
$stmt = $db->prepare("SELECT * FROM widget_config WHERE api_key = ? AND enabled = 1");
$stmt->execute([$key]);
$config = $stmt->fetch();

if (!$config) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Configuración no encontrada']);
    exit;
}

if (!License::check()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Licencia inactiva']);
    exit;
}

$kb = new KnowledgeManager();
$systemPrompt = $kb->getSystemPrompt();

echo json_encode([
    'success' => true,
    'config' => [
        'primary_color' => $config['primary_color'],
        'secondary_color' => $config['secondary_color'],
        'welcome_title' => $config['welcome_title'],
        'welcome_subtitle' => $config['welcome_subtitle'],
        'response_mode' => $config['response_mode'],
    ],
    'knowledge_base' => $systemPrompt,
]);
