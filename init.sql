CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL DEFAULT '',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    meta_app_id VARCHAR(100) DEFAULT NULL,
    whatsapp_token TEXT DEFAULT NULL,
    whatsapp_phone_number_id VARCHAR(50) DEFAULT NULL,
    whatsapp_business_account_id VARCHAR(50) DEFAULT NULL,
    whatsapp_verify_token VARCHAR(100) DEFAULT NULL,
    whatsapp_app_secret VARCHAR(100) DEFAULT NULL,
    openai_api_key VARCHAR(255) DEFAULT NULL,
    openai_model VARCHAR(50) DEFAULT 'gpt-4o-mini',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_phone_number_id (whatsapp_phone_number_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT DEFAULT NULL,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    whatsapp VARCHAR(20) DEFAULT NULL,
    foto_perfil VARCHAR(255) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'agent') NOT NULL DEFAULT 'agent',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_acceso DATETIME NULL,
    ultimo_logout DATETIME NULL,
    disponible TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS conversaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT DEFAULT NULL,
    wa_phone VARCHAR(20) NOT NULL,
    wa_name VARCHAR(100) NOT NULL DEFAULT '',
    ultimo_mensaje TEXT NULL,
    ultimo_tiempo DATETIME NULL,
    estado ENUM('pendiente', 'respondido') NOT NULL DEFAULT 'pendiente',
    leido_por INT NULL,
    leido_en DATETIME NULL,
    asignado_a INT NULL,
    departamento VARCHAR(50) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wa_phone (wa_phone),
    INDEX idx_estado (estado),
    INDEX idx_ultimo_tiempo (ultimo_tiempo),
    INDEX idx_asignado (asignado_a),
    INDEX idx_cliente (cliente_id),
    FOREIGN KEY (leido_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (asignado_a) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversacion_id INT NOT NULL,
    wa_message_id VARCHAR(100) NULL,
    contenido TEXT NOT NULL,
    direccion ENUM('in', 'out') NOT NULL,
    respondido_por INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conversacion (conversacion_id),
    INDEX idx_wa_message (wa_message_id),
    FOREIGN KEY (conversacion_id) REFERENCES conversaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (respondido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS processed_ids (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wa_message_id VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wa_message (wa_message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agentes_sesion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    activo TINYINT(1) NOT NULL DEFAULT 0,
    ultimo_ping DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS metricas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversacion_id INT NOT NULL,
    mensaje_in_id INT NULL,
    mensaje_out_id INT NULL,
    tiempo_respuesta_seg INT NULL,
    respondido_por INT NULL,
    respondido_por_ia TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conversacion (conversacion_id),
    INDEX idx_respondido_por (respondido_por),
    INDEX idx_created (created_at),
    FOREIGN KEY (conversacion_id) REFERENCES conversaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (respondido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plantillas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    idioma VARCHAR(10) NOT NULL DEFAULT 'es',
    contenido TEXT NOT NULL,
    parametros INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO usuarios (nombre, email, password_hash, rol)
VALUES ('Admin', 'rolo27281@gmail.com', '$2y$10$5nxdX.2GAa1NSeTtDtWph./fGvEIRDmSRBIJJRpY4PezEhPP.ICWy', 'admin');
