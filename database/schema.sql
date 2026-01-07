-- ============================================================
-- PLATAFORMA INTELIGENTE DE CLASIFICACIÓN DE FACTURAS ADUANERAS
-- Esquema de Base de Datos MySQL
-- ============================================================

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS facturacion_aduanera CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE facturacion_aduanera;

-- ============================================================
-- TABLA: usuarios
-- Gestión de usuarios del sistema con roles
-- ============================================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'operador', 'visualizador') DEFAULT 'operador',
    activo TINYINT(1) DEFAULT 1,
    email_verificado TINYINT(1) DEFAULT 0,
    token_verificacion VARCHAR(100) NULL,
    token_recuperacion VARCHAR(100) NULL,
    token_expiracion DATETIME NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_rol (rol),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: facturas
-- Registro maestro de facturas con seguimiento de estado
-- ============================================================
CREATE TABLE facturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_factura VARCHAR(50) NULL,
    proveedor VARCHAR(200) NULL,
    fecha_factura DATE NULL,
    fecha_carga TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    archivo_original VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(500) NOT NULL,
    tipo_archivo VARCHAR(10) NOT NULL,
    tamano_archivo INT NOT NULL,
    estado ENUM('pendiente', 'procesando', 'ocr_completado', 'digitado', 'clasificado', 'aprobado', 'rechazado') DEFAULT 'pendiente',
    usuario_id INT NOT NULL,
    total_factura DECIMAL(15, 2) NULL,
    moneda VARCHAR(10) DEFAULT 'USD',
    observaciones TEXT NULL,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_estado (estado),
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha_factura (fecha_factura),
    INDEX idx_numero_factura (numero_factura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: items_factura
-- Ítems individuales de línea de cada factura
-- ============================================================
CREATE TABLE items_factura (
    id INT AUTO_INCREMENT PRIMARY KEY,
    factura_id INT NOT NULL,
    numero_linea INT NOT NULL,
    descripcion TEXT NOT NULL,
    cantidad DECIMAL(10, 2) NOT NULL,
    unidad_medida VARCHAR(20) NULL,
    precio_unitario DECIMAL(15, 2) NOT NULL,
    subtotal DECIMAL(15, 2) NOT NULL,
    codigo_producto VARCHAR(100) NULL,
    observaciones TEXT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE,
    INDEX idx_factura (factura_id),
    INDEX idx_numero_linea (numero_linea)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: resultados_ocr
-- Resultados de extracción OCR y texto sin procesar
-- ============================================================
CREATE TABLE resultados_ocr (
    id INT AUTO_INCREMENT PRIMARY KEY,
    factura_id INT NOT NULL,
    texto_extraido LONGTEXT NOT NULL,
    confianza_promedio DECIMAL(5, 2) NULL,
    metodo_ocr VARCHAR(50) NOT NULL DEFAULT 'tesseract',
    tiempo_procesamiento INT NULL COMMENT 'Tiempo en milisegundos',
    datos_estructurados JSON NULL COMMENT 'Datos extraídos en formato JSON',
    errores TEXT NULL,
    fecha_procesamiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE,
    INDEX idx_factura (factura_id),
    INDEX idx_fecha (fecha_procesamiento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: resultados_ia
-- Sugerencias de clasificación IA con puntajes de confianza
-- ============================================================
CREATE TABLE resultados_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_factura_id INT NOT NULL,
    codigo_arancelario_sugerido VARCHAR(20) NOT NULL,
    descripcion_codigo TEXT NOT NULL,
    explicacion TEXT NOT NULL,
    confianza DECIMAL(5, 2) NOT NULL COMMENT 'Porcentaje de confianza 0-100',
    modelo_ia VARCHAR(50) NOT NULL DEFAULT 'gpt-3.5-turbo',
    prompt_usado TEXT NULL,
    respuesta_completa JSON NULL,
    alternativas JSON NULL COMMENT 'Códigos alternativos sugeridos',
    tiempo_procesamiento INT NULL COMMENT 'Tiempo en milisegundos',
    fecha_clasificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_factura_id) REFERENCES items_factura(id) ON DELETE CASCADE,
    INDEX idx_item (item_factura_id),
    INDEX idx_confianza (confianza),
    INDEX idx_fecha (fecha_clasificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: clasificacion_final
-- Clasificaciones finales aprobadas por usuarios
-- ============================================================
CREATE TABLE clasificacion_final (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_factura_id INT NOT NULL,
    resultado_ia_id INT NULL COMMENT 'NULL si fue clasificación manual',
    codigo_arancelario VARCHAR(20) NOT NULL,
    descripcion_codigo TEXT NOT NULL,
    fue_modificado TINYINT(1) DEFAULT 0 COMMENT '1 si el usuario modificó la sugerencia IA',
    codigo_original_ia VARCHAR(20) NULL,
    justificacion_modificacion TEXT NULL,
    usuario_aprobador_id INT NOT NULL,
    estado ENUM('pendiente', 'aprobado', 'rechazado', 'revision') DEFAULT 'pendiente',
    fecha_aprobacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_factura_id) REFERENCES items_factura(id) ON DELETE CASCADE,
    FOREIGN KEY (resultado_ia_id) REFERENCES resultados_ia(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_aprobador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_item_clasificacion (item_factura_id),
    INDEX idx_estado (estado),
    INDEX idx_usuario (usuario_aprobador_id),
    INDEX idx_fecha (fecha_aprobacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: auditoria
-- Registro completo de auditoría (quién, qué, cuándo)
-- ============================================================
CREATE TABLE auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    accion VARCHAR(100) NOT NULL,
    tabla_afectada VARCHAR(50) NOT NULL,
    registro_id INT NULL,
    datos_anteriores JSON NULL,
    datos_nuevos JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    fecha_accion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_accion (accion),
    INDEX idx_tabla (tabla_afectada),
    INDEX idx_fecha (fecha_accion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: configuracion
-- Configuración del sistema y claves API
-- ============================================================
CREATE TABLE configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT NULL,
    tipo ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    descripcion TEXT NULL,
    es_sensible TINYINT(1) DEFAULT 0 COMMENT '1 para datos sensibles como API keys',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DATOS INICIALES
-- ============================================================

-- Usuario administrador por defecto (password: Admin123!)
INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, activo, email_verificado) VALUES
('Administrador', 'Sistema', 'admin@facturacion.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 1);

-- Configuraciones iniciales del sistema
INSERT INTO configuracion (clave, valor, tipo, descripcion, es_sensible) VALUES
('ocr_service_url', 'http://localhost:5000', 'string', 'URL del microservicio OCR Python', 0),
('ai_service_url', 'http://localhost:5001', 'string', 'URL del microservicio de clasificación IA', 0),
('openai_api_key', '', 'string', 'Clave API de OpenAI para clasificación', 1),
('openai_model', 'gpt-3.5-turbo', 'string', 'Modelo de OpenAI a utilizar', 0),
('max_file_size', '10485760', 'number', 'Tamaño máximo de archivo en bytes (10MB)', 0),
('allowed_extensions', '["pdf","jpg","jpeg","png"]', 'json', 'Extensiones de archivo permitidas', 0),
('smtp_host', '', 'string', 'Servidor SMTP para envío de emails', 0),
('smtp_port', '587', 'number', 'Puerto SMTP', 0),
('smtp_user', '', 'string', 'Usuario SMTP', 1),
('smtp_password', '', 'string', 'Contraseña SMTP', 1),
('sistema_nombre', 'Plataforma de Clasificación de Facturas Aduaneras', 'string', 'Nombre del sistema', 0);

-- ============================================================
-- VISTAS ÚTILES
-- ============================================================

-- Vista de facturas con información completa
CREATE VIEW vista_facturas_completas AS
SELECT 
    f.id,
    f.numero_factura,
    f.proveedor,
    f.fecha_factura,
    f.fecha_carga,
    f.estado,
    f.total_factura,
    f.moneda,
    u.nombre AS usuario_nombre,
    u.apellido AS usuario_apellido,
    u.email AS usuario_email,
    COUNT(DISTINCT i.id) AS total_items,
    COUNT(DISTINCT cf.id) AS items_clasificados,
    CASE 
        WHEN COUNT(DISTINCT i.id) = COUNT(DISTINCT cf.id) THEN 'Completo'
        WHEN COUNT(DISTINCT cf.id) > 0 THEN 'Parcial'
        ELSE 'Pendiente'
    END AS estado_clasificacion
FROM facturas f
LEFT JOIN usuarios u ON f.usuario_id = u.id
LEFT JOIN items_factura i ON f.id = i.factura_id
LEFT JOIN clasificacion_final cf ON i.id = cf.item_factura_id
GROUP BY f.id;

-- Vista de estadísticas de clasificación IA
CREATE VIEW vista_estadisticas_ia AS
SELECT 
    DATE(fecha_clasificacion) AS fecha,
    COUNT(*) AS total_clasificaciones,
    AVG(confianza) AS confianza_promedio,
    MIN(confianza) AS confianza_minima,
    MAX(confianza) AS confianza_maxima,
    modelo_ia,
    AVG(tiempo_procesamiento) AS tiempo_promedio_ms
FROM resultados_ia
GROUP BY DATE(fecha_clasificacion), modelo_ia;

-- ============================================================
-- PROCEDIMIENTOS ALMACENADOS
-- ============================================================

DELIMITER //

-- Procedimiento para obtener estadísticas del dashboard
CREATE PROCEDURE sp_estadisticas_dashboard(IN p_usuario_id INT)
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM facturas WHERE usuario_id = p_usuario_id) AS total_facturas,
        (SELECT COUNT(*) FROM facturas WHERE usuario_id = p_usuario_id AND estado = 'aprobado') AS facturas_aprobadas,
        (SELECT COUNT(*) FROM facturas WHERE usuario_id = p_usuario_id AND estado = 'pendiente') AS facturas_pendientes,
        (SELECT COUNT(*) FROM items_factura i 
         INNER JOIN facturas f ON i.factura_id = f.id 
         WHERE f.usuario_id = p_usuario_id) AS total_items,
        (SELECT COUNT(*) FROM clasificacion_final cf
         INNER JOIN items_factura i ON cf.item_factura_id = i.id
         INNER JOIN facturas f ON i.factura_id = f.id
         WHERE f.usuario_id = p_usuario_id) AS items_clasificados;
END //

DELIMITER ;

-- ============================================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ============================================================

-- Índices compuestos para consultas frecuentes
CREATE INDEX idx_facturas_usuario_estado ON facturas(usuario_id, estado);
CREATE INDEX idx_items_factura_completo ON items_factura(factura_id, numero_linea);
CREATE INDEX idx_clasificacion_item_estado ON clasificacion_final(item_factura_id, estado);

-- ============================================================
-- FIN DEL ESQUEMA
-- ============================================================
