<?php

$isLocal = in_array($_SERVER['SERVER_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']);
if ($isLocal) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

header('Content-Type: application/json');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/WebhookHandler.php';

Database::initTables();
Database::createFirstAdmin();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handleVerification();
} elseif ($method === 'POST') {
    $rawBody = file_get_contents('php://input');
    handleMessage($rawBody);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function handleVerification(): void
{
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($mode !== 'subscribe') {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid mode']);
        return;
    }

    // Check global verify token first
    if ($token === WHATSAPP_VERIFY_TOKEN) {
        echo $challenge;
        return;
    }

    // Check client-specific verify tokens
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM clientes WHERE whatsapp_verify_token = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$token]);
        if ($stmt->fetch()) {
            echo $challenge;
            return;
        }
    } catch (Exception $e) {}

    http_response_code(403);
    echo json_encode(['error' => 'Verification failed']);
}

function handleMessage(string $rawBody): void
{
    $input = json_decode($rawBody, true);

    if (!$input || !isset($input['entry'][0]['changes'][0]['value'])) {
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        return;
    }

    $change = $input['entry'][0]['changes'][0]['value'];
    $incomingPhoneNumberId = $change['metadata']['phone_number_id'] ?? '';
    $isFromSetting = $incomingPhoneNumberId === WHATSAPP_PHONE_NUMBER_ID;

    // Resolve cliente by phone_number_id
    $cliente = null;
    if (!$isFromSetting && $incomingPhoneNumberId !== '') {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM clientes WHERE whatsapp_phone_number_id = ? AND activo = 1 LIMIT 1");
            $stmt->execute([$incomingPhoneNumberId]);
            $cliente = $stmt->fetch();
        } catch (Exception $e) {}
    }

    $whatsappToken = $cliente ? $cliente['whatsapp_token'] : WHATSAPP_TOKEN;
    $whatsappPhoneNumberId = $cliente ? $cliente['whatsapp_phone_number_id'] : WHATSAPP_PHONE_NUMBER_ID;
    $clienteId = $cliente ? (int)$cliente['id'] : null;

    // Verify signature if app secret is configured
    if ($cliente && !empty($cliente['whatsapp_app_secret'])) {
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        if ($signature !== '') {
            $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $cliente['whatsapp_app_secret']);
            if (!hash_equals($expected, $signature)) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid signature']);
                exit;
            }
        }
    } elseif (WHATSAPP_APP_SECRET !== '') {
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        if ($signature !== '' && !verifySignature($rawBody, $signature)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }
    }

    $handler = new WebhookHandler();
    $handler->handleMessage($rawBody, $whatsappToken, $whatsappPhoneNumberId, $clienteId);

    http_response_code(200);
    echo json_encode(['status' => 'ok']);
}

function verifySignature(string $rawBody, string $signatureHeader): bool
{
    $expected = 'sha256=' . hash_hmac('sha256', $rawBody, WHATSAPP_APP_SECRET);
    return hash_equals($expected, $signatureHeader);
}
