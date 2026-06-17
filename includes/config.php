<?php

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

define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
define('OPENAI_MODEL', getenv('OPENAI_MODEL') ?: 'gpt-4o-mini');
define('OPENAI_MAX_TOKENS', (int)(getenv('OPENAI_MAX_TOKENS') ?: 1024));
define('OPENAI_TEMPERATURE', (float)(getenv('OPENAI_TEMPERATURE') ?: 0.7));

define('WHATSAPP_TOKEN', getenv('WHATSAPP_TOKEN') ?: '');
define('WHATSAPP_VERIFY_TOKEN', getenv('WHATSAPP_VERIFY_TOKEN') ?: '');
define('WHATSAPP_PHONE_NUMBER_ID', getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '');
define('WHATSAPP_APP_SECRET', getenv('WHATSAPP_APP_SECRET') ?: '');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('META_APP_ID', getenv('META_APP_ID') ?: '');
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@ejemplo.com');
define('ADMIN_SESSION_KEY', 'wabot_admin_logged_in');
define('ADMIN_USER_ID_KEY', 'wabot_user_id');
define('ADMIN_USER_NAME_KEY', 'wabot_user_name');
define('ADMIN_USER_ROL_KEY', 'wabot_user_rol');

define('KNOWLEDGE_FILE', __DIR__ . '/../conocimiento/conocimiento.txt');
