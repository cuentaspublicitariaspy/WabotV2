<?php
try {
    require_once __DIR__ . '/../includes/Auth.php';
    requireLogin();

    $user = getUsuarioActual();
    $userId = (int)$user['id'];
    $db = Database::getConnection();

    $action = $_POST['action'] ?? '';

    if ($action === 'guardar') {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $foto = $_FILES['foto_perfil'] ?? null;

        $errors = [];
        if ($nombre === '') $errors[] = 'El nombre es obligatorio';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email válido es obligatorio';

        $fotoPath = null;
        if ($foto && $foto['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $errors[] = 'Formato de imagen no válido (jpg, png, gif, webp)';
            } else {
                $uploadDir = __DIR__ . '/../uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $fotoPath = 'uploads/perfil_' . $userId . '_' . time() . '.' . $ext;
                if (!move_uploaded_file($foto['tmp_name'], $uploadDir . basename($fotoPath))) {
                    $errors[] = 'Error al subir la imagen';
                    $fotoPath = null;
                }
            }
        } elseif (!empty($_POST['foto_actual'])) {
            $fotoPath = $_POST['foto_actual'];
        }

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }

        $sql = "UPDATE usuarios SET nombre = ?, email = ?, whatsapp = ?" . ($fotoPath ? ", foto_perfil = ?" : "") . " WHERE id = ?";
        $params = [$nombre, $email, $whatsapp];
        if ($fotoPath) $params[] = $fotoPath;
        $params[] = $userId;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $_SESSION[ADMIN_USER_NAME_KEY] = $nombre;

        echo json_encode(['success' => true, 'foto' => $fotoPath]);
        exit;
    }

    if ($action === 'cambiar_password') {
        $actual = $_POST['password_actual'] ?? '';
        $nueva = $_POST['password_nueva'] ?? '';
        $confirmar = $_POST['password_confirmar'] ?? '';

        $errors = [];
        if ($actual === '') $errors[] = 'Debes ingresar tu contraseña actual';
        if ($nueva === '') $errors[] = 'Debes ingresar una nueva contraseña';
        elseif (strlen($nueva) < 6) $errors[] = 'La nueva contraseña debe tener al menos 6 caracteres';
        if ($nueva !== $confirmar) $errors[] = 'Las contraseñas nuevas no coinciden';

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }

        $stmt = $db->prepare("SELECT password_hash FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($actual, $row['password_hash'])) {
            echo json_encode(['success' => false, 'errors' => ['La contraseña actual es incorrecta']]);
            exit;
        }

        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $userId]);

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'errors' => ['Acción no válida']]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'errors' => ['Error interno: ' . $e->getMessage()]]);
}
