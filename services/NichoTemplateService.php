<?php
/**
 * NichoTemplateService — Auto-carga servicios, flujo y prompts al crear negocio.
 *
 * Cuando se crea un negocio con tipo_negocio='estetica':
 *   NichoTemplateService::aplicar($idnegocio, 'estetica');
 *
 * Carga automáticamente:
 * - Servicios del módulo (servicios_negocio)
 * - Flujo conversacional (flujos_conversacion)
 * - Prompts de ventas (prompts_ia)
 * - Plantillas de mensaje (plantillas_mensaje)
 * - Módulos activos (modulos_negocio)
 * - Configuración base (configuracion_soporte)
 * - Automatizaciones por defecto
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/tenant.php';

class NichoTemplateService {

    /**
     * Aplicar template completo a un negocio nuevo
     */
    public static function aplicar(int $idnegocio, string $tipoNegocio): array {
        $resultados = [];

        // 1. Cargar definición del módulo
        $modulePath = __DIR__ . "/../modules/{$tipoNegocio}/module.php";
        if (!file_exists($modulePath)) {
            $modulePath = __DIR__ . '/../modules/base/module.php';
        }
        $modulo = require $modulePath;

        // 2. Activar módulos
        $resultados['modulos'] = self::crearModulos($idnegocio, $tipoNegocio);

        // 3. Crear servicios
        $resultados['servicios'] = self::crearServicios($idnegocio, $modulo['servicios_default'] ?? []);

        // 4. Crear flujo conversacional
        $resultados['flujo'] = self::crearFlujo($idnegocio, $tipoNegocio);

        // 5. Crear prompt de ventas
        $resultados['prompt'] = self::crearPrompt($idnegocio, $tipoNegocio, $modulo['prompt_ventas'] ?? '');

        // 6. Crear configuración base
        $resultados['config'] = self::crearConfigBase($idnegocio);

        // 7. Crear plantillas de mensaje
        $resultados['plantillas'] = self::crearPlantillas($idnegocio, $tipoNegocio);

        // 8. Crear automatizaciones
        $resultados['automatizaciones'] = self::crearAutomatizaciones($idnegocio);

        return $resultados;
    }

    /**
     * Listar nichos disponibles con preview
     */
    public static function listarNichos(): array {
        $nichos = [];
        $modulesDir = __DIR__ . '/../modules/';
        $dirs = glob($modulesDir . '*/module.php');

        foreach ($dirs as $f) {
            $mod = require $f;
            if ($mod['slug'] === 'base') continue;
            $nichos[] = [
                'slug' => $mod['slug'],
                'nombre' => $mod['nombre'],
                'descripcion' => $mod['descripcion'],
                'icono' => $mod['icono'],
                'servicios' => count($mod['servicios_default'] ?? []),
                'features' => $mod['features'] ?? [],
            ];
        }

        return $nichos;
    }

    // ============================================================
    // CREADORES INTERNOS
    // ============================================================

    private static function crearModulos(int $idnegocio, string $tipo): int {
        $count = 0;
        $modulos = ['base', $tipo];
        foreach ($modulos as $mod) {
            $sql = "INSERT IGNORE INTO modulos_negocio (idnegocio, modulo, config_json) VALUES (?, ?, '{}')";
            if (ejecutarConsulta($sql, 'is', [$idnegocio, $mod])) $count++;
        }
        return $count;
    }

    private static function crearServicios(int $idnegocio, array $servicios): int {
        $count = 0;
        foreach ($servicios as $i => $s) {
            $sql = "INSERT IGNORE INTO servicios_negocio (negocio_id, nombre, slug, precio_base, duracion_minutos, orden)
                    VALUES (?, ?, ?, ?, ?, ?)";
            if (ejecutarConsulta($sql, 'issdii', [
                $idnegocio, $s['nombre'], $s['slug'],
                $s['precio_base'] ?? 0, $s['duracion_minutos'] ?? 60, $i + 1
            ])) $count++;
        }
        return $count;
    }

    private static function crearFlujo(int $idnegocio, string $tipo): bool {
        // Buscar flujo template para este tipo
        $sql = "SELECT pasos FROM flujos_conversacion WHERE tipo_negocio = ? AND nombre = 'principal' AND activo = 1 LIMIT 1";
        $result = ejecutarConsulta($sql, 's', [$tipo]);
        $template = $result ? $result->fetch_object() : null;

        if ($template) {
            $sql = "INSERT IGNORE INTO flujos_conversacion (negocio_id, nombre, version, tipo_negocio, pasos, activo, estabilidad)
                    VALUES (?, 'principal', 1, ?, ?, 1, 'estable')";
            return (bool)ejecutarConsulta($sql, 'iss', [$idnegocio, $tipo, $template->pasos]);
        }

        return false;
    }

    private static function crearPrompt(int $idnegocio, string $tipo, string $prompt): bool {
        if (empty($prompt)) return false;

        $sql = "INSERT IGNORE INTO prompts_ia (negocio_id, tipo_negocio, tipo, nombre, system_prompt, version, activo, estabilidad, es_default)
                VALUES (?, ?, 'ventas', 'default', ?, 1, 1, 'estable', 1)";
        return (bool)ejecutarConsulta($sql, 'iss', [$idnegocio, $tipo, $prompt]);
    }

    private static function crearConfigBase(int $idnegocio): int {
        $configs = [
            ['ai_provider', 'anthropic', 'Proveedor de IA'],
            ['ai_model', 'claude-haiku-4-5-20251001', 'Modelo de IA'],
            ['ticket_prefix', 'TKT', 'Prefijo de tickets'],
            ['moneda_simbolo', '$', 'Símbolo de moneda'],
            ['iva_porcentaje', '19', 'IVA %'],
        ];

        $count = 0;
        foreach ($configs as $c) {
            $sql = "INSERT IGNORE INTO configuracion_soporte (negocio_id, clave, valor, descripcion) VALUES (?, ?, ?, ?)";
            if (ejecutarConsulta($sql, 'isss', [$idnegocio, $c[0], $c[1], $c[2]])) $count++;
        }
        return $count;
    }

    private static function crearPlantillas(int $idnegocio, string $tipo): int {
        $plantillas = [
            ['bienvenida', 'Hola {nombre}! Gracias por contactarnos. En que podemos ayudarte?'],
            ['cotizacion', 'Hola {nombre}, aqui tienes la cotizacion:\n\nServicio: {tipo_servicio}\nCosto: {moneda}{costo}\n\nConfirmas?'],
            ['confirmacion', 'Tu servicio ha sido confirmado. Te contactaremos pronto.'],
            ['seguimiento', 'Hola {nombre}! Vimos que no pudiste responder. Seguimos disponibles para ayudarte.'],
        ];

        $count = 0;
        foreach ($plantillas as $p) {
            $sql = "INSERT IGNORE INTO plantillas_mensaje (negocio_id, nombre, canal, contenido) VALUES (?, ?, 'whatsapp', ?)";
            if (ejecutarConsulta($sql, 'iss', [$idnegocio, $p[0], $p[1]])) $count++;
        }
        return $count;
    }

    private static function crearAutomatizaciones(int $idnegocio): int {
        $autos = [
            [
                'Seguimiento 24h sin respuesta',
                'mensaje_enviado',
                '{"campo": "estado_flujo", "operador": "==", "valor": "esperando_respuesta"}',
                'enviar_whatsapp',
                '{"plantilla": "seguimiento"}',
                1440
            ],
            [
                'Score al crear ticket',
                'ticket_creado',
                null,
                'scoring',
                '{"evaluar": "inmediato"}',
                0
            ],
        ];

        $count = 0;
        foreach ($autos as $a) {
            $sql = "INSERT IGNORE INTO automatizaciones (negocio_id, nombre, trigger_evento, condicion_json, accion, accion_config, delay_minutos)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            if (ejecutarConsulta($sql, 'isssssi', [$idnegocio, $a[0], $a[1], $a[2], $a[3], $a[4], $a[5]])) $count++;
        }
        return $count;
    }
}
