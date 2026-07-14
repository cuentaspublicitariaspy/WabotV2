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
$clientMessageId = trim($input['client_message_id'] ?? '');
if ($key === '' || $sessionId === '' || $content === '' || !in_array($role, ['visitor','assistant'], true)) {
    http_response_code(400); echo json_encode(['success'=>false, 'error'=>'Datos incompletos']); exit;
}

$db = Database::getConnection();
// Compatibilidad con instalaciones creadas antes de la memoria persistente.
try { $db->exec('ALTER TABLE widget_chats ADD COLUMN memory_summary LONGTEXT NULL'); } catch (Throwable $e) {}
try { $db->exec('ALTER TABLE widget_chats ADD COLUMN memory_message_count INT NOT NULL DEFAULT 0'); } catch (Throwable $e) {}
try { $db->exec('ALTER TABLE widget_messages ADD COLUMN client_message_id VARCHAR(80) NULL'); } catch (Throwable $e) {}
try { $db->exec('ALTER TABLE widget_messages ADD UNIQUE KEY uq_widget_message_client (client_message_id)'); } catch (Throwable $e) {}
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
        // "Visitante web" es solo el texto de reserva de la interfaz: nunca
        // debe impedir que un nombre declarado reemplace el dato visible.
        $stmt = $db->prepare("UPDATE widget_chats SET
            visitor_name = CASE WHEN ? <> '' AND (visitor_name IS NULL OR visitor_name = '' OR visitor_name = 'Visitante web') THEN ? ELSE visitor_name END,
            visitor_email = COALESCE(NULLIF(?, ''), visitor_email),
            visitor_phone = COALESCE(NULLIF(?, ''), visitor_phone)
            WHERE id = ?");
        $stmt->execute([$nombre, $nombre, $email, $telefono, $chatId]);
    }
}
$stmt = $db->prepare('INSERT IGNORE INTO widget_messages (chat_id, role, content, client_message_id) VALUES (?, ?, ?, ?)');
$stmt->execute([$chatId, $role, $content, $clientMessageId !== '' ? $clientMessageId : null]);
if ($stmt->rowCount() === 1) {
    $db->prepare('UPDATE widget_chats SET unread = ?, memory_message_count = memory_message_count + 1, updated_at = NOW() WHERE id = ?')->execute([$role === 'visitor' ? 1 : 0, $chatId]);
}
if ($role === 'visitor') {
    // El enriquecimiento puede encontrar un nombre que no coincide con los
    // patrones locales. Aplicamos ese mismo resultado a widget_chats: la
    // lista de Conversaciones se alimenta de esta tabla, no de prospectos.
    // Se analiza el tramo reciente, no una frase aislada. Con ello el
    // extractor entiende respuestas humanas cortas dentro del diálogo.
    $historialStmt = $db->prepare('SELECT role, content FROM widget_messages WHERE chat_id = ? ORDER BY id DESC LIMIT 16');
    $historialStmt->execute([$chatId]);
    $contexto = array_reverse($historialStmt->fetchAll());
    $datosDeclarados = $prospecto->registrarDatosDeclarados($prospectoId, $content, $contexto, $key);
    $nombreDeclarado = trim((string) ($datosDeclarados['nombre'] ?? ''));
    $emailDeclarado = trim((string) ($datosDeclarados['email'] ?? ''));
    $telefonoDeclarado = preg_replace('/\D+/', '', (string) ($datosDeclarados['whatsapp'] ?? ''));
    if ($nombreDeclarado !== '' || $emailDeclarado !== '' || $telefonoDeclarado !== '') {
        $stmt = $db->prepare("UPDATE widget_chats SET
            visitor_name = CASE WHEN ? <> '' AND (visitor_name IS NULL OR visitor_name = '' OR visitor_name = 'Visitante web') THEN ? ELSE visitor_name END,
            visitor_email = COALESCE(NULLIF(?, ''), visitor_email),
            visitor_phone = COALESCE(NULLIF(?, ''), visitor_phone)
            WHERE id = ?");
        $stmt->execute([$nombreDeclarado, $nombreDeclarado, $emailDeclarado, $telefonoDeclarado, $chatId]);
    }
}
echo json_encode(['success'=>true]);
