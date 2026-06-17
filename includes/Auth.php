<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AgentRouter.php';

session_start();

function getUsuarioActual(): ?array
{
    if (isset($_SESSION[ADMIN_SESSION_KEY]) && $_SESSION[ADMIN_SESSION_KEY] === true) {
        return [
            'id' => $_SESSION[ADMIN_USER_ID_KEY] ?? 0,
            'nombre' => $_SESSION[ADMIN_USER_NAME_KEY] ?? '',
            'rol' => $_SESSION[ADMIN_USER_ROL_KEY] ?? '',
            'cliente_id' => $_SESSION[ADMIN_CLIENTE_ID_KEY] ?? null,
        ];
    }
    return null;
}

function requireLogin(): void
{
    if (getUsuarioActual() === null) {
        header('Location: index.php');
        exit;
    }
}

function requireAdmin(): void
{
    $user = getUsuarioActual();
    if ($user === null || !in_array($user['rol'], ['super_admin', 'admin'])) {
        http_response_code(403);
        echo 'Acceso denegado';
        exit;
    }
}

function requireSuperAdmin(): void
{
    $user = getUsuarioActual();
    if ($user === null || $user['rol'] !== 'super_admin') {
        http_response_code(403);
        echo 'Acceso denegado';
        exit;
    }
}

function login(string $email, string $password): array
{
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT id, nombre, email, password_hash, rol, activo, cliente_id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Credenciales incorrectas'];
    }

    if (!$user['activo']) {
        return ['success' => false, 'error' => 'Usuario desactivado'];
    }

    $_SESSION[ADMIN_SESSION_KEY] = true;
    $_SESSION[ADMIN_USER_ID_KEY] = (int)$user['id'];
    $_SESSION[ADMIN_USER_NAME_KEY] = $user['nombre'];
    $_SESSION[ADMIN_USER_ROL_KEY] = $user['rol'];
    $_SESSION[ADMIN_CLIENTE_ID_KEY] = $user['cliente_id'] !== null ? (int)$user['cliente_id'] : null;

    $stmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    $router = new AgentRouter();
    $router->marcarActivo((int)$user['id']);

    return ['success' => true];
}

function logout(): void
{
    $userId = $_SESSION[ADMIN_USER_ID_KEY] ?? null;
    session_destroy();

    if ($userId) {
        $router = new AgentRouter();
        $router->marcarInactivo((int)$userId);
    }

    header('Location: index.php');
    exit;
}
