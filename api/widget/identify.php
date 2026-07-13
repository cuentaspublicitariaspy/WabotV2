<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$key = $_POST['key'] ?? '';
$session_id = $_POST['session_id'] ?? '';
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

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

$stmt = $db->prepare("UPDATE widget_chats SET visitor_name = COALESCE(NULLIF(?, ''), visitor_name), visitor_email = COALESCE(NULLIF(?, ''), visitor_email), visitor_phone = COALESCE(NULLIF(?, ''), visitor_phone) WHERE session_id = ?");
$stmt->execute([$name, $email, $phone, $session_id]);

echo json_encode(['success' => true]);
