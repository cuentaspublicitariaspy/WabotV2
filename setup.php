<?php
// If .env already has DB_NAME, check if users exist
$envFile = __DIR__ . '/.env';
$dbConfigured = false;
$previousConfigurationError = '';
$resetPreviousEnvironment = false;
if (file_exists($envFile)) {
    $envVars = [];
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $envVars[trim($k)] = trim(trim($v), '"\'');
        }
    }
    if (!empty($envVars['DB_NAME'])) {
        $dbConfigured = true;
        try {
            $pdo = new PDO("mysql:host={$envVars['DB_HOST']};dbname={$envVars['DB_NAME']};charset=utf8mb4", $envVars['DB_USER'], $envVars['DB_PASS'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
            $count = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
            header('Location: ' . ((int)$count > 0 ? 'login.php' : 'setup_admin.php'));
            exit;
        } catch (PDOException $e) {
            // Si la base fue eliminada (caso normal al reiniciar una prueba), no generar un bucle.
            // El instalador debe permitir configurar una base nueva sin intervención manual en .env.
            $previousConfigurationError = 'Se detectó una configuración anterior cuya base de datos ya no está disponible. Completá los datos de la nueva base para reiniciar esta instalación.';
            $mysqlErrorCode = (int) ($e->errorInfo[1] ?? 0);
            $resetPreviousEnvironment = $mysqlErrorCode === 1049;
        } catch (Exception $e) {
            $previousConfigurationError = 'Se detectó una configuración anterior que no se pudo validar. Completá los datos de la base para continuar.';
        }
    }
}

$error = $previousConfigurationError;
$success = false;
$testHost = '';
$testName = '';
$testUser = '';
$testPass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';

    if ($name === '' || $user === '') {
        $error = 'Completá Nombre de BD y Usuario';
    } else {
        // Test connection
        try {
            $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3,
            ]);

            // Run init.sql
            $sql = file_get_contents(__DIR__ . '/init.sql');
            if ($sql) {
                $pdo->exec($sql);
            }

            // En una reinstalación cuyo esquema anterior fue eliminado, comenzar sin credenciales.
            // En una actualización normal, se conservan los valores no relacionados con la base.
            $oldEnv = [];
            if (!$resetPreviousEnvironment && file_exists($envFile)) {
                foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    if (str_contains($line, '=')) {
                        [$k, $v] = explode('=', $line, 2);
                        $oldEnv[trim($k)] = trim(trim($v), '"\'');
                    }
                }
            }
            $envContent = "DB_HOST=$host\nDB_NAME=$name\nDB_USER=$user\nDB_PASS=$pass\n\n"
                . "WHATSAPP_TOKEN=" . ($oldEnv['WHATSAPP_TOKEN'] ?? '') . "\n"
                . "WHATSAPP_VERIFY_TOKEN=" . ($oldEnv['WHATSAPP_VERIFY_TOKEN'] ?? '') . "\n"
                . "WHATSAPP_PHONE_NUMBER_ID=" . ($oldEnv['WHATSAPP_PHONE_NUMBER_ID'] ?? '') . "\n"
                . "WHATSAPP_APP_SECRET=" . ($oldEnv['WHATSAPP_APP_SECRET'] ?? '') . "\n\n"
                . "LICENSE_KEY=" . ($oldEnv['LICENSE_KEY'] ?? '') . "\n"
                . "CHATBOT_API_KEY=" . ($oldEnv['CHATBOT_API_KEY'] ?? '') . "\n\n"
                . "SMTP_HOST=" . ($oldEnv['SMTP_HOST'] ?? '') . "\n"
                . "SMTP_PORT=" . ($oldEnv['SMTP_PORT'] ?? '587') . "\n"
                . "SMTP_USER=" . ($oldEnv['SMTP_USER'] ?? '') . "\n"
                . "SMTP_PASS=" . ($oldEnv['SMTP_PASS'] ?? '') . "\n"
                . "SMTP_FROM_EMAIL=" . ($oldEnv['SMTP_FROM_EMAIL'] ?? 'no-reply@wabot.app') . "\n"
                . "SMTP_FROM_NAME=" . ($oldEnv['SMTP_FROM_NAME'] ?? 'Wabot') . "\n";
            file_put_contents($envFile, $envContent);

            header('Location: setup_admin.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Error de conexión: ' . $e->getMessage();
        }
    }
    $testHost = $host;
    $testName = $name;
    $testUser = $user;
    $testPass = $pass;
}
?><!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalación - Wabot</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <h2><b>Wabot</b></h2>
                <p class="text-secondary">Instalación</p>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                <div class="alert alert-success">Base de datos configurada correctamente.</div>
                <p class="text-center text-sm text-secondary mb-3">Ya podés iniciar sesión.</p>
                <a href="login.php" class="btn btn-primary w-100">Ir a iniciar sesión</a>
                <?php else: ?>
                <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label text-secondary text-sm">Host</label>
                        <input type="text" name="db_host" value="<?= htmlspecialchars($testHost ?: 'localhost') ?>" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary text-sm">Nombre de la BD</label>
                        <input type="text" name="db_name" value="<?= htmlspecialchars($testName) ?>" class="form-control" placeholder="wabot_v2" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary text-sm">Usuario</label>
                        <input type="text" name="db_user" value="<?= htmlspecialchars($testUser) ?>" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary text-sm">Contraseña</label>
                        <input type="password" name="db_pass" value="<?= htmlspecialchars($testPass) ?>" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Configurar base de datos</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
