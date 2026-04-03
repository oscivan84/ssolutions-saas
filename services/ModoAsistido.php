<?php
/**
 * ModoAsistido — Detecta cuándo el bot necesita intervención humana.
 *
 * Triggers de intervención:
 * 1. Cliente caliente detectado (score = caliente)
 * 2. Objeción fuerte (precio, queja, insatisfacción)
 * 3. Silencio prolongado después de cotización
 * 4. Queja o reclamación
 * 5. Bot da respuesta genérica 2+ veces seguidas
 *
 * Genera: notificación urgente + registro en intervenciones_humanas
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/tenant.php';
require_once __DIR__ . '/NotificacionService.php';

class ModoAsistido {

    /**
     * Evaluar si una conversación necesita intervención humana.
     * Llamar después de cada mensaje procesado.
     *
     * @param string $telefono
     * @param string $mensajeCliente Último mensaje del cliente
     * @param string $respuestaBot Respuesta que generó el bot
     * @param array $contexto Contexto de la conversación
     * @return array|null null = no intervenir, array = razón + datos
     */
    public static function evaluar(string $telefono, string $mensajeCliente, string $respuestaBot, array $contexto = []): ?array {
        $msg = mb_strtolower($mensajeCliente);

        // 1. Queja o reclamación explícita → SIEMPRE intervenir
        if (preg_match('/\b(queja|reclamo|demanda|abogado|denuncia|estafa|robo|pésimo|horrible|terrible|peor)\b/i', $msg)) {
            return self::disparar($telefono, 'queja', $mensajeCliente, $contexto,
                "Cliente se queja o reclama. Intervención urgente.");
        }

        // 2. Objeción de precio fuerte → intervenir si es conversación avanzada
        if (preg_match('/\b(muy caro|robo|no pago|absurdo|ridículo|otro lado.*barato|competencia.*menos)\b/i', $msg)) {
            $etapa = $contexto['estado'] ?? $contexto['estado_flujo'] ?? '';
            if (in_array($etapa, ['cotizacion', 'confirmacion_servicio', 'en_proceso'])) {
                return self::disparar($telefono, 'objecion_fuerte', $mensajeCliente, $contexto,
                    "Objeción de precio fuerte en etapa de cotización.");
            }
        }

        // 3. Cliente caliente + bot no cerró → intervenir
        $score = $contexto['score_cliente'] ?? '';
        if ($score === 'caliente') {
            $estado = $contexto['estado'] ?? $contexto['estado_flujo'] ?? '';
            if (!in_array($estado, ['confirmado', 'cerrada', 'finalizado'])) {
                return self::disparar($telefono, 'cliente_caliente', $mensajeCliente, $contexto,
                    "Cliente caliente detectado. Oportunidad de cierre manual.");
            }
        }

        // 4. Bot respondió genérico (fallback) → posible confusión
        if (stripos($respuestaBot, 'asesor revisará tu consulta') !== false
            || stripos($respuestaBot, 'te respondera pronto') !== false) {
            return self::disparar($telefono, 'escalacion', $mensajeCliente, $contexto,
                "Bot no pudo clasificar el mensaje. Respuesta genérica enviada.");
        }

        return null; // No requiere intervención
    }

    /**
     * Verificar silencio prolongado (llamar desde worker/cron)
     * Busca conversaciones en cotización sin respuesta > 2 horas
     */
    public static function verificarSilencios() {
        $nid = Tenant::id();

        $sql = "SELECT c.*, p.nombre AS cliente
                FROM conversaciones_whatsapp c
                LEFT JOIN persona p ON c.idpersona = p.idpersona
                WHERE c.negocio_id = ?
                AND c.estado_flujo IN ('cotizacion', 'confirmacion_servicio')
                AND c.fecha_ultimo_mensaje < DATE_SUB(NOW(), INTERVAL 2 HOUR)
                AND c.fecha_ultimo_mensaje >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND NOT EXISTS (
                    SELECT 1 FROM intervenciones_humanas ih
                    WHERE ih.idconversacion = c.idconversacion AND ih.razon = 'silencio_prolongado'
                    AND ih.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                )";
        $result = ejecutarConsulta($sql, 'i', [$nid]);

        $count = 0;
        if ($result) {
            while ($conv = $result->fetch_object()) {
                self::disparar($conv->telefono, 'silencio_prolongado', '', [
                    'estado_flujo' => $conv->estado_flujo,
                    'idconversacion' => $conv->idconversacion,
                    'nombre_cliente' => $conv->cliente ?? 'Cliente'
                ], "Cliente en cotización sin respuesta por 2+ horas.");
                $count++;
            }
        }

        return $count;
    }

    /**
     * Disparar intervención: registrar + notificar
     */
    private static function disparar(string $telefono, string $razon, string $mensajeTrigger, array $contexto, string $descripcion): array {
        $nid = Tenant::id();

        // 1. Registrar en BD
        $sql = "INSERT INTO intervenciones_humanas (negocio_id, idconversacion, idticket, telefono, razon, mensaje_trigger)
                VALUES (?, ?, ?, ?, ?, ?)";
        ejecutarConsulta($sql, 'iiisss', [
            $nid,
            $contexto['idconversacion'] ?? null,
            $contexto['idticket'] ?? null,
            $telefono,
            $razon,
            mb_substr($mensajeTrigger, 0, 500)
        ]);

        // 2. Notificación urgente
        $nombre = $contexto['nombre_cliente'] ?? $contexto['cliente'] ?? $telefono;
        $iconMap = [
            'queja' => 'exclamation-circle',
            'objecion_fuerte' => 'dollar',
            'cliente_caliente' => 'fire',
            'silencio_prolongado' => 'clock-o',
            'escalacion' => 'hand-paper-o',
        ];

        NotificacionService::crear('alerta',
            "Intervención: $nombre",
            $descripcion . ($mensajeTrigger ? " Msg: \"" . mb_substr($mensajeTrigger, 0, 80) . "\"" : ''),
            'soporte_tickets',
            $contexto['idticket'] ?? null
        );

        return [
            'requiere_intervencion' => true,
            'razon' => $razon,
            'descripcion' => $descripcion,
            'telefono' => $telefono
        ];
    }

    /**
     * Contar intervenciones pendientes
     */
    public static function pendientes(): int {
        $sql = "SELECT COUNT(*) as total FROM intervenciones_humanas
                WHERE negocio_id = ? AND estado = 'pendiente'";
        $result = ejecutarConsulta($sql, 'i', [Tenant::id()]);
        return $result ? intval($result->fetch_object()->total) : 0;
    }
}
