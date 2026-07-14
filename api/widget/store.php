<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/ProspectManager.php';

header('Content-Type: application/json');
// El Chatbot se instala dentro del mismo dominio que WC. Solo exponemos CORS
// cuando el origen coincide con este hosting; así no se abre el endpoint a
// sitios de terceros que conozcan la API Key pública del Chatbot.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$requestHost = strtolower((string) preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$source = $origin !== '' ? $origin : ($_SERVER['HTTP_REFERER'] ?? '');
$sourceHost = strtolower((string) parse_url($source, PHP_URL_HOST));
if ($sourceHost === '' || !hash_equals($requestHost, $sourceHost)) {
    http_response_code(403); header('Content-Type: application/json'); echo json_encode(['success'=>false, 'error'=>'Origen no autorizado']); exit;
}
if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false]); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$key = trim($input['api_key'] ?? '');
$sessionId = trim($input['session_id'] ?? '');
$role = $input['role'] ?? '';
$content = trim($input['content'] ?? '');
if ($key === '' || $sessionId === '' || $content === '' || !in_array($role, ['visitor','assistant'], true)) {
    http_response_code(400); echo json_encode(['success'=>false, 'error'=>'Datos incompletos']); exit;
}

$db = Database::getConnection();
// Compatibilidad con instalaciones creadas antes de la memoria persistente.
try { $db->exec('ALTER TABLE widget_chats ADD COLUMN memory_summary LONGTEXT NULL'); } catch (Throwable $e) {}
try { $db->exec('ALTER TABLE widget_chats ADD COLUMN memory_message_count INT NOT NULL DEFAULT 0'); } catch (Throwable $e) {}
$stmt = $db->prepare('SELECT id FROM widget_config WHERE api_key = ? AND enabled = 1');
$stmt->execute([$key]);
$config = $stmt->fetch();
if (!$config) { http_response_code(404); echo json_encode(['success'=>false]); exit; }

$db->prepare('INSERT IGNORE INTO widget_chats (widget_config_id, session_id, unread) VALUES (?, ?, 0)')->execute([$config['id'], $sessionId]);
$stmt = $db->prepare('SELECT wc.id FROM widget_chats wc INNER JOIN widget_config cfg ON cfg.id = wc.widget_config_id WHERE wc.session_id = ? AND cfg.api_key = ?');
$stmt->execute([$sessionId, $key]);
$chatId = (int) $stmt->fetchColumn();
$prospecto = new ProspectManager();
$prospectoId = $prospecto->vincular('chatbot', (string) $chatId);
if ($role === 'visitor') {
    // Nombre, correo, teléfono y web se detectan localmente y se muestran
    // antes de pedir cualquier enriquecimiento a IA.
    $datosBasicos = $prospecto->detectarDatosBasicos($content);
    if ($datosBasicos) $prospecto->actualizar($prospectoId, $datosBasicos);
    $nombre = trim((string) ($datosBasicos['nombre'] ?? ''));
    $email = trim((string) ($datosBasicos['email'] ?? ''));
    $telefono = preg_replace('/\D+/', '', (string) ($datosBasicos['whatsapp'] ?? ''));
    if ($nombre !== '' || $email !== '' || $telefono !== '') {
        $stmt = $db->prepare("UPDATE widget_chats SET visitor_name = COALESCE(NULLIF(?, ''), visitor_name), visitor_email = COALESCE(NULLIF(?, ''), visitor_email), visitor_phone = COALESCE(NULLIF(?, ''), visitor_phone) WHERE id = ?");
        $stmt->execute([$nombre, $email, $telefono, $chatId]);
    }
}
$db->prepare('INSERT INTO widget_messages (chat_id, role, content) VALUES (?, ?, ?)')->execute([$chatId, $role, $content]);
$db->prepare('UPDATE widget_chats SET unread = ?, memory_message_count = memory_message_count + 1, updated_at = NOW() WHERE id = ?')->execute([$role === 'visitor' ? 1 : 0, $chatId]);
if ($role === 'visitor') $prospecto->registrarDatosDeclarados($prospectoId, $content);
echo json_encode(['success'=>true]);
