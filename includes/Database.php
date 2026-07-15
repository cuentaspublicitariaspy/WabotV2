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
                self::runOneTimeTestReset(self::$instance);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Database connection failed']);
                exit;
            }
        }
        return self::$instance;
    }


    /**
     * Limpieza única, deliberadamente limitada al entorno de pruebas actual.
     * Conserva agenda, sucursales, servicios, horarios, bloqueos y citas.
     * La marca en BD evita que vuelva a ejecutarse después del primer acceso.
     */
    private static function runOneTimeTestReset(PDO $db): void
    {
        $host = strtolower(preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
        if (!in_array($host, ['vendiendoeninternet.com', 'www.vendiendoeninternet.com'], true)) return;

        $db->exec('CREATE TABLE IF NOT EXISTS wabot_mantenimiento (clave VARCHAR(120) PRIMARY KEY, ejecutado_en DATETIME NOT NULL)');
        $key = 'limpieza_interacciones_prueba_20260715';
        $check = $db->prepare('SELECT 1 FROM wabot_mantenimiento WHERE clave = ?');
        $check->execute([$key]);
        if ($check->fetchColumn()) return;

        // Se borran únicamente datos de interacción y perfiles comerciales.
        // Las tablas se comprueban para mantener compatibilidad con instalaciones
        // previas que aún no tengan el Chatbot o Prospectos habilitados.
        $tables = ['metricas', 'mensajes', 'conversaciones', 'widget_messages', 'widget_chats', 'prospecto_referencias', 'prospectos', 'processed_ids'];
        $db->beginTransaction();
        try {
            foreach ($tables as $table) {
                $exists = $db->query("SHOW TABLES LIKE " . $db->quote($table))->fetchColumn();
                if ($exists) $db->exec("DELETE FROM \`{$table}\`");
            }
            $db->prepare('INSERT INTO wabot_mantenimiento (clave, ejecutado_en) VALUES (?, NOW())')->execute([$key]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function initTables(): void
    {
        $sql = file_get_contents(__DIR__ . '/../init.sql');
        if ($sql) {
            self::getConnection()->exec($sql);
        }
    }
}
