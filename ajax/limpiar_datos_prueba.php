<?php
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';

requireAdmin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$token = (string)($input['csrf'] ?? '');
$confirmation = strtoupper(trim((string)($input['confirmacion'] ?? '')));
if (!hash_equals((string)($_SESSION['wabot_cleanup_csrf'] ?? ''), $token) || $confirmation !== 'LIMPIAR') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Confirmación inválida']);
    exit;
}

// Lista cerrada y deliberada: solo datos de conversación, Chatbot y prospectos.
// No se ejecutan sentencias de estructura ni se toca ninguna tabla de agenda.
$tables = ['metricas', 'mensajes', 'conversaciones', 'widget_messages', 'widget_chats', 'prospecto_referencias', 'prospectos', 'processed_ids'];
$db = Database::getConnection();
$deleted = [];

try {
    $db->beginTransaction();
    foreach ($tables as $table) {
        $exists = $db->query('SHOW TABLES LIKE ' . $db->quote($table))->fetchColumn();
        if (!$exists) { $deleted[$table] = 0; continue; }
        $deleted[$table] = $db->exec('DELETE FROM `' . $table . '`');
    }
    $db->commit();
    error_log('Wabot: limpieza manual de datos de prueba por usuario ' . (int)(getUsuarioActual()['id'] ?? 0));
    echo json_encode(['success' => true, 'eliminados' => $deleted]);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No se pudo completar la limpieza. No se aplicaron cambios parciales.']);
}
