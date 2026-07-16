<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/Database.php';

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

$photo = trim((string)($config['agent_photo'] ?? ''));
if ($photo !== '' && !preg_match('#^https?://#i', $photo)) {
    $forwarded = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]);
    $scheme = $forwarded !== '' ? $forwarded : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');
    $basePath = rtrim(str_replace('\\', '/', dirname(dirname(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))))), '/');
    $photo = $scheme.'://'.($_SERVER['HTTP_HOST'] ?? '').$basePath.'/'.ltrim($photo, '/');
}

echo json_encode([
    'success' => true,
    'config' => [
        'position' => $config['position'],
        'primary_color' => $config['primary_color'],
        'secondary_color' => $config['secondary_color'],
        'welcome_title' => $config['welcome_title'],
        'welcome_subtitle' => $config['welcome_subtitle'],
        'agent_name' => $config['agent_name'] ?? $config['welcome_title'],
        'agent_photo' => $photo,
        'whatsapp_number' => $config['whatsapp_number'],
    ]
]);
