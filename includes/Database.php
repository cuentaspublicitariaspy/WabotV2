<?php

require_once __DIR__ . '/config.php';

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        // Redirect to setup if DB not configured
        if (DB_NAME === '') {
            $script = $_SERVER['SCRIPT_NAME'] ?? '';
            if (!str_contains($script, 'setup.php') && !str_contains($script, 'setup_admin.php') && !str_contains($script, 'webhook.php')) {
                header('Location: setup.php');
                exit;
            }
            throw new RuntimeException('DB not configured');
        }

        if (self::$instance === null) {
            try {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database connection failed']);
                exit;
            }
        }
        return self::$instance;
    }

    public static function initTables(): void
    {
        $sql = file_get_contents(__DIR__ . '/../init.sql');
        if ($sql) {
            self::getConnection()->exec($sql);
        }
    }
}
