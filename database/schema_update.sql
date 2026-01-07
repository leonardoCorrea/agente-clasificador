-- ============================================================
-- ACTUALIZACIÓN DE ESQUEMA: MÓDULO DE FACTURACIÓN
-- ============================================================

-- Tabla de configuración de facturación por usuario
CREATE TABLE IF NOT EXISTS facturacion_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    costo_fijo DECIMAL(10, 2) DEFAULT 0.00,
    costo_linea_reconocida DECIMAL(10, 2) DEFAULT 0.00,
    costo_linea_clasificada DECIMAL(10, 2) DEFAULT 0.00,
    moneda VARCHAR(10) DEFAULT 'USD',
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_config (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuración por defecto para el admin (si existe)
INSERT INTO facturacion_config (usuario_id, costo_fijo, costo_linea_reconocida, costo_linea_clasificada)
SELECT id, 100.00, 0.50, 1.00 FROM usuarios WHERE rol = 'admin'
ON DUPLICATE KEY UPDATE fecha_actualizacion = NOW();

-- ============================================================
-- ACTUALIZACIÓN DE ESQUEMA: MÓDULO DUA Y CATÁLOGO (SAC)
-- ============================================================

-- Tabla para el Catálogo Arancelario (SAC Costa Rica)
CREATE TABLE IF NOT EXISTS catalogo_arancelario (
    codigo_sac VARCHAR(20) PRIMARY KEY,
    descripcion TEXT NOT NULL,
    dai DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Derechos Arancelarios a la Importación',
    sc DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Selectivo de Consumo',
    iva DECIMAL(5, 2) DEFAULT 13.00 COMMENT 'Impuesto al Valor Agregado',
    ley_6946 DECIMAL(5, 2) DEFAULT 1.00 COMMENT 'Ley 6946 (1%)',
    otros_impuestos DECIMAL(5, 2) DEFAULT 0.00,
    nota_tecnica TEXT NULL,
    unidad_medida VARCHAR(50) DEFAULT 'Unidades',
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FULLTEXT idx_busqueda (codigo_sac, descripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para Encabezado de DUA
CREATE TABLE IF NOT EXISTS duas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha_generacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    numero_referencia VARCHAR(50) NOT NULL COMMENT 'Referencia interna del sistema',
    regimen_aduanero VARCHAR(50) DEFAULT 'IMPORTACION_DEFINITIVA',
    aduana_control VARCHAR(50) DEFAULT 'SANTAMARIA',
    valor_cif_total DECIMAL(15, 2) DEFAULT 0.00,
    total_impuestos DECIMAL(15, 2) DEFAULT 0.00,
    peso_bruto_total DECIMAL(10, 2) DEFAULT 0.00,
    total_bultos INT DEFAULT 0,
    estado ENUM('borrador', 'generado', 'transmitido') DEFAULT 'borrador',
    datos_extra JSON NULL COMMENT 'Datos adicionales encabezado TICA',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_dua (usuario_id),
    INDEX idx_estado_dua (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para Líneas de DUA (Items)
CREATE TABLE IF NOT EXISTS dua_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dua_id INT NOT NULL,
    item_factura_id INT NULL COMMENT 'Link opcional al item original de factura',
    codigo_sac VARCHAR(20) NOT NULL,
    numero_linea INT NOT NULL,
    descripcion_mercancia TEXT NOT NULL,
    cantidad DECIMAL(12, 2) NOT NULL,
    peso_neto DECIMAL(12, 2) DEFAULT 0.00,
    unidad_medida VARCHAR(20) NULL,
    
    -- Valores Monetarios
    valor_fob DECIMAL(15, 2) NOT NULL,
    flete_prorrateado DECIMAL(15, 2) DEFAULT 0.00,
    seguro_prorrateado DECIMAL(15, 2) DEFAULT 0.00,
    valor_cif DECIMAL(15, 2) NOT NULL,
    
    -- Impuestos Calculados
    dai_monto DECIMAL(15, 2) DEFAULT 0.00,
    sc_monto DECIMAL(15, 2) DEFAULT 0.00,
    ley_monto DECIMAL(15, 2) DEFAULT 0.00,
    iva_monto DECIMAL(15, 2) DEFAULT 0.00,
    total_impuestos_linea DECIMAL(15, 2) DEFAULT 0.00,
    
    -- Metadata para recálculo
    porcentajes_aplicados JSON NULL COMMENT 'Snapshot de % usados: {dai:X, sc:Y, iva:Z}',
    
    FOREIGN KEY (dua_id) REFERENCES duas(id) ON DELETE CASCADE,
    FOREIGN KEY (item_factura_id) REFERENCES items_factura(id) ON DELETE SET NULL,
    -- FOREIGN KEY (codigo_sac) REFERENCES catalogo_arancelario(codigo_sac) ON DELETE RESTRICT,
    INDEX idx_dua (dua_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
