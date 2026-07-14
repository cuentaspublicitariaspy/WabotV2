<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/ProspectManager.php';

header('Content-Type: application/json');

// Los datos personales del visitante solo pueden llegar desde el mismo sitio
// donde está instalado WC; no basta con conocer una API Key pública.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$requestHost = strtolower((string) preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$source = $origin !== '' ? $origin : ($_SERVER['HTTP_REFERER'] ?? '');
$sourceHost = strtolower((string) parse_url($source, PHP_URL_HOST));
if ($sourceHost === '' || !hash_equals($requestHost, $sourceHost)) {
    http_response_code(403); echo json_encode(['success'=>false, 'error'=>'Origen no autorizado']); exit;
}
if ($origin !== '') { header('Access-Control-Allow-Origin: ' . $origin); header('Vary: Origin'); }
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

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
$stmt = $db->prepare("SELECT wc.id FROM widget_chats wc INNER JOIN widget_config cfg ON cfg.id = wc.widget_config_id WHERE wc.session_id = ? AND cfg.api_key = ? AND cfg.enabled = 1");
$stmt->execute([$session_id, $key]);
$chatId = (int) $stmt->fetchColumn();
if (!$chatId) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Configuración no encontrada']);
    exit;
}

$stmt = $db->prepare("UPDATE widget_chats SET visitor_name = COALESCE(NULLIF(?, ''), visitor_name), visitor_email = COALESCE(NULLIF(?, ''), visitor_email), visitor_phone = COALESCE(NULLIF(?, ''), visitor_phone) WHERE id = ?");
$stmt->execute([$name, $email, $phone, $chatId]);
if ($chatId) {
    (new ProspectManager())->vincular('chatbot', (string) $chatId, ['nombre' => $name, 'email' => $email, 'whatsapp' => $phone]);
}

echo json_encode(['success' => true]);
