<?php
require_once __DIR__ . '/includes/Database.php';

// If users exist, redirect to login
try {
    $db = Database::getConnection();
    $count = $db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    if ((int)$count > 0) {
        header('Location: login.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: setup.php');
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if ($nombre === '' || $email === '' || $password === '') {
        $error = 'Completá todos los campos';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, 'super_admin')");
            $stmt->execute([$nombre, $email, $hash]);
            @unlink(__DIR__ . '/setup.php');
            @unlink(__DIR__ . '/init.sql');
            @unlink(__FILE__);
            header('Location: login.php?installed=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Error: ' . ($e->getCode() === '23000' ? 'El email ya existe' : $e->getMessage());
        }
    }
}
?><!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear administrador - Wabot</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <h2><b>Wabot</b></h2>
                <p class="text-secondary">Crear administrador</p>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                <div class="alert alert-success">Administrador creado correctamente.</div>
                <a href="login.php" class="btn btn-primary w-100">Ir a iniciar sesión</a>
                <?php else: ?>
                <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <p class="text-sm text-secondary mb-3">Creá el primer usuario administrador del sistema.</p>
                <form method="POST">
                    <div class="mb-3">
                        <input type="text" name="nombre" class="form-control" placeholder="Nombre completo" required autofocus>
                    </div>
                    <div class="mb-3">
                        <input type="email" name="email" class="form-control" placeholder="Email" required>
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Contraseña (mín. 8 caracteres)" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password_confirm" class="form-control" placeholder="Confirmar contraseña" required minlength="8">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Crear administrador</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
