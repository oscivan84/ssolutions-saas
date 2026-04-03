-- ============================================================
-- MIGRACION: Funnel de conversión + Modo Asistido
-- Version: 4.2
-- ============================================================

USE dbsolventas17;

-- ============================================================
-- TABLA: funnel_conversiones
-- Tracking completo: mensaje → respuesta → cotización → cierre → $
-- Cada conversación tiene su funnel con timestamps por etapa
-- ============================================================
CREATE TABLE IF NOT EXISTS funnel_conversiones (
    idfunnel INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    idconversacion INT NULL,
    idticket INT NULL,
    idpersona INT NULL,
    telefono VARCHAR(20) NOT NULL,

    -- Etapas del funnel con timestamps
    etapa_actual ENUM('contacto','respuesta','interes','cotizacion','cierre','venta','perdido') DEFAULT 'contacto',
    fecha_contacto DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_respuesta DATETIME NULL,
    fecha_interes DATETIME NULL,
    fecha_cotizacion DATETIME NULL,
    fecha_cierre DATETIME NULL,
    fecha_venta DATETIME NULL,
    fecha_perdido DATETIME NULL,

    -- Datos de la conversión
    monto_cotizado DECIMAL(10,2) DEFAULT 0,
    monto_cerrado DECIMAL(10,2) DEFAULT 0,
    servicio_slug VARCHAR(50) NULL,
    canal VARCHAR(20) DEFAULT 'whatsapp',
    fuente VARCHAR(50) NULL COMMENT 'organico, seguimiento, recuperacion, agente',

    -- Métricas
    mensajes_total INT DEFAULT 0,
    tiempo_primera_respuesta_seg INT NULL,
    tiempo_cierre_seg INT NULL,
    score_cliente VARCHAR(20) NULL,
    intervencion_humana TINYINT(1) DEFAULT 0,

    INDEX idx_negocio (negocio_id),
    INDEX idx_negocio_telefono (negocio_id, telefono),
    INDEX idx_negocio_etapa (negocio_id, etapa_actual),
    INDEX idx_etapa (etapa_actual),
    INDEX idx_fecha (fecha_contacto),
    INDEX idx_telefono (telefono)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: intervenciones_humanas
-- Registro de cuándo y por qué intervino un humano
-- ============================================================
CREATE TABLE IF NOT EXISTS intervenciones_humanas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    idconversacion INT NULL,
    idticket INT NULL,
    telefono VARCHAR(20) NOT NULL,
    razon ENUM('cliente_caliente','objecion_fuerte','silencio_prolongado','queja','escalacion','manual') NOT NULL,
    mensaje_trigger TEXT NULL COMMENT 'Mensaje que disparó la intervención',
    idusuario_asignado INT NULL COMMENT 'Técnico/admin que tomó la conversación',
    estado ENUM('pendiente','tomada','resuelta','ignorada') DEFAULT 'pendiente',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_tomada DATETIME NULL,
    fecha_resuelta DATETIME NULL,
    INDEX idx_negocio (negocio_id),
    INDEX idx_estado (estado),
    INDEX idx_fecha (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
