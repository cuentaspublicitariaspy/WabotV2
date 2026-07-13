<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/Database.php';

header('Content-Type: application/json');

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

$stmt = $db->prepare("SELECT id FROM widget_chats WHERE session_id = ?");
$stmt->execute([$session_id]);
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
