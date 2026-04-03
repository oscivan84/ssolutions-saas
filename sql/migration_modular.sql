-- ============================================================
-- MIGRACION: Arquitectura Modular (LEGO)
-- Version: 4.0
-- Ejecutar DESPUES de migration_ux.sql
--
-- Convierte el sistema de "CRM de técnicos" a
-- "Motor de conversión por WhatsApp" multi-nicho
-- ============================================================

USE dbsolventas17;

-- ============================================================
-- 1. Agregar tipo_negocio a negocios
-- ============================================================
ALTER TABLE negocios
    ADD COLUMN tipo_negocio VARCHAR(30) DEFAULT 'tecnico' AFTER plan,
    ADD INDEX idx_tipo (tipo_negocio);

-- ============================================================
-- 2. TABLA: modulos_negocio
-- Qué módulos tiene activados cada negocio
-- ============================================================
CREATE TABLE IF NOT EXISTS modulos_negocio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    idnegocio INT NOT NULL,
    modulo VARCHAR(30) NOT NULL COMMENT 'tecnico, estetica, automotriz, base',
    activo TINYINT(1) DEFAULT 1,
    config_json JSON NULL COMMENT 'Config específica del módulo para este negocio',
    fecha_activacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_negocio_modulo (idnegocio, modulo),
    INDEX idx_negocio (idnegocio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. TABLA: servicios_negocio
-- Servicios configurables por negocio (reemplaza ENUM hardcodeado)
-- ============================================================
CREATE TABLE IF NOT EXISTS servicios_negocio (
    idservicio INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL COMMENT 'identificador: remoto, en_sitio, unas_acrilicas, etc',
    descripcion TEXT NULL,
    precio_base DECIMAL(10,2) DEFAULT 0,
    duracion_minutos INT DEFAULT 60,
    activo TINYINT(1) DEFAULT 1,
    orden INT DEFAULT 0,
    UNIQUE KEY uk_negocio_slug (negocio_id, slug),
    INDEX idx_negocio (negocio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. TABLA: flujos_conversacion
-- Motor de flujo configurable por tipo de negocio
-- En vez de switch hardcodeado, el flujo es JSON
-- ============================================================
CREATE TABLE IF NOT EXISTS flujos_conversacion (
    idflujo INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL DEFAULT 'principal',
    tipo_negocio VARCHAR(30) NOT NULL DEFAULT 'tecnico',
    pasos JSON NOT NULL COMMENT 'Array de pasos del flujo conversacional',
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_negocio_nombre (negocio_id, nombre),
    INDEX idx_negocio (negocio_id),
    INDEX idx_tipo (tipo_negocio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. Prompts por tipo de negocio (ya existe prompts_ia, agregar tipo_negocio)
-- ============================================================
ALTER TABLE prompts_ia
    ADD COLUMN tipo_negocio VARCHAR(30) DEFAULT 'tecnico' AFTER negocio_id,
    ADD INDEX idx_tipo_negocio (tipo_negocio);

-- ============================================================
-- DATOS: Migrar negocio existente
-- ============================================================
SET @nid = (SELECT idnegocio FROM negocios WHERE slug = 'ssolutions' LIMIT 1);

-- Asignar tipo_negocio al negocio existente
UPDATE negocios SET tipo_negocio = 'tecnico' WHERE idnegocio = @nid;

-- Activar módulo técnico
INSERT IGNORE INTO modulos_negocio (idnegocio, modulo, config_json) VALUES
(@nid, 'base', '{"version": "1.0"}'),
(@nid, 'tecnico', '{"diagnosticos": true, "mantenimientos": true}');

-- Migrar servicios de config a tabla servicios_negocio
INSERT IGNORE INTO servicios_negocio (negocio_id, nombre, slug, precio_base, duracion_minutos, orden) VALUES
(@nid, 'Soporte Remoto', 'remoto', 25000, 60, 1),
(@nid, 'Soporte En Sitio', 'en_sitio', 40000, 60, 2),
(@nid, 'Soporte Taller', 'taller', 30000, 60, 3);

-- ============================================================
-- FLUJO: Técnico (default)
-- ============================================================
INSERT IGNORE INTO flujos_conversacion (negocio_id, nombre, tipo_negocio, pasos) VALUES
(@nid, 'principal', 'tecnico', '{
  "estados": {
    "inicio": {
      "mensaje_bienvenida": "Hola {nombre}! Bienvenido a {empresa}. En que podemos ayudarte?",
      "opciones": ["Diagnostico PC", "Estado de ticket", "Precios"],
      "transiciones": {
        "ACEPTAR_SERVICIO": "seleccion_servicio",
        "CONSULTAR_PRECIO": "mostrar_precios",
        "CONSULTAR_ESTADO": "mostrar_estado",
        "default": "esperando_respuesta"
      }
    },
    "esperando_respuesta": {
      "transiciones": {
        "ACEPTAR_SERVICIO": "seleccion_servicio",
        "RECHAZAR_SERVICIO": "cerrada",
        "CONSULTAR_PRECIO": "mostrar_precios",
        "CONSULTAR_ESTADO": "mostrar_estado",
        "default": "respuesta_ia"
      }
    },
    "seleccion_servicio": {
      "mensaje": "Que tipo de servicio necesitas?",
      "tipo_input": "botones",
      "opciones_from": "servicios_negocio",
      "transiciones": {
        "servicio_seleccionado": "cotizacion"
      }
    },
    "mostrar_precios": {
      "mensaje": "Nuestras tarifas:",
      "tipo_input": "lista_servicios",
      "transiciones": {
        "ACEPTAR_SERVICIO": "seleccion_servicio",
        "default": "esperando_respuesta"
      }
    },
    "cotizacion": {
      "mensaje": "Servicio: {servicio}\\nCosto: {moneda}{precio}\\n\\nConfirmas?",
      "transiciones": {
        "ACEPTAR_SERVICIO": "confirmado",
        "RECHAZAR_SERVICIO": "cerrada",
        "default": "esperando_respuesta"
      }
    },
    "confirmado": {
      "mensaje": "Excelente! Tu servicio ha sido confirmado. Un tecnico te contactara pronto.",
      "accion": "confirmar_servicio",
      "transiciones": {"default": "cerrada"}
    },
    "cerrada": {
      "mensaje": "Gracias por contactarnos. Si necesitas algo mas, escribe cuando quieras.",
      "es_final": true
    }
  }
}');

-- ============================================================
-- FLUJO: Estética (ejemplo segundo nicho)
-- ============================================================
INSERT IGNORE INTO flujos_conversacion (negocio_id, nombre, tipo_negocio, pasos) VALUES
(0, 'principal', 'estetica', '{
  "estados": {
    "inicio": {
      "mensaje_bienvenida": "Hola {nombre}! Bienvenida a {empresa}. Que servicio te interesa?",
      "opciones": ["Ver servicios", "Agendar cita", "Precios"],
      "transiciones": {
        "ACEPTAR_SERVICIO": "seleccion_servicio",
        "CONSULTAR_PRECIO": "mostrar_precios",
        "AGENDAR_CITA": "agendar",
        "default": "esperando_respuesta"
      }
    },
    "esperando_respuesta": {
      "transiciones": {
        "ACEPTAR_SERVICIO": "seleccion_servicio",
        "RECHAZAR_SERVICIO": "cerrada",
        "CONSULTAR_PRECIO": "mostrar_precios",
        "AGENDAR_CITA": "agendar",
        "default": "respuesta_ia"
      }
    },
    "seleccion_servicio": {
      "mensaje": "Estos son nuestros servicios:",
      "tipo_input": "botones",
      "opciones_from": "servicios_negocio",
      "transiciones": {
        "servicio_seleccionado": "agendar"
      }
    },
    "agendar": {
      "mensaje": "Perfecto! Para cuando te gustaria la cita?\\n\\n1. Hoy\\n2. Manana\\n3. Esta semana",
      "transiciones": {
        "ACEPTAR_SERVICIO": "confirmado",
        "default": "cotizacion"
      }
    },
    "cotizacion": {
      "mensaje": "Servicio: {servicio}\\nPrecio: {moneda}{precio}\\nDuracion: {duracion} min\\n\\nAgendamos?",
      "transiciones": {
        "ACEPTAR_SERVICIO": "confirmado",
        "RECHAZAR_SERVICIO": "cerrada"
      }
    },
    "confirmado": {
      "mensaje": "Cita confirmada! Te esperamos. Te enviaremos un recordatorio.",
      "accion": "confirmar_cita",
      "transiciones": {"default": "cerrada"}
    },
    "cerrada": {
      "mensaje": "Gracias por tu interes! Nos vemos pronto.",
      "es_final": true
    }
  }
}');
