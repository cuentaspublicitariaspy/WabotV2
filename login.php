<?php
require_once __DIR__ . '/includes/Auth.php';

$user = getUsuarioActual();
if ($user !== null) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $result = login($email, $password);
    if ($result['success']) {
        header('Location: index.php');
        exit;
    }
    $error = $result['error'];
}
?><!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión - Wabot</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <h2><b>Wabot</b></h2>
                <p class="text-secondary">WhatsApp Multiagente</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="POST">
                    <div class="mb-3"><input type="email" name="email" class="form-control" placeholder="Email" required autofocus></div>
                    <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="Contraseña" required></div>
                    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                    <p class="text-center mt-3 mb-0"><a href="recuperar.php" class="text-secondary small">¿Olvidaste tu contraseña?</a></p>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
