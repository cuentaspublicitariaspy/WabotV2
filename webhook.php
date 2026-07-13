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

    if ($token === WHATSAPP_VERIFY_TOKEN) {
        echo $challenge;
        return;
    }

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

    // Verify signature if app secret is configured
    if (WHATSAPP_APP_SECRET !== '') {
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        if ($signature !== '') {
            $expected = 'sha256=' . hash_hmac('sha256', $rawBody, WHATSAPP_APP_SECRET);
            if (!hash_equals($expected, $signature)) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid signature']);
                exit;
            }
        }
    }

    $handler = new WebhookHandler();
    $handler->handleMessage($rawBody, WHATSAPP_TOKEN, WHATSAPP_PHONE_NUMBER_ID);

    http_response_code(200);
    echo json_encode(['status' => 'ok']);
}
