<?php
/**
 * Sistema de Eventos Internos
 *
 * Desacopla acciones: en vez de hacer todo inline,
 * se emite un evento y los listeners reaccionan.
 *
 * Uso:
 *   EventService::emit('ticket_creado', ['idticket' => 1, 'codigo' => 'TKT-001']);
 *   EventService::emit('mensaje_recibido', ['telefono' => '573001234567', 'contenido' => 'Hola']);
 */

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/tenant.php";
require_once __DIR__ . "/NotificacionService.php";

class EventService {

    // Guard contra recursión: máximo 3 niveles de profundidad
    private static int $depth = 0;
    private const MAX_DEPTH = 3;

    /**
     * Emitir un evento (persiste en BD + dispara listeners síncronos)
     */
    public static function emit($tipo, $payload = []) {
        // Protección contra recursión infinita (evento → job → evento → ...)
        if (self::$depth >= self::MAX_DEPTH) {
            error_log("EventService: recursión detectada (depth=" . self::$depth . ") — evento '$tipo' ignorado");
            return null;
        }
        self::$depth++;

        $negocioId = Tenant::id();

        // 1. Persistir evento
        $sql = "INSERT INTO eventos (negocio_id, tipo, payload) VALUES (?, ?, ?)";
        ejecutarConsulta($sql, 'iss', [$negocioId, $tipo, json_encode($payload)]);
        $idevento = ultimoId();

        // 2. Verificar automatizaciones para este evento
        try { self::verificarAutomatizaciones($tipo, $payload); }
        catch (\Exception $e) { error_log("Event automation error: " . $e->getMessage()); }

        // 3. Generar notificaciones según tipo de evento
        try { self::generarNotificacion($tipo, $payload); }
        catch (\Exception $e) { error_log("Event notification error: " . $e->getMessage()); }

        // 4. Marcar como procesado
        $sql = "UPDATE eventos SET procesado = 1, fecha_procesado = NOW() WHERE idevento = ?";
        ejecutarConsulta($sql, 'i', [$idevento]);

        self::$depth--;
        return $idevento;
    }

    /**
     * Verificar y ejecutar automatizaciones para un evento
     */
    private static function verificarAutomatizaciones($tipo, $payload) {
        $sql = "SELECT * FROM automatizaciones
                WHERE negocio_id = ? AND trigger_evento = ? AND activo = 1";
        $result = ejecutarConsulta($sql, 'is', [Tenant::id(), $tipo]);

        if (!$result) return;

        while ($auto = $result->fetch_object()) {
            // Verificar condición
            if (!self::evaluarCondicion($auto->condicion_json, $payload)) {
                continue;
            }

            if ($auto->delay_minutos > 0) {
                // Crear job programado
                JobQueue::crear(
                    $auto->accion,
                    array_merge(
                        json_decode($auto->accion_config, true) ?: [],
                        ['payload_evento' => $payload, 'idautomatizacion' => $auto->idautomatizacion]
                    ),
                    'media',
                    $auto->delay_minutos
                );
            } else {
                // Ejecutar inmediatamente como job
                JobQueue::crear(
                    $auto->accion,
                    array_merge(
                        json_decode($auto->accion_config, true) ?: [],
                        ['payload_evento' => $payload, 'idautomatizacion' => $auto->idautomatizacion]
                    )
                );
            }

            // Incrementar contador
            $sql = "UPDATE automatizaciones SET ejecutada_veces = ejecutada_veces + 1 WHERE idautomatizacion = ?";
            ejecutarConsulta($sql, 'i', [$auto->idautomatizacion]);
        }
    }

    /**
     * Evaluar condición de una automatización contra el payload
     */
    private static function evaluarCondicion($condicionJson, $payload) {
        if (empty($condicionJson)) return true; // Sin condición = siempre

        $condicion = json_decode($condicionJson, true);
        if (!$condicion) return true;

        $campo = $condicion['campo'] ?? '';
        $operador = $condicion['operador'] ?? '==';
        $valorEsperado = $condicion['valor'] ?? '';
        $valorReal = $payload[$campo] ?? null;

        if ($valorReal === null) return false;

        switch ($operador) {
            case '==': return $valorReal == $valorEsperado;
            case '!=': return $valorReal != $valorEsperado;
            case '>':  return $valorReal > $valorEsperado;
            case '<':  return $valorReal < $valorEsperado;
            case 'contains': return strpos($valorReal, $valorEsperado) !== false;
            default: return false;
        }
    }

    /**
     * Generar notificaciones automáticas según tipo de evento
     */
    private static function generarNotificacion($tipo, $payload) {
        switch ($tipo) {
            case 'ticket_creado':
                $codigo = $payload['codigo'] ?? '';
                $prioridad = $payload['prioridad'] ?? 'media';
                $notifTipo = ($prioridad === 'critica' || $prioridad === 'alta') ? 'alerta' : 'ticket_nuevo';
                NotificacionService::crear($notifTipo,
                    "Nuevo ticket: $codigo",
                    "Prioridad: $prioridad. " . ($payload['tipo_servicio'] ?? ''),
                    'soporte_tickets',
                    $payload['idticket'] ?? null
                );
                break;

            case 'mensaje_recibido':
                $telefono = $payload['telefono'] ?? '';
                // Throttle: max 1 notificación por teléfono cada 5 min (evitar spam)
                $sqlThrottle = "SELECT COUNT(*) as c FROM notificaciones
                    WHERE negocio_id = ? AND tipo = 'cliente_respondio'
                    AND titulo LIKE ? AND fecha >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
                $resT = ejecutarConsulta($sqlThrottle, 'is', [Tenant::id(), "%$telefono%"]);
                $yaNotificado = $resT && $resT->fetch_object()->c > 0;

                if (!$yaNotificado) {
                    $contenido = mb_substr($payload['contenido'] ?? '', 0, 80);
                    NotificacionService::crear('cliente_respondio',
                        "Cliente respondio ($telefono)",
                        $contenido,
                        'soporte_tickets'
                    );
                }
                break;

            case 'score_generado':
                $score = $payload['score'] ?? '';
                if ($score === 'caliente') {
                    NotificacionService::crear('cliente_caliente',
                        "Cliente caliente detectado!",
                        ($payload['razon'] ?? '') . '. ' . ($payload['accion_sugerida'] ?? ''),
                        'soporte_automatizacion',
                        $payload['idpersona'] ?? null
                    );
                }
                break;

            case 'estado_cambiado':
                if (($payload['estado'] ?? '') === 'finalizado') {
                    NotificacionService::crear('venta_cerrada',
                        "Ticket cerrado: " . ($payload['codigo_ticket'] ?? ''),
                        "Se cerro venta exitosamente",
                        'soporte_tickets',
                        $payload['idticket'] ?? null
                    );
                }
                break;
        }
    }
}
