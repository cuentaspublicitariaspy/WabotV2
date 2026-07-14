CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    whatsapp VARCHAR(20) DEFAULT NULL,
    foto_perfil VARCHAR(255) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('super_admin', 'admin', 'agent') NOT NULL DEFAULT 'agent',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_acceso DATETIME NULL,
    ultimo_logout DATETIME NULL,
    disponible TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS conversaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
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
    FOREIGN KEY (leido_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (asignado_a) REFERENCES usuarios(id) ON DELETE SET NULL
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

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS widget_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key VARCHAR(64) DEFAULT NULL UNIQUE,
    enabled TINYINT(1) DEFAULT 1,
    position VARCHAR(10) DEFAULT 'right',
    primary_color VARCHAR(7) DEFAULT '#2F63E9',
    secondary_color VARCHAR(7) DEFAULT '#F3F4F6',
    welcome_title VARCHAR(255) DEFAULT 'Asistente',
    welcome_subtitle VARCHAR(255) DEFAULT 'Online',
    whatsapp_number VARCHAR(30) DEFAULT '',
    license_key VARCHAR(64) DEFAULT '',
    response_mode ENUM('ai','human') DEFAULT 'ai',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS widget_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    widget_config_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    visitor_name VARCHAR(100) DEFAULT '',
    visitor_email VARCHAR(255) DEFAULT '',
    visitor_phone VARCHAR(30) DEFAULT '',
    memory_summary LONGTEXT NULL,
    memory_message_count INT NOT NULL DEFAULT 0,
    unread TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_widget_config (widget_config_id),
    FOREIGN KEY (widget_config_id) REFERENCES widget_config(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS widget_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    role ENUM('visitor', 'assistant', 'agent', 'system') NOT NULL,
    content TEXT NOT NULL,
    client_message_id VARCHAR(80) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_widget_chat (chat_id),
    UNIQUE KEY uq_widget_message_client (client_message_id),
    FOREIGN KEY (chat_id) REFERENCES widget_chats(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prospectos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL DEFAULT '',
    whatsapp VARCHAR(40) NOT NULL DEFAULT '',
    direccion VARCHAR(255) NOT NULL DEFAULT '',
    ciudad VARCHAR(100) NOT NULL DEFAULT '',
    pais VARCHAR(100) NOT NULL DEFAULT '',
    sitio_web VARCHAR(255) NOT NULL DEFAULT '',
    ocupacion VARCHAR(150) NOT NULL DEFAULT '',
    empresa VARCHAR(150) NOT NULL DEFAULT '',
    estado ENUM('nuevo','contactado','seguimiento','cerrado') NOT NULL DEFAULT 'nuevo',
    notas TEXT NULL,
    resumen TEXT NULL,
    intencion VARCHAR(255) NOT NULL DEFAULT '',
    nivel_interes ENUM('bajo','medio','alto') NOT NULL DEFAULT 'medio',
    temperatura ENUM('frio','tibio','caliente','muy_caliente') NOT NULL DEFAULT 'tibio',
    puntaje TINYINT UNSIGNED NOT NULL DEFAULT 0,
    analizado_en DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email), INDEX idx_whatsapp (whatsapp), INDEX idx_temperatura (temperatura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prospecto_referencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prospecto_id INT NOT NULL,
    canal ENUM('whatsapp','chatbot') NOT NULL,
    referencia VARCHAR(140) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_referencia (canal, referencia),
    INDEX idx_prospecto (prospecto_id),
    FOREIGN KEY (prospecto_id) REFERENCES prospectos(id) ON DELETE CASCADE
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

CREATE TABLE IF NOT EXISTS knowledge_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
