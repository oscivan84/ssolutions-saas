-- ============================================================
-- MIGRACION: UX avanzada (notificaciones, audit, onboarding)
-- Version: 3.2
-- Ejecutar DESPUES de migration_advanced.sql
-- ============================================================

USE dbsolventas17;

-- ============================================================
-- TABLA: notificaciones
-- Notificaciones en tiempo real para el panel admin
-- ============================================================
CREATE TABLE IF NOT EXISTS notificaciones (
    idnotificacion INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    idusuario INT NULL COMMENT 'NULL = para todos los del negocio',
    tipo ENUM('cliente_respondio', 'cliente_caliente', 'ticket_estancado', 'ticket_nuevo', 'venta_cerrada', 'alerta', 'sistema') NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    mensaje TEXT NOT NULL,
    icono VARCHAR(30) DEFAULT 'bell' COMMENT 'fa icon name',
    color VARCHAR(20) DEFAULT 'info' COMMENT 'bootstrap color class',
    url_accion VARCHAR(255) NULL COMMENT 'Vista a cargar al hacer click',
    referencia_id INT NULL COMMENT 'ID del ticket/diagnostico/persona relacionado',
    leida TINYINT(1) DEFAULT 0,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_negocio (negocio_id),
    INDEX idx_usuario (idusuario),
    INDEX idx_leida (leida),
    INDEX idx_fecha (fecha),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: audit_log
-- Log de auditoría para operaciones sensibles
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_log (
    idaudit INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    idusuario INT NULL,
    accion VARCHAR(50) NOT NULL COMMENT 'config_update, ticket_delete, rol_change, api_key_view, etc',
    entidad VARCHAR(50) NULL COMMENT 'tickets, configuracion_soporte, usuarios_negocio, etc',
    entidad_id INT NULL,
    datos_antes JSON NULL,
    datos_despues JSON NULL,
    ip VARCHAR(45) NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_negocio (negocio_id),
    INDEX idx_usuario (idusuario),
    INDEX idx_accion (accion),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: onboarding_estado
-- Tracking del progreso de onboarding por negocio
-- ============================================================
CREATE TABLE IF NOT EXISTS onboarding_estado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL UNIQUE,
    paso_actual INT DEFAULT 1,
    completado TINYINT(1) DEFAULT 0,
    datos_json JSON NULL COMMENT 'Estado de cada paso',
    fecha_inicio DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_completado DATETIME NULL,
    INDEX idx_negocio (negocio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
