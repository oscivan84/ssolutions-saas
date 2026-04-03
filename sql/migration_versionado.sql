-- ============================================================
-- MIGRACION: Versionado de flujos y prompts
-- Version: 4.1
-- Ejecutar DESPUES de migration_modular.sql
-- ============================================================

USE dbsolventas17;

-- ============================================================
-- 1. Versionado de flujos conversacionales
-- ============================================================
ALTER TABLE flujos_conversacion
    ADD COLUMN version INT NOT NULL DEFAULT 1 AFTER nombre,
    ADD COLUMN estabilidad ENUM('estable', 'experimental', 'deprecated') DEFAULT 'estable' AFTER activo,
    ADD COLUMN metricas_json JSON NULL COMMENT '{"conversiones": 32, "abandonos": 18, "total": 50}' AFTER estabilidad,
    ADD COLUMN creado_por INT NULL COMMENT 'idusuario que creó esta versión',
    ADD COLUMN notas_cambio TEXT NULL COMMENT 'Qué cambió en esta versión',
    DROP INDEX uk_negocio_nombre,
    ADD UNIQUE KEY uk_negocio_nombre_version (negocio_id, nombre, version);

-- ============================================================
-- 2. Control de prompts: métricas por versión
-- ============================================================
ALTER TABLE prompts_ia
    ADD COLUMN estabilidad ENUM('estable', 'experimental', 'deprecated') DEFAULT 'estable' AFTER activo,
    ADD COLUMN metricas_json JSON NULL COMMENT '{"usos": 100, "conversion": 0.32, "satisfaccion": 0.85}' AFTER estabilidad,
    ADD COLUMN creado_por INT NULL;

-- ============================================================
-- 3. TABLA: ab_tests
-- A/B testing de flujos y prompts
-- ============================================================
CREATE TABLE IF NOT EXISTS ab_tests (
    idtest INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id INT NOT NULL,
    tipo ENUM('flujo', 'prompt') NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    variante_a_id INT NOT NULL COMMENT 'ID del flujo/prompt variante A',
    variante_b_id INT NOT NULL COMMENT 'ID del flujo/prompt variante B',
    porcentaje_b INT DEFAULT 50 COMMENT '% de tráfico que va a variante B',
    estado ENUM('activo', 'pausado', 'finalizado') DEFAULT 'activo',
    metricas_a JSON NULL COMMENT '{"conversiones": N, "total": N}',
    metricas_b JSON NULL COMMENT '{"conversiones": N, "total": N}',
    ganador ENUM('a', 'b', 'empate') NULL,
    fecha_inicio DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_fin DATETIME NULL,
    INDEX idx_negocio (negocio_id),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. Actualizar flujos existentes con versión
-- ============================================================
UPDATE flujos_conversacion SET version = 1, estabilidad = 'estable' WHERE version = 0 OR version IS NULL;
