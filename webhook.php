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

$handler = new WebhookHandler();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $handler->handleVerification();
} elseif ($method === 'POST') {
    $rawBody = file_get_contents('php://input');

    if (WHATSAPP_APP_SECRET !== '') {
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        if ($signature === '' || !verifySignature($rawBody, $signature)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }
    }

    $handler->handleMessage($rawBody);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function verifySignature(string $rawBody, string $signatureHeader): bool
{
    $expected = 'sha256=' . hash_hmac('sha256', $rawBody, WHATSAPP_APP_SECRET);
    return hash_equals($expected, $signatureHeader);
}
