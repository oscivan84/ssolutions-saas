-- ============================================================
-- BASE DE DATOS: SSolutions - Sistema de Soporte Multi-Tenant
-- Compatible con dbsolventas17
-- Version: 3.0 (Multi-Tenant SaaS)
-- ============================================================

USE dbsolventas17;

-- ============================================================
-- TABLA: negocios (tenants)
-- Cada negocio/empresa que usa la plataforma
-- ============================================================
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

-- ============================================================
-- TABLA: usuarios_negocio (relacion usuario <-> negocio + rol)
-- ============================================================
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

-- ============================================================
-- TABLA: planes_saas
-- Definicion de planes de suscripcion
-- ============================================================
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
-- TABLA: diagnosticos
-- Almacena los reportes enviados por el agente Python
-- ============================================================
CREATE TABLE IF NOT EXISTS diagnosticos (
    iddiagnostico INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    idpersona INT NULL COMMENT 'FK a persona (cliente), NULL si es nuevo',
    nombre_cliente VARCHAR(100) NOT NULL,
    telefono_cliente VARCHAR(20) NULL,
    email_cliente VARCHAR(100) NULL,
    hostname VARCHAR(100) NOT NULL,
    sistema_operativo VARCHAR(100) NOT NULL,
    cpu_modelo VARCHAR(150) NULL,
    cpu_uso_porcentaje DECIMAL(5,2) NULL,
    cpu_nucleos INT NULL,
    ram_total_gb DECIMAL(6,2) NULL,
    ram_usada_gb DECIMAL(6,2) NULL,
    ram_uso_porcentaje DECIMAL(5,2) NULL,
    disco_total_gb DECIMAL(10,2) NULL,
    disco_usado_gb DECIMAL(10,2) NULL,
    disco_uso_porcentaje DECIMAL(5,2) NULL,
    temperatura_cpu DECIMAL(5,1) NULL,
    procesos_activos INT NULL,
    problemas_detectados TEXT NULL COMMENT 'JSON array de problemas',
    recomendaciones TEXT NULL COMMENT 'JSON array de recomendaciones',
    optimizaciones_realizadas TEXT NULL COMMENT 'JSON array de optimizaciones hechas por el agente',
    reporte_completo LONGTEXT NULL COMMENT 'JSON completo del agente',
    resumen_ia TEXT NULL COMMENT 'Resumen generado por IA en lenguaje humano',
    nivel_urgencia ENUM('baja', 'media', 'alta', 'critica') DEFAULT 'media',
    agente_version VARCHAR(20) NULL COMMENT 'Version del agente que envio el reporte',
    ip_origen VARCHAR(45) NULL COMMENT 'IP desde donde se envio el diagnostico',
    fecha_diagnostico DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_negocio (negocio_id),
    INDEX idx_persona (idpersona),
    INDEX idx_urgencia (nivel_urgencia),
    INDEX idx_fecha (fecha_diagnostico),
    INDEX idx_hostname (hostname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: tickets
-- Gestion de tickets de soporte tecnico
-- ============================================================
CREATE TABLE IF NOT EXISTS tickets (
    idticket INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    iddiagnostico INT NULL COMMENT 'FK a diagnosticos, NULL si es manual',
    idpersona INT NULL COMMENT 'FK a persona (cliente)',
    idusuario INT NULL COMMENT 'FK a usuario (tecnico asignado)',
    codigo_ticket VARCHAR(20) NOT NULL UNIQUE,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NOT NULL,
    tipo_servicio ENUM('remoto', 'en_sitio', 'taller') DEFAULT 'remoto',
    estado ENUM('abierto', 'en_diagnostico', 'en_proceso', 'esperando_repuestos', 'finalizado', 'cancelado') DEFAULT 'abierto',
    prioridad ENUM('baja', 'media', 'alta', 'critica') DEFAULT 'media',
    costo_estimado DECIMAL(10,2) DEFAULT 0,
    costo_final DECIMAL(10,2) DEFAULT 0,
    idventa INT NULL COMMENT 'FK a venta cuando se convierte en venta',
    notas TEXT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fecha_cierre DATETIME NULL,
    INDEX idx_negocio (negocio_id),
    INDEX idx_persona (idpersona),
    INDEX idx_usuario (idusuario),
    INDEX idx_estado (estado),
    INDEX idx_diagnostico (iddiagnostico),
    INDEX idx_codigo (codigo_ticket),
    INDEX idx_venta (idventa),
    INDEX idx_prioridad (prioridad),
    INDEX idx_fecha_creacion (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: historial_estados_ticket
-- Auditoria de cambios de estado en tickets
-- ============================================================
CREATE TABLE IF NOT EXISTS historial_estados_ticket (
    idhistorial INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    idticket INT NOT NULL,
    estado_anterior VARCHAR(30) NULL,
    estado_nuevo VARCHAR(30) NOT NULL,
    idusuario INT NULL COMMENT 'Usuario que realizo el cambio',
    observacion TEXT NULL,
    fecha_cambio DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_negocio (negocio_id),
    INDEX idx_ticket (idticket),
    INDEX idx_fecha (fecha_cambio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: mantenimientos
-- Historial de acciones realizadas en cada ticket
-- ============================================================
CREATE TABLE IF NOT EXISTS mantenimientos (
    idmantenimiento INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    idticket INT NOT NULL,
    idusuario INT NULL COMMENT 'FK a usuario (tecnico que realizo)',
    tipo_accion ENUM('diagnostico', 'reparacion', 'limpieza', 'instalacion', 'actualizacion', 'reemplazo', 'otro') NOT NULL,
    descripcion TEXT NOT NULL,
    repuestos_usados TEXT NULL COMMENT 'JSON: [{idarticulo, nombre, cantidad, precio}]',
    duracion_minutos INT DEFAULT 0,
    costo DECIMAL(10,2) DEFAULT 0,
    fecha_accion DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_negocio (negocio_id),
    INDEX idx_ticket (idticket),
    INDEX idx_usuario (idusuario),
    INDEX idx_fecha (fecha_accion),
    INDEX idx_tipo (tipo_accion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: mensajes_whatsapp
-- Log de mensajes enviados/recibidos por WhatsApp
-- ============================================================
CREATE TABLE IF NOT EXISTS mensajes_whatsapp (
    idmensaje INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    idticket INT NULL,
    idpersona INT NULL,
    telefono VARCHAR(20) NOT NULL,
    direccion ENUM('enviado', 'recibido') NOT NULL,
    tipo ENUM('texto', 'plantilla', 'interactivo', 'imagen', 'documento') DEFAULT 'texto',
    contenido TEXT NOT NULL,
    message_id VARCHAR(100) NULL COMMENT 'ID de WhatsApp Cloud API',
    estado ENUM('pendiente', 'enviado', 'entregado', 'leido', 'fallido') DEFAULT 'pendiente',
    error_detalle TEXT NULL COMMENT 'Detalle del error si fallo',
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_negocio (negocio_id),
    INDEX idx_ticket (idticket),
    INDEX idx_persona (idpersona),
    INDEX idx_telefono (telefono),
    INDEX idx_fecha (fecha),
    INDEX idx_message_id (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: conversaciones_whatsapp
-- Flujos conversacionales activos con clientes
-- ============================================================
CREATE TABLE IF NOT EXISTS conversaciones_whatsapp (
    idconversacion INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    idpersona INT NULL,
    idticket INT NULL,
    telefono VARCHAR(20) NOT NULL,
    estado_flujo ENUM('inicio', 'esperando_respuesta', 'confirmacion_servicio', 'cotizacion', 'cerrada') DEFAULT 'inicio',
    contexto_json TEXT NULL COMMENT 'Estado actual del flujo conversacional',
    ultimo_mensaje_enviado TEXT NULL,
    ultimo_mensaje_recibido TEXT NULL,
    fecha_inicio DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_ultimo_mensaje DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_negocio (negocio_id),
    INDEX idx_telefono (telefono),
    INDEX idx_estado (estado_flujo),
    INDEX idx_ticket (idticket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: plantillas_mensaje
-- Plantillas reutilizables para mensajes automaticos
-- ============================================================
CREATE TABLE IF NOT EXISTS plantillas_mensaje (
    idplantilla INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    nombre VARCHAR(50) NOT NULL,
    canal ENUM('whatsapp', 'email', 'sms') DEFAULT 'whatsapp',
    asunto VARCHAR(200) NULL,
    contenido TEXT NOT NULL COMMENT 'Soporta variables: {nombre}, {codigo_ticket}, {estado}, {costo}',
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_nombre_negocio (nombre, negocio_id),
    INDEX idx_negocio (negocio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: configuracion_soporte
-- Configuracion por negocio (negocio_id NULL = global)
-- ============================================================
CREATE TABLE IF NOT EXISTS configuracion_soporte (
    idconfig INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    clave VARCHAR(50) NOT NULL,
    valor TEXT NULL,
    descripcion VARCHAR(200) NULL,
    UNIQUE KEY uk_clave_negocio (clave, negocio_id),
    INDEX idx_negocio (negocio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DATOS INICIALES
-- ============================================================

-- Planes SaaS
INSERT IGNORE INTO planes_saas (nombre, codigo, precio_mensual, max_tickets_mes, max_tecnicos, whatsapp_habilitado, ia_habilitada, diagnosticos_habilitados, automatizacion_habilitada, descripcion) VALUES
('Basico', 'basico', 49900, 50, 3, 0, 0, 0, 0,
 'CRM basico con seguimiento manual de tickets. Ideal para empezar.'),
('Pro', 'pro', 149900, 200, 10, 1, 1, 0, 0,
 'Bot WhatsApp con Claude + mensajes automaticos. Para negocios en crecimiento.'),
('Premium', 'premium', 299900, 999999, 50, 1, 1, 1, 1,
 'Automatizacion completa: diagnosticos, IA avanzada, reportes. Para escalar.');

-- Negocio por defecto
INSERT INTO negocios (nombre, slug, plan, estado, max_tickets_mes, max_tecnicos, whatsapp_habilitado, ia_habilitada)
VALUES ('SSolutions', 'ssolutions', 'premium', 'activo', 999999, 50, 1, 1);

SET @default_negocio = LAST_INSERT_ID();

-- Configuracion por defecto para el negocio inicial
INSERT IGNORE INTO configuracion_soporte (negocio_id, clave, valor, descripcion) VALUES
(@default_negocio, 'whatsapp_token', '', 'Token de acceso WhatsApp Cloud API'),
(@default_negocio, 'whatsapp_phone_id', '', 'Phone Number ID de WhatsApp Business'),
(@default_negocio, 'whatsapp_verify_token', '', 'Token de verificacion del webhook'),
(@default_negocio, 'ai_api_key', '', 'API Key del servicio de IA (OpenAI/Anthropic)'),
(@default_negocio, 'ai_provider', 'anthropic', 'Proveedor de IA: openai o anthropic'),
(@default_negocio, 'ai_model', 'claude-haiku-4-5-20251001', 'Modelo de IA a usar'),
(@default_negocio, 'costo_hora_remoto', '25000', 'Costo por hora de soporte remoto'),
(@default_negocio, 'costo_hora_sitio', '40000', 'Costo por hora de soporte en sitio'),
(@default_negocio, 'costo_hora_taller', '30000', 'Costo por hora de soporte en taller'),
(@default_negocio, 'ticket_prefix', 'TKT', 'Prefijo para codigos de ticket'),
(@default_negocio, 'api_key_agente', '', 'API Key para autenticar el agente Python'),
(@default_negocio, 'empresa_nombre', 'SSolutions', 'Nombre de la empresa para mensajes'),
(@default_negocio, 'empresa_telefono', '', 'Telefono principal de la empresa'),
(@default_negocio, 'moneda_simbolo', '$', 'Simbolo de moneda para costos'),
(@default_negocio, 'iva_porcentaje', '19', 'Porcentaje de IVA aplicable');

-- Plantillas para el negocio inicial
INSERT IGNORE INTO plantillas_mensaje (negocio_id, nombre, canal, contenido) VALUES
(@default_negocio, 'diagnostico_nuevo', 'whatsapp',
 'Hola {nombre}, hemos recibido un diagnostico de tu equipo.\n\nCodigo de ticket: {codigo_ticket}\nNivel de urgencia: {urgencia}\n\n{resumen}\n\nResponde SI para recibir una cotizacion del servicio.'),
(@default_negocio, 'cotizacion_servicio', 'whatsapp',
 'Hola {nombre}, aqui tienes la cotizacion para tu ticket {codigo_ticket}:\n\nServicio: {tipo_servicio}\nCosto estimado: {moneda}{costo}\n\nResponde ACEPTAR para confirmar o LLAMAR si prefieres hablar con un tecnico.'),
(@default_negocio, 'ticket_en_proceso', 'whatsapp',
 'Hola {nombre}, tu ticket {codigo_ticket} esta ahora en proceso.\nTecnico asignado: {tecnico}\n\nTe mantendremos informado del avance.'),
(@default_negocio, 'ticket_finalizado', 'whatsapp',
 'Hola {nombre}, tu servicio ha sido completado.\n\nTicket: {codigo_ticket}\nCosto final: {moneda}{costo}\n\nGracias por confiar en nosotros. Califica nuestro servicio del 1 al 5.'),
(@default_negocio, 'seguimiento_automatico', 'whatsapp',
 'Hola {nombre}, detectamos problemas en tu equipo. Quieres ayuda para solucionarlos?\n\nProblemas encontrados:\n{problemas}\n\nResponde SI para mas informacion.');
