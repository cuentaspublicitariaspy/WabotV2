<?php

require_once __DIR__ . '/config.php';

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
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

    public static function createFirstAdmin(): void
    {
        $db = self::getConnection();
        $stmt = $db->query("SELECT COUNT(*) FROM usuarios");
        $count = (int)$stmt->fetchColumn();

        if ($count === 0) {
            $hash = password_hash('admin', PASSWORD_BCRYPT);
            $nombre = 'Admin';
            $email = ADMIN_EMAIL;
            $stmt = $db->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, 'admin')");
            $stmt->execute([$nombre, $email, $hash]);
            error_log("First admin user created: $email / admin");
        }
    }
}
