-- ============================================================
-- MIGRACION: Multi-Tenant SaaS para SSolutions
-- Version: 3.0
--
-- EJECUTAR SOBRE dbsolventas17 EXISTENTE
-- Agrega soporte multi-negocio sin romper datos existentes
-- ============================================================

USE dbsolventas17;

-- ============================================================
-- NUEVAS TABLAS: Estructura Multi-Tenant
-- ============================================================

-- TABLA: negocios (tenants)
CREATE TABLE IF NOT EXISTS negocios (
    idnegocio INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE COMMENT 'Identificador URL-friendly unico',
    telefono VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    direccion TEXT NULL,
    logo_url VARCHAR(255) NULL,
    plan ENUM('basico', 'pro', 'premium') DEFAULT 'basico',
    estado ENUM('activo', 'suspendido', 'cancelado') DEFAULT 'activo',
    max_tickets_mes INT DEFAULT 50 COMMENT 'Limite segun plan',
    max_tecnicos INT DEFAULT 3 COMMENT 'Limite segun plan',
    whatsapp_habilitado TINYINT(1) DEFAULT 0,
    ia_habilitada TINYINT(1) DEFAULT 0,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_vencimiento DATE NULL COMMENT 'Fecha limite de suscripcion',
    INDEX idx_slug (slug),
    INDEX idx_estado (estado),
    INDEX idx_plan (plan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLA: usuarios_negocio (relacion usuario <-> negocio + rol)
CREATE TABLE IF NOT EXISTS usuarios_negocio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    idusuario INT NOT NULL COMMENT 'FK a usuario (tabla existente)',
    idnegocio INT NOT NULL COMMENT 'FK a negocios',
    rol ENUM('dueno', 'admin', 'tecnico') NOT NULL DEFAULT 'tecnico',
    activo TINYINT(1) DEFAULT 1,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuario_negocio (idusuario, idnegocio),
    INDEX idx_negocio (idnegocio),
    INDEX idx_usuario (idusuario),
    INDEX idx_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TABLA: planes_saas (definicion de planes)
CREATE TABLE IF NOT EXISTS planes_saas (
    idplan INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    codigo ENUM('basico', 'pro', 'premium') NOT NULL UNIQUE,
    precio_mensual DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_tickets_mes INT NOT NULL DEFAULT 50,
    max_tecnicos INT NOT NULL DEFAULT 3,
    whatsapp_habilitado TINYINT(1) DEFAULT 0,
    ia_habilitada TINYINT(1) DEFAULT 0,
    diagnosticos_habilitados TINYINT(1) DEFAULT 0,
    automatizacion_habilitada TINYINT(1) DEFAULT 0,
    descripcion TEXT NULL,
    activo TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- MIGRAR TABLAS EXISTENTES: Agregar negocio_id
-- ============================================================

-- diagnosticos
ALTER TABLE diagnosticos
    ADD COLUMN negocio_id INT NULL AFTER iddiagnostico,
    ADD INDEX idx_negocio (negocio_id);

-- tickets
ALTER TABLE tickets
    ADD COLUMN negocio_id INT NULL AFTER idticket,
    ADD INDEX idx_negocio (negocio_id);

-- historial_estados_ticket
ALTER TABLE historial_estados_ticket
    ADD COLUMN negocio_id INT NULL AFTER idhistorial,
    ADD INDEX idx_negocio (negocio_id);

-- mantenimientos
ALTER TABLE mantenimientos
    ADD COLUMN negocio_id INT NULL AFTER idmantenimiento,
    ADD INDEX idx_negocio (negocio_id);

-- mensajes_whatsapp
ALTER TABLE mensajes_whatsapp
    ADD COLUMN negocio_id INT NULL AFTER idmensaje,
    ADD INDEX idx_negocio (negocio_id);

-- conversaciones_whatsapp
ALTER TABLE conversaciones_whatsapp
    ADD COLUMN negocio_id INT NULL AFTER idconversacion,
    ADD INDEX idx_negocio (negocio_id);

-- plantillas_mensaje
ALTER TABLE plantillas_mensaje
    ADD COLUMN negocio_id INT NULL AFTER idplantilla,
    ADD INDEX idx_negocio (negocio_id);

-- MIGRAR configuracion_soporte → agregar negocio_id (config por negocio)
ALTER TABLE configuracion_soporte
    ADD COLUMN negocio_id INT NULL AFTER idconfig,
    ADD INDEX idx_negocio (negocio_id),
    DROP INDEX clave,
    ADD UNIQUE KEY uk_clave_negocio (clave, negocio_id);

-- ============================================================
-- DATOS INICIALES: Planes SaaS
-- ============================================================

INSERT IGNORE INTO planes_saas (nombre, codigo, precio_mensual, max_tickets_mes, max_tecnicos, whatsapp_habilitado, ia_habilitada, diagnosticos_habilitados, automatizacion_habilitada, descripcion) VALUES
('Basico', 'basico', 49900, 50, 3, 0, 0, 0, 0,
 'CRM basico con seguimiento manual de tickets. Ideal para empezar.'),
('Pro', 'pro', 149900, 200, 10, 1, 1, 0, 0,
 'Bot WhatsApp con Claude + mensajes automaticos. Para negocios en crecimiento.'),
('Premium', 'premium', 299900, 999999, 50, 1, 1, 1, 1,
 'Automatizacion completa: diagnosticos, IA avanzada, reportes. Para escalar.');

-- ============================================================
-- NEGOCIO POR DEFECTO: Migrar datos existentes
-- ============================================================

-- Crear negocio por defecto para datos existentes
INSERT INTO negocios (nombre, slug, plan, estado, max_tickets_mes, max_tecnicos, whatsapp_habilitado, ia_habilitada)
VALUES ('SSolutions', 'ssolutions', 'premium', 'activo', 999999, 50, 1, 1);

SET @default_negocio = LAST_INSERT_ID();

-- Asignar todos los registros existentes al negocio por defecto
UPDATE diagnosticos SET negocio_id = @default_negocio WHERE negocio_id IS NULL;
UPDATE tickets SET negocio_id = @default_negocio WHERE negocio_id IS NULL;
UPDATE historial_estados_ticket SET negocio_id = @default_negocio WHERE negocio_id IS NULL;
UPDATE mantenimientos SET negocio_id = @default_negocio WHERE negocio_id IS NULL;
UPDATE mensajes_whatsapp SET negocio_id = @default_negocio WHERE negocio_id IS NULL;
UPDATE conversaciones_whatsapp SET negocio_id = @default_negocio WHERE negocio_id IS NULL;
UPDATE plantillas_mensaje SET negocio_id = @default_negocio WHERE negocio_id IS NULL;
UPDATE configuracion_soporte SET negocio_id = @default_negocio WHERE negocio_id IS NULL;

-- Hacer negocio_id NOT NULL despues de migrar datos
ALTER TABLE diagnosticos MODIFY COLUMN negocio_id INT NOT NULL;
ALTER TABLE tickets MODIFY COLUMN negocio_id INT NOT NULL;
ALTER TABLE historial_estados_ticket MODIFY COLUMN negocio_id INT NOT NULL;
ALTER TABLE mantenimientos MODIFY COLUMN negocio_id INT NOT NULL;
ALTER TABLE mensajes_whatsapp MODIFY COLUMN negocio_id INT NOT NULL;
ALTER TABLE conversaciones_whatsapp MODIFY COLUMN negocio_id INT NOT NULL;
ALTER TABLE plantillas_mensaje MODIFY COLUMN negocio_id INT NOT NULL;
ALTER TABLE configuracion_soporte MODIFY COLUMN negocio_id INT NOT NULL;
