<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Mailer.php';

Database::initTables();

$user = getUsuarioActual();
if ($user !== null) {
    header('Location: index.php');
    exit;
}

$msg = '';
$msgType = '';
$envio = false;
$showLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $msg = 'Ingresá tu email';
        $msgType = 'error';
    } else {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND activo = 1");
        $stmt->execute([$email]);
        $userRow = $stmt->fetch();

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $db->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);

        $stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $token, $expires]);

        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $resetLink = $baseUrl . '/resetear.php?token=' . $token;

        $mailSent = false;
        if ($userRow) {
            $subject = 'Recuperación de contraseña - Wabot';
            $body = '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px">';
            $body .= '<h2 style="color:#0d9488">Recuperación de contraseña</h2><p>Hacé clic en el siguiente enlace para restablecer tu contraseña:</p>';
            $body .= '<p><a href="' . htmlspecialchars($resetLink) . '" style="display:inline-block;padding:12px 24px;background:#0d9488;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold">Restablecer contraseña</a></p>';
            $body .= '<p>Este enlace expira en 1 hora.</p><p style="color:#94a3b8;font-size:12px">Si no solicitaste este cambio, ignorá este mensaje.</p></body></html>';
            $mailSent = Mailer::send($email, $subject, $body);
        }

        if ($userRow && $mailSent) {
            $msg = 'Si el email está registrado, recibirás un enlace para restablecer tu contraseña.';
            $msgType = 'success';
        } else {
            $msg = 'El email no pudo ser enviado. Si estás en un entorno local, usá el siguiente enlace:';
            $msgType = 'warning';
            $showLink = $resetLink;
        }
        $envio = true;
    }
}
?><!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contraseña - Wabot</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <h2><b>Wabot</b></h2>
                <p class="text-secondary">Recuperar contraseña</p>
            </div>
            <div class="card-body">
                <?php if ($msg): ?>
                <div class="alert alert-<?= $msgType === 'success' ? 'success' : ($msgType === 'warning' ? 'warning' : 'danger') ?>"><?= htmlspecialchars($msg) ?></div>
                <?php if (!empty($showLink)): ?>
                <div class="alert alert-info small p-2">Link: <a href="<?= htmlspecialchars($showLink) ?>"><?= htmlspecialchars($showLink) ?></a></div>
                <?php endif; ?>
                <?php endif; ?>
                <?php if (!$envio): ?>
                <form method="POST">
                    <div class="mb-3"><input type="email" name="email" class="form-control" placeholder="Tu email" required autofocus></div>
                    <button type="submit" class="btn btn-primary w-100">Enviar enlace</button>
                </form>
                <?php endif; ?>
                <p class="text-center mt-3 mb-0"><a href="login.php" class="text-secondary">Volver a iniciar sesión</a></p>
            </div>
        </div>
    </div>
</body>
</html>
