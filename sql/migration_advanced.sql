-- ============================================================
-- MIGRACION: Sistema avanzado (prompts, eventos, jobs, automatizaciones)
-- Version: 3.1
-- Ejecutar DESPUES de migration_multitenant.sql
-- ============================================================

USE dbsolventas17;

-- ============================================================
-- TABLA: prompts_ia
-- Prompts versionados y administrables por negocio
-- ============================================================
CREATE TABLE IF NOT EXISTS prompts_ia (
    idprompt INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    tipo ENUM('ventas', 'soporte', 'diagnostico', 'recomendaciones', 'clasificacion', 'scoring', 'sugerencia', 'custom') NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    system_prompt TEXT NOT NULL,
    user_template TEXT NULL COMMENT 'Template con variables: {nombre}, {ticket}, {mensaje}',
    variables_json TEXT NULL COMMENT 'JSON: variables disponibles y defaults',
    version INT NOT NULL DEFAULT 1,
    activo TINYINT(1) DEFAULT 1,
    es_default TINYINT(1) DEFAULT 0 COMMENT 'Prompt por defecto para este tipo',
    notas TEXT NULL COMMENT 'Notas de cambios para A/B testing',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_negocio_tipo_version (negocio_id, tipo, nombre, version),
    INDEX idx_negocio (negocio_id),
    INDEX idx_tipo (tipo),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: eventos
-- Sistema de eventos internos (desacoplamiento)
-- ============================================================
CREATE TABLE IF NOT EXISTS eventos (
    idevento INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL COMMENT 'diagnostico_creado, ticket_creado, mensaje_recibido, estado_cambiado, etc',
    payload JSON NOT NULL COMMENT 'Datos del evento',
    procesado TINYINT(1) DEFAULT 0,
    intentos INT DEFAULT 0,
    error TEXT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_procesado DATETIME NULL,
    INDEX idx_negocio (negocio_id),
    INDEX idx_tipo (tipo),
    INDEX idx_procesado (procesado),
    INDEX idx_fecha (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: jobs (cola de trabajos asincrónicos)
-- ============================================================
CREATE TABLE IF NOT EXISTS jobs (
    idjob INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL COMMENT 'enviar_whatsapp, generar_resumen_ia, scoring_cliente, seguimiento, etc',
    payload JSON NOT NULL,
    prioridad ENUM('alta', 'media', 'baja') DEFAULT 'media',
    estado ENUM('pendiente', 'procesando', 'completado', 'fallido') DEFAULT 'pendiente',
    intentos INT DEFAULT 0,
    max_intentos INT DEFAULT 3,
    resultado JSON NULL,
    error TEXT NULL,
    programado_para DATETIME NULL COMMENT 'NULL = ejecutar ya, fecha = ejecutar despues de',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_inicio DATETIME NULL,
    fecha_fin DATETIME NULL,
    INDEX idx_negocio (negocio_id),
    INDEX idx_estado (estado),
    INDEX idx_tipo (tipo),
    INDEX idx_prioridad (prioridad),
    INDEX idx_programado (programado_para)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: automatizaciones
-- Reglas de automatizacion por negocio
-- ============================================================
CREATE TABLE IF NOT EXISTS automatizaciones (
    idautomatizacion INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    trigger_evento VARCHAR(50) NOT NULL COMMENT 'Evento que dispara la regla',
    condicion_json JSON NULL COMMENT 'Condiciones: {"campo": "estado", "operador": "==", "valor": "abierto"}',
    accion VARCHAR(50) NOT NULL COMMENT 'enviar_whatsapp, cambiar_estado, crear_job, scoring, alerta',
    accion_config JSON NOT NULL COMMENT 'Config de la accion: {"plantilla": "seguimiento", "destino": "cliente"}',
    delay_minutos INT DEFAULT 0 COMMENT '0 = inmediato, >0 = esperar N minutos',
    activo TINYINT(1) DEFAULT 1,
    ejecutada_veces INT DEFAULT 0,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_negocio (negocio_id),
    INDEX idx_trigger (trigger_evento),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: score_clientes
-- Historial de scoring de clientes
-- ============================================================
CREATE TABLE IF NOT EXISTS score_clientes (
    idscore INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    idpersona INT NOT NULL,
    score ENUM('caliente', 'tibio', 'frio') NOT NULL,
    razon VARCHAR(255) NULL,
    accion_sugerida VARCHAR(255) NULL,
    source ENUM('ia', 'reglas', 'manual') DEFAULT 'reglas',
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_negocio (negocio_id),
    INDEX idx_persona (idpersona),
    INDEX idx_score (score),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: log_sistema
-- Log centralizado de errores y eventos del sistema
-- ============================================================
CREATE TABLE IF NOT EXISTS log_sistema (
    idlog INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NULL,
    nivel ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
    origen VARCHAR(50) NOT NULL COMMENT 'ia_service, whatsapp, worker, webhook, etc',
    mensaje TEXT NOT NULL,
    contexto JSON NULL COMMENT 'Datos adicionales para debug',
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_negocio (negocio_id),
    INDEX idx_nivel (nivel),
    INDEX idx_origen (origen),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DATOS INICIALES: Automatizaciones por defecto
-- ============================================================

-- Obtener negocio default
SET @default_negocio = (SELECT idnegocio FROM negocios WHERE slug = 'ssolutions' LIMIT 1);

-- Automatizacion: seguimiento si no responde en 24h
INSERT IGNORE INTO automatizaciones (negocio_id, nombre, trigger_evento, condicion_json, accion, accion_config, delay_minutos) VALUES
(@default_negocio,
 'Seguimiento 24h sin respuesta',
 'mensaje_enviado',
 '{"campo": "estado_flujo", "operador": "==", "valor": "esperando_respuesta"}',
 'enviar_whatsapp',
 '{"plantilla": "seguimiento_automatico", "mensaje": "Hola {nombre}, vimos que no pudiste responder. Seguimos disponibles para ayudarte!"}',
 1440);

-- Automatizacion: alerta ticket en diagnostico > 2 dias
INSERT IGNORE INTO automatizaciones (negocio_id, nombre, trigger_evento, condicion_json, accion, accion_config, delay_minutos) VALUES
(@default_negocio,
 'Alerta ticket estancado > 2 dias',
 'estado_cambiado',
 '{"campo": "estado", "operador": "==", "valor": "en_diagnostico"}',
 'alerta',
 '{"tipo": "ticket_estancado", "mensaje": "Ticket {codigo_ticket} lleva mas de 2 dias en diagnostico"}',
 2880);

-- Automatizacion: scoring despues de crear ticket
INSERT IGNORE INTO automatizaciones (negocio_id, nombre, trigger_evento, condicion_json, accion, accion_config, delay_minutos) VALUES
(@default_negocio,
 'Score cliente al crear ticket',
 'ticket_creado',
 NULL,
 'scoring',
 '{"evaluar": "inmediato"}',
 0);
