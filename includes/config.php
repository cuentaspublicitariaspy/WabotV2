<?php

$isLocal = in_array($_SERVER['SERVER_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']);
if ($isLocal) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, '"\'');
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

// OpenAI constants removed — all calls go through Vercel proxy

define('WHATSAPP_TOKEN', getenv('WHATSAPP_TOKEN') ?: '');
define('WHATSAPP_VERIFY_TOKEN', getenv('WHATSAPP_VERIFY_TOKEN') ?: '');
define('WHATSAPP_PHONE_NUMBER_ID', getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '');
define('WHATSAPP_APP_SECRET', getenv('WHATSAPP_APP_SECRET') ?: '');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('LICENSE_KEY', getenv('LICENSE_KEY') ?: '');
define('CHATBOT_API_KEY', getenv('CHATBOT_API_KEY') ?: '');
define('ADMIN_SESSION_KEY', 'wabot_admin_logged_in');
define('ADMIN_USER_ID_KEY', 'wabot_user_id');
define('ADMIN_USER_NAME_KEY', 'wabot_user_name');
define('ADMIN_USER_ROL_KEY', 'wabot_user_rol');
define('ADMIN_CLIENTE_ID_KEY', 'wabot_cliente_id');
