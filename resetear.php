<?php
require_once __DIR__ . '/includes/Auth.php';

Database::initTables();

$user = getUsuarioActual();
if ($user !== null) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$valido = false;
$email = '';

if ($token !== '') {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if ($row && !$row['used'] && strtotime($row['expires_at']) > time()) {
        $valido = true;
        $email = $row['email'];
    } else {
        $error = 'Este enlace es inválido o ya expiró.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valido) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $db = Database::getConnection();
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE usuarios SET password_hash = ? WHERE email = ?");
        $stmt->execute([$hash, $email]);

        $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->execute([$token]);

        $success = 'Contraseña actualizada correctamente.';
        $valido = false;
    }
}
?><!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Restablecer contraseña - Wabot</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <h2><b>Wabot</b></h2>
                <p class="text-secondary">Nueva contraseña</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <p class="text-center mt-3"><a href="login.php" class="btn btn-primary">Iniciar sesión</a></p>
                <?php elseif ($valido): ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label text-secondary">Email</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($email) ?>" disabled>
                    </div>
                    <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="Nueva contraseña" required minlength="6" autofocus></div>
                    <div class="mb-3"><input type="password" name="password_confirm" class="form-control" placeholder="Confirmar contraseña" required minlength="6"></div>
                    <button type="submit" class="btn btn-primary w-100">Restablecer</button>
                </form>
                <?php else: ?>
                <p class="text-center mt-3"><a href="recuperar.php" class="text-secondary">Solicitar nuevo enlace</a></p>
                <?php endif; ?>
                <p class="text-center mt-3 mb-0"><a href="login.php" class="text-secondary">Volver a iniciar sesión</a></p>
            </div>
        </div>
    </div>
</body>
</html>
