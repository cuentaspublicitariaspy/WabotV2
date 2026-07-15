<?php
/**
 * Activador temporal de acceso de control de calidad.
 * El código de autorización no está guardado en el repositorio.
 */
declare(strict_types=1);

const ACCESS_CODE_HASH = '378d9ebe013fd07ff5178132d46d9b6c0f80b8e3104031fcc51f2f58e24bff8b';
const QUALITY_EMAIL = 'calidad.wc.20260715.2@wabot.local';

function responsePage(string $title, string $message, int $status = 200): never {
    http_response_code($status);
    $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$title.'</title><body style="font-family:system-ui;max-width:560px;margin:48px auto;padding:0 20px;color:#162033"><h1>'.$title.'</h1><p>'.$message.'</p></body></html>';
    exit;
}

$code = (string)($_GET['code'] ?? $_POST['code'] ?? '');
if (!hash_equals(ACCESS_CODE_HASH, hash('sha256', $code))) responsePage('No disponible', 'El enlace no es válido.', 404);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Crear acceso temporal</title><body style="font-family:system-ui;max-width:560px;margin:48px auto;padding:0 20px;color:#162033"><h1>Crear acceso temporal de WC</h1><p>Definí una contraseña que usarás para ingresar ahora. Este archivo se eliminará al terminar.</p><form method="post"><input type="hidden" name="code" value="'.$safeCode.'"><label style="display:block;margin:14px 0 6px">Contraseña temporal</label><input required minlength="14" type="password" name="password" style="box-sizing:border-box;width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:8px"><label style="display:block;margin:14px 0 6px">Repetir contraseña</label><input required minlength="14" type="password" name="password_confirm" style="box-sizing:border-box;width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:8px"><button style="margin-top:20px;background:#059669;color:#fff;border:0;border-radius:10px;padding:14px 20px;font-weight:700">Crear acceso</button></form></body></html>';
    exit;
}

$password = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['password_confirm'] ?? '');
if (strlen($password) < 14 || !hash_equals($password, $confirm)) responsePage('No se creó el acceso', 'Las contraseñas deben coincidir y tener al menos 14 caracteres.', 422);

try {
    require_once __DIR__ . '/includes/Database.php';
    $db = Database::getConnection();
    $exists = $db->prepare('SELECT id FROM usuarios WHERE email=? LIMIT 1');
    $exists->execute([QUALITY_EMAIL]);
    if (!$exists->fetchColumn()) {
        $add = $db->prepare('INSERT INTO usuarios(nombre,email,password_hash,rol,activo,disponible) VALUES(?,?,?,?,1,1)');
        $add->execute(['Control de Calidad WC 2', QUALITY_EMAIL, password_hash($password, PASSWORD_DEFAULT), 'super_admin']);
    }
    $deleted = @unlink(__FILE__);
    responsePage('Acceso creado', $deleted ? 'Ya podés iniciar sesión con el correo de calidad y la contraseña que definiste. El archivo temporal se eliminó.' : 'La cuenta fue creada. El servidor no pudo eliminar el archivo; avisame para retirarlo del repositorio y borrarlo desde Hostinger.');
} catch (Throwable $e) {
    responsePage('No se creó el acceso', 'Ocurrió un error al crear la cuenta. No se realizaron cambios.', 500);
}
