<?php
/**
 * FunnelService — Tracking completo de conversión.
 *
 * Registra cada etapa: contacto → respuesta → interés → cotización → cierre → venta
 * Calcula métricas de funnel por negocio.
 *
 * Uso:
 *   FunnelService::registrarContacto($telefono, $idpersona);
 *   FunnelService::avanzarEtapa($telefono, 'cotizacion', ['monto' => 50000]);
 *   FunnelService::marcarPerdido($telefono, 'sin_respuesta');
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/tenant.php';

class FunnelService {

    /**
     * Registrar nuevo contacto en el funnel
     */
    public static function registrarContacto($telefono, $idpersona = null, $fuente = 'organico') {
        $nid = Tenant::id();

        // Verificar si ya tiene funnel activo (no perdido/venta)
        $sql = "SELECT idfunnel FROM funnel_conversiones
                WHERE negocio_id = ? AND telefono = ? AND etapa_actual NOT IN ('venta','perdido')
                ORDER BY fecha_contacto DESC LIMIT 1";
        $result = ejecutarConsulta($sql, 'is', [$nid, $telefono]);
        if ($result && $result->fetch_object()) {
            return; // Ya tiene funnel activo
        }

        $sql = "INSERT INTO funnel_conversiones (negocio_id, idpersona, telefono, etapa_actual, fuente)
                VALUES (?, ?, ?, 'contacto', ?)";
        ejecutarConsulta($sql, 'iiss', [$nid, $idpersona, $telefono, $fuente]);
    }

    /**
     * Avanzar etapa del funnel
     */
    // Orden válido de etapas (solo avanzar, nunca retroceder)
    private static array $ordenEtapas = [
        'contacto' => 0, 'respuesta' => 1, 'interes' => 2,
        'cotizacion' => 3, 'cierre' => 4, 'venta' => 5, 'perdido' => 99
    ];

    public static function avanzarEtapa($telefono, string $nuevaEtapa, array $datos = []) {
        // Validar etapa
        if (!isset(self::$ordenEtapas[$nuevaEtapa])) {
            return; // Etapa inválida — ignorar silenciosamente
        }

        $nid = Tenant::id();

        // Obtener funnel activo
        $sql = "SELECT * FROM funnel_conversiones
                WHERE negocio_id = ? AND telefono = ? AND etapa_actual NOT IN ('venta','perdido')
                ORDER BY fecha_contacto DESC LIMIT 1";
        $result = ejecutarConsulta($sql, 'is', [$nid, $telefono]);
        $funnel = $result ? $result->fetch_object() : null;

        // Validar que no retrocede (excepto 'perdido' que siempre se puede)
        if ($funnel && $nuevaEtapa !== 'perdido') {
            $ordenActual = self::$ordenEtapas[$funnel->etapa_actual] ?? 0;
            $ordenNueva = self::$ordenEtapas[$nuevaEtapa] ?? 0;
            if ($ordenNueva <= $ordenActual) {
                return; // No retroceder
            }
        }

        if (!$funnel) {
            self::registrarContacto($telefono);
            $result = ejecutarConsulta($sql, 'is', [$nid, $telefono]);
            $funnel = $result ? $result->fetch_object() : null;
            if (!$funnel) return;
        }

        // Construir UPDATE dinámico
        $sets = ["etapa_actual = ?"];
        $types = 's';
        $params = [$nuevaEtapa];

        // Timestamp de la etapa
        $campoFecha = "fecha_{$nuevaEtapa}";
        $sets[] = "$campoFecha = NOW()";

        // Datos opcionales
        if (isset($datos['monto'])) {
            if ($nuevaEtapa === 'cotizacion') {
                $sets[] = "monto_cotizado = ?";
            } else {
                $sets[] = "monto_cerrado = ?";
            }
            $types .= 'd';
            $params[] = floatval($datos['monto']);
        }
        if (isset($datos['servicio'])) {
            $sets[] = "servicio_slug = ?";
            $types .= 's';
            $params[] = $datos['servicio'];
        }
        if (isset($datos['idticket'])) {
            $sets[] = "idticket = ?";
            $types .= 'i';
            $params[] = intval($datos['idticket']);
        }
        if (isset($datos['idconversacion'])) {
            $sets[] = "idconversacion = ?";
            $types .= 'i';
            $params[] = intval($datos['idconversacion']);
        }
        if (isset($datos['score'])) {
            $sets[] = "score_cliente = ?";
            $types .= 's';
            $params[] = $datos['score'];
        }

        // Calcular tiempo de primera respuesta
        if ($nuevaEtapa === 'respuesta' && $funnel->fecha_contacto) {
            $sets[] = "tiempo_primera_respuesta_seg = TIMESTAMPDIFF(SECOND, fecha_contacto, NOW())";
        }

        // Calcular tiempo de cierre
        if ($nuevaEtapa === 'cierre' && $funnel->fecha_contacto) {
            $sets[] = "tiempo_cierre_seg = TIMESTAMPDIFF(SECOND, fecha_contacto, NOW())";
        }

        $types .= 'i';
        $params[] = $funnel->idfunnel;

        $sql = "UPDATE funnel_conversiones SET " . implode(', ', $sets) . " WHERE idfunnel = ?";
        ejecutarConsulta($sql, $types, $params);
    }

    /**
     * Incrementar contador de mensajes
     */
    public static function incrementarMensajes($telefono) {
        $sql = "UPDATE funnel_conversiones SET mensajes_total = mensajes_total + 1
                WHERE negocio_id = ? AND telefono = ? AND etapa_actual NOT IN ('venta','perdido')
                ORDER BY fecha_contacto DESC LIMIT 1";
        ejecutarConsulta($sql, 'is', [Tenant::id(), $telefono]);
    }

    /**
     * Marcar como perdido
     */
    public static function marcarPerdido($telefono) {
        self::avanzarEtapa($telefono, 'perdido');
    }

    /**
     * Obtener métricas del funnel (últimos 30 días)
     */
    public static function metricas(): array {
        $nid = Tenant::id();

        $sql = "SELECT
            COUNT(*) as total_contactos,
            SUM(CASE WHEN etapa_actual IN ('respuesta','interes','cotizacion','cierre','venta') THEN 1 ELSE 0 END) as respondieron,
            SUM(CASE WHEN etapa_actual IN ('interes','cotizacion','cierre','venta') THEN 1 ELSE 0 END) as interesados,
            SUM(CASE WHEN etapa_actual IN ('cotizacion','cierre','venta') THEN 1 ELSE 0 END) as cotizados,
            SUM(CASE WHEN etapa_actual IN ('cierre','venta') THEN 1 ELSE 0 END) as cerrados,
            SUM(CASE WHEN etapa_actual = 'venta' THEN 1 ELSE 0 END) as ventas,
            SUM(CASE WHEN etapa_actual = 'perdido' THEN 1 ELSE 0 END) as perdidos,
            COALESCE(SUM(monto_cerrado), 0) as ingresos_total,
            AVG(tiempo_primera_respuesta_seg) as avg_tiempo_respuesta_seg,
            AVG(tiempo_cierre_seg) as avg_tiempo_cierre_seg,
            AVG(mensajes_total) as avg_mensajes
        FROM funnel_conversiones
        WHERE negocio_id = ? AND fecha_contacto >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

        $result = ejecutarConsulta($sql, 'i', [$nid]);
        $data = $result ? $result->fetch_assoc() : [];

        // Calcular tasas de conversión por etapa
        $total = intval($data['total_contactos'] ?? 0);
        $data['tasa_respuesta'] = $total > 0 ? round(intval($data['respondieron']) / $total * 100, 1) : 0;
        $data['tasa_interes'] = $total > 0 ? round(intval($data['interesados']) / $total * 100, 1) : 0;
        $data['tasa_cotizacion'] = $total > 0 ? round(intval($data['cotizados']) / $total * 100, 1) : 0;
        $data['tasa_cierre'] = $total > 0 ? round(intval($data['cerrados']) / $total * 100, 1) : 0;
        $data['tasa_venta'] = $total > 0 ? round(intval($data['ventas']) / $total * 100, 1) : 0;

        return $data;
    }
}
