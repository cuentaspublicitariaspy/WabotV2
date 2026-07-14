<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/Database.php';

header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$requestHost = strtolower((string) preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$source = $origin !== '' ? $origin : ($_SERVER['HTTP_REFERER'] ?? '');
$sourceHost = strtolower((string) parse_url($source, PHP_URL_HOST));
if ($sourceHost === '' || !hash_equals($requestHost, $sourceHost)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Origen no autorizado']);
    exit;
}
if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

$key = $_GET['key'] ?? '';
$session_id = $_GET['session_id'] ?? '';

if (!$key || !$session_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'key y session_id requeridos']);
    exit;
}

$db = Database::getConnection();

$stmt = $db->prepare("SELECT id FROM widget_config WHERE api_key = ? AND enabled = 1");
$stmt->execute([$key]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Configuración no encontrada']);
    exit;
}

$stmt = $db->prepare("SELECT wc.id FROM widget_chats wc INNER JOIN widget_config cfg ON cfg.id = wc.widget_config_id WHERE wc.session_id = ? AND cfg.api_key = ?");
$stmt->execute([$session_id, $key]);
$chat = $stmt->fetch();

if (!$chat) {
    echo json_encode(['success' => true, 'messages' => []]);
    exit;
}

$stmt = $db->prepare("SELECT id, role, content, created_at FROM widget_messages WHERE chat_id = ? ORDER BY id ASC");
$stmt->execute([$chat['id']]);
$messages = $stmt->fetchAll();

$stmt = $db->prepare("UPDATE widget_chats SET unread = 0 WHERE id = ?");
$stmt->execute([$chat['id']]);

echo json_encode(['success' => true, 'messages' => $messages]);
