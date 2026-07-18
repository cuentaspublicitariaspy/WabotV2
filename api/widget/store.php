<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/Database.php';

header('Content-Type: application/json');

function widgetStoreReply(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

// El Chatbot vive en el mismo dominio que WC. WS puede reenviar el mensaje
// transitoriamente usando ese mismo Origin, pero ningún tercero puede escribir.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$requestHost = strtolower((string) preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$source = $origin !== '' ? $origin : ($_SERVER['HTTP_REFERER'] ?? '');
$sourceHost = strtolower((string) parse_url($source, PHP_URL_HOST));
if ($sourceHost === '' || !hash_equals($requestHost, $sourceHost)) {
    widgetStoreReply(403, ['success' => false, 'error' => 'Origen no autorizado']);
}
if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') widgetStoreReply(204, []);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') widgetStoreReply(405, ['success' => false]);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$key = trim((string) ($input['api_key'] ?? ''));
$sessionId = trim((string) ($input['session_id'] ?? ''));
$role = (string) ($input['role'] ?? '');
$content = trim((string) ($input['content'] ?? ''));
$clientMessageId = trim((string) ($input['client_message_id'] ?? ''));
if ($key === '' || $sessionId === '' || $content === '' || !in_array($role, ['visitor', 'assistant'], true)) {
    widgetStoreReply(400, ['success' => false, 'error' => 'Datos incompletos']);
}

$requestId = bin2hex(random_bytes(6));

try {
    $db = Database::getConnection();

    $stmt = $db->prepare('SELECT id FROM widget_config WHERE api_key = ? AND enabled = 1');
    $stmt->execute([$key]);
    $configId = (int) $stmt->fetchColumn();
    if ($configId <= 0) widgetStoreReply(404, ['success' => false, 'error' => 'Configuración no encontrada']);

    $db->prepare('INSERT IGNORE INTO widget_chats (widget_config_id, session_id, unread) VALUES (?, ?, 0)')
        ->execute([$configId, $sessionId]);
    $stmt = $db->prepare('SELECT id FROM widget_chats WHERE widget_config_id = ? AND session_id = ? LIMIT 1');
    $stmt->execute([$configId, $sessionId]);
    $chatId = (int) $stmt->fetchColumn();
    if ($chatId <= 0) throw new RuntimeException('widget_chat_not_created');

    // Primero se conserva el mensaje. Las instalaciones nuevas deduplican por
    // client_message_id; las antiguas siguen funcionando con el esquema previo.
    $inserted = false;
    if ($clientMessageId !== '') {
        try {
            $stmt = $db->prepare('INSERT IGNORE INTO widget_messages (chat_id, role, content, client_message_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$chatId, $role, $content, $clientMessageId]);
            $inserted = $stmt->rowCount() === 1;
        } catch (Throwable $compatibilityError) {
            $stmt = $db->prepare('INSERT INTO widget_messages (chat_id, role, content) VALUES (?, ?, ?)');
            $stmt->execute([$chatId, $role, $content]);
            $inserted = true;
        }
    } else {
        $stmt = $db->prepare('INSERT INTO widget_messages (chat_id, role, content) VALUES (?, ?, ?)');
        $stmt->execute([$chatId, $role, $content]);
        $inserted = true;
    }

    if ($inserted) {
        try {
            $db->prepare('UPDATE widget_chats SET unread = ?, memory_message_count = memory_message_count + 1, updated_at = NOW() WHERE id = ?')
                ->execute([$role === 'visitor' ? 1 : 0, $chatId]);
        } catch (Throwable $compatibilityError) {
            $db->prepare('UPDATE widget_chats SET unread = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$role === 'visitor' ? 1 : 0, $chatId]);
        }
    }
} catch (Throwable $coreError) {
    error_log('[widget-store][' . $requestId . '] core: ' . $coreError->getMessage());
    widgetStoreReply(500, ['success' => false, 'error' => 'No se pudo guardar el mensaje', 'request_id' => $requestId]);
}

// Prospectos es enriquecimiento secundario: nunca puede impedir que el chat y
// su mensaje aparezcan en Conversaciones.
if ($role === 'visitor') {
    try {
        $prospectManagerPath = __DIR__ . '/../../includes/ProspectManager.php';
        if (is_file($prospectManagerPath)) require_once $prospectManagerPath;
        if (class_exists('ProspectManager')) {
            $prospecto = new ProspectManager();
            $prospectoId = $prospecto->vincular('chatbot', (string) $chatId);
            $datosBasicos = $prospecto->detectarDatosBasicos($content);
            if ($datosBasicos) $prospecto->actualizar($prospectoId, $datosBasicos);

            $historialStmt = $db->prepare('SELECT role, content FROM widget_messages WHERE chat_id = ? ORDER BY id DESC LIMIT 16');
            $historialStmt->execute([$chatId]);
            $contexto = array_reverse($historialStmt->fetchAll());
            try {
                $datosDeclarados = $prospecto->registrarDatosDeclarados($prospectoId, $content, $contexto, $key);
            } catch (Throwable $enrichmentError) {
                error_log('[widget-store][' . $requestId . '] enrichment: ' . $enrichmentError->getMessage());
                $datosDeclarados = $datosBasicos;
            }

            $datos = array_merge($datosBasicos, is_array($datosDeclarados) ? $datosDeclarados : []);
            $nombre = trim((string) ($datos['nombre'] ?? ''));
            $email = trim((string) ($datos['email'] ?? ''));
            $telefono = preg_replace('/\D+/', '', (string) ($datos['whatsapp'] ?? ''));
            if ($nombre !== '' || $email !== '' || $telefono !== '') {
                $stmt = $db->prepare("UPDATE widget_chats SET
                    visitor_name = CASE WHEN ? <> '' AND (visitor_name IS NULL OR visitor_name = '' OR visitor_name IN ('Visitante web', 'Sin nombre', 'Unknown')) THEN ? ELSE visitor_name END,
                    visitor_email = COALESCE(NULLIF(?, ''), visitor_email),
                    visitor_phone = COALESCE(NULLIF(?, ''), visitor_phone)
                    WHERE id = ?");
                $stmt->execute([$nombre, $nombre, $email, $telefono, $chatId]);
            }
        }
    } catch (Throwable $prospectError) {
        error_log('[widget-store][' . $requestId . '] prospect: ' . $prospectError->getMessage());
    }
}

widgetStoreReply(200, ['success' => true, 'chat_id' => $chatId, 'stored' => $inserted]);
