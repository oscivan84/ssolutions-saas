<?php
/**
 * Servicio de Conversaciones WhatsApp
 * Gestiona el flujo conversacional automatico con clientes
 * Procesa mensajes entrantes y genera respuestas inteligentes
 */

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/tenant.php";
require_once __DIR__ . "/WhatsAppService.php";
require_once __DIR__ . "/AIService.php";
require_once __DIR__ . "/FlowEngine.php";
require_once __DIR__ . "/FunnelService.php";
require_once __DIR__ . "/ModoAsistido.php";
require_once __DIR__ . "/../model/Ticket.php";

class ConversacionService {

    private $whatsapp;
    private $ai;
    private $objTicket;
    private ?FlowEngine $flowEngine = null;

    public function __construct() {
        $this->whatsapp = new WhatsAppService();
        $this->ai = new AIService();
        $this->objTicket = new Ticket();
    }

    /**
     * Procesar mensaje entrante y generar respuesta automatica.
     * Usa FlowEngine (JSON configurable) si hay flujo definido.
     * Fallback al switch hardcodeado para backward compatibility.
     */
    public function procesarMensajeEntrante($telefono, $contenido, $buttonId = null) {
        // Tracking funnel: registrar contacto + incrementar mensajes
        FunnelService::registrarContacto($telefono);
        FunnelService::incrementarMensajes($telefono);

        // Buscar o crear conversacion activa
        $conv = $this->obtenerConversacionActiva($telefono);

        if (!$conv) {
            return $this->iniciarConversacion($telefono, $contenido);
        }

        // Actualizar ultimo mensaje recibido
        $this->actualizarConversacion($conv->idconversacion, [
            'ultimo_mensaje_recibido' => $contenido
        ]);

        // Funnel: avanzar a 'respuesta' si es primera respuesta del cliente
        FunnelService::avanzarEtapa($telefono, 'respuesta');

        // Intentar FlowEngine (JSON configurable) primero
        $resultado = $this->procesarConFlowEngine($conv, $contenido, $buttonId);
        if ($resultado !== null) {
            return $resultado;
        }

        // Solo llegar aquí si NO hay flujo JSON configurado (legacy mode)
        // Si FlowEngine retornó null = no hay flujo, usar switch hardcodeado
        switch ($conv->estado_flujo) {
            case 'inicio':
                return $this->procesarInicio($conv, $contenido, $buttonId);
            case 'esperando_respuesta':
                return $this->procesarRespuesta($conv, $contenido, $buttonId);
            case 'confirmacion_servicio':
                return $this->procesarConfirmacion($conv, $contenido, $buttonId);
            case 'cotizacion':
                return $this->procesarCotizacion($conv, $contenido, $buttonId);
            default:
                return $this->respuestaGenerica($conv, $contenido);
        }
    }

    /**
     * Procesar mensaje usando FlowEngine (JSON configurable).
     * Retorna null si no hay flujo configurado → usa fallback.
     */
    private function procesarConFlowEngine($conv, $contenido, $buttonId) {
        try {
            $engine = $this->getFlowEngine($conv);
            if (!$engine) return null; // null = no hay flujo configurado → legacy switch

            // Clasificar intención
            $intencion = $buttonId ? $this->mapearBoton($buttonId) : $this->ai->clasificarIntencion($contenido);

            // Detectar si seleccionó un servicio
            $seleccion = $this->detectarServicio($contenido, $buttonId);

            // Procesar transición
            $resultado = $engine->procesar($conv->estado_flujo, $intencion, $seleccion);

            // Si necesita IA → delegar a respuesta genérica
            if ($resultado['usa_ia'] ?? false) {
                return $this->respuestaGenerica($conv, $contenido);
            }

            // Actualizar estado
            if ($resultado['estado_nuevo'] !== $conv->estado_flujo) {
                $this->actualizarConversacion($conv->idconversacion, [
                    'estado_flujo' => $resultado['es_final'] ? 'cerrada' : $resultado['estado_nuevo']
                ]);
            }

            // Ejecutar acción si hay
            if ($resultado['accion']) {
                $this->ejecutarAccionFlujo($resultado['accion'], $conv, $seleccion);
            }

            // Enviar respuesta
            if ($resultado['mensaje']) {
                if (!empty($resultado['opciones']) && count($resultado['opciones']) <= 3) {
                    $botones = [];
                    foreach ($resultado['opciones'] as $i => $op) {
                        $botones['btn_' . $i] = substr($op, 0, 20);
                    }
                    $this->whatsapp->enviarBotones(
                        $conv->telefono,
                        Tenant::config('empresa_nombre', 'SSolutions'),
                        $resultado['mensaje'],
                        $botones,
                        $conv->idticket, $conv->idpersona
                    );
                } else {
                    $this->whatsapp->enviarTexto(
                        $conv->telefono, $resultado['mensaje'],
                        $conv->idticket, $conv->idpersona
                    );
                }
            }

            // Cerrar si es final
            if ($resultado['es_final']) {
                $this->cerrarConversacion($conv->idconversacion);
            }

            return true;
        } catch (\Exception $e) {
            error_log("FlowEngine error: " . $e->getMessage());
            return null; // Fallback al switch
        }
    }

    private function getFlowEngine($conv): ?FlowEngine {
        if ($this->flowEngine === null) {
            $loader = ModuleLoader::getInstance();
            $flujo = $loader->getFlujoConversacion();
            if (!$flujo) return null;

            $contexto = [];
            if ($conv->idticket) {
                $ticket = $this->obtenerTicketCompleto($conv->idticket);
                if ($ticket) $contexto = (array)$ticket;
            }
            if ($conv->idpersona) {
                $contexto['nombre_cliente'] = $this->obtenerNombreCliente($conv->idpersona);
            }

            $this->flowEngine = new FlowEngine($flujo, $contexto);
        }
        return $this->flowEngine;
    }

    private function detectarServicio($contenido, $buttonId): ?string {
        $loader = ModuleLoader::getInstance();
        foreach ($loader->getServicios() as $s) {
            if ($buttonId && stripos($buttonId, $s['slug']) !== false) return $s['slug'];
            if (stripos($contenido, $s['nombre']) !== false) return $s['slug'];
            if (stripos($contenido, $s['slug']) !== false) return $s['slug'];
        }
        return null;
    }

    private function ejecutarAccionFlujo(string $accion, $conv, ?string $seleccion) {
        switch ($accion) {
            case 'confirmar_servicio':
            case 'confirmar_cita':
                if ($conv->idticket) {
                    $sql = "UPDATE tickets SET estado = 'en_diagnostico' WHERE idticket = ? AND negocio_id = ? AND estado = 'abierto'";
                    ejecutarConsulta($sql, 'ii', [$conv->idticket, Tenant::id()]);
                }
                break;
        }
    }

    /**
     * Iniciar nueva conversacion
     */
    private function iniciarConversacion($telefono, $mensaje) {
        // Buscar si tiene tickets activos
        $idpersona = $this->buscarPersonaPorTelefono($telefono);
        $ticketActivo = null;
        $nombreCliente = null;

        if ($idpersona) {
            $ticketActivo = $this->buscarTicketActivo($idpersona);
            $nombreCliente = $this->obtenerNombreCliente($idpersona);
        }

        $contexto = [
            'idpersona' => $idpersona,
            'idticket' => $ticketActivo ? $ticketActivo->idticket : null,
            'nombre_cliente' => $nombreCliente,
            'mensaje_original' => $mensaje
        ];

        // Crear conversacion
        $sql = "INSERT INTO conversaciones_whatsapp (negocio_id, idpersona, idticket, telefono, estado_flujo, contexto_json, ultimo_mensaje_recibido)
                VALUES (?, ?, ?, ?, 'esperando_respuesta', ?, ?)";
        ejecutarConsulta($sql, 'iiisss', [
            Tenant::id(),
            $idpersona, $ticketActivo ? $ticketActivo->idticket : null,
            $telefono, json_encode($contexto), $mensaje
        ]);

        // Generar respuesta con el nuevo método orientado a ventas
        if ($ticketActivo) {
            $contextoCompleto = $this->construirContextoCliente($ticketActivo, $idpersona, $nombreCliente);
            $respuesta = $this->ai->responderWhatsApp($mensaje, $contextoCompleto);
            $this->whatsapp->enviarTexto($telefono, $respuesta, $ticketActivo->idticket, $idpersona);
        } else {
            // Clasificar intencion
            $intencion = $this->ai->clasificarIntencion($mensaje);
            $respuesta = $this->generarRespuestaIntencion($intencion, $telefono, $contexto);
        }

        return true;
    }

    /**
     * Procesar mensaje en estado 'esperando_respuesta'
     */
    private function procesarRespuesta($conv, $contenido, $buttonId) {
        $contexto = json_decode($conv->contexto_json, true) ?: [];
        $intencion = $buttonId ? $this->mapearBoton($buttonId) : $this->ai->clasificarIntencion($contenido);

        switch ($intencion) {
            case 'ACEPTAR_SERVICIO':
                // Mover a confirmacion
                $this->actualizarConversacion($conv->idconversacion, [
                    'estado_flujo' => 'confirmacion_servicio'
                ]);

                if ($conv->idticket) {
                    $ticket = $this->obtenerTicketCompleto($conv->idticket);
                    $this->whatsapp->enviarBotones(
                        $conv->telefono,
                        'Confirmar servicio',
                        "Tipo de servicio para tu ticket {$ticket->codigo_ticket}:\n"
                        . "Costo estimado: $" . number_format($ticket->costo_estimado, 0),
                        [
                            'tipo_remoto' => 'Remoto',
                            'tipo_sitio' => 'En sitio',
                            'tipo_taller' => 'Taller'
                        ],
                        $conv->idticket, $conv->idpersona
                    );
                } else {
                    $this->whatsapp->enviarTexto(
                        $conv->telefono,
                        "Que tipo de servicio necesitas?\n\n"
                        . "1. Remoto (asistencia a distancia)\n"
                        . "2. En sitio (vamos a tu ubicacion)\n"
                        . "3. Taller (traes tu equipo)\n\n"
                        . "Responde con el numero.",
                        null, $conv->idpersona
                    );
                }
                return true;

            case 'RECHAZAR_SERVICIO':
                $this->cerrarConversacion($conv->idconversacion);
                $this->whatsapp->enviarTexto(
                    $conv->telefono,
                    "Entendido, no hay problema. Si necesitas ayuda en el futuro, no dudes en escribirnos. Buen dia!",
                    $conv->idticket, $conv->idpersona
                );
                return true;

            case 'CONSULTAR_PRECIO':
                if ($conv->idticket) {
                    $ticket = $this->obtenerTicketCompleto($conv->idticket);
                    $moneda = getConfigSoporte('moneda_simbolo') ?: '$';
                    $this->whatsapp->enviarTexto(
                        $conv->telefono,
                        "El costo estimado para tu servicio es: {$moneda}" . number_format($ticket->costo_estimado, 0)
                        . ".\n\nQuieres proceder? Responde SI o NO.",
                        $conv->idticket, $conv->idpersona
                    );
                } else {
                    $this->whatsapp->enviarTexto(
                        $conv->telefono,
                        "Para darte un presupuesto necesitamos primero realizar un diagnostico. Quieres agendar uno?",
                        null, $conv->idpersona
                    );
                }
                return true;

            case 'CONSULTAR_ESTADO':
                if ($conv->idticket) {
                    $ticket = $this->obtenerTicketCompleto($conv->idticket);
                    $estado = ucwords(str_replace('_', ' ', $ticket->estado));
                    $this->whatsapp->enviarTexto(
                        $conv->telefono,
                        "Tu ticket {$ticket->codigo_ticket} esta en estado: *{$estado}*.\n"
                        . ($ticket->tecnico ? "Tecnico asignado: {$ticket->tecnico}" : "Aun no se ha asignado tecnico."),
                        $conv->idticket, $conv->idpersona
                    );
                }
                return true;

            default:
                return $this->respuestaGenerica($conv, $contenido);
        }
    }

    /**
     * Procesar confirmacion de tipo de servicio
     */
    private function procesarConfirmacion($conv, $contenido, $buttonId) {
        $tipoServicio = null;

        if ($buttonId === 'tipo_remoto' || preg_match('/\b(1|remoto)\b/i', $contenido)) {
            $tipoServicio = 'remoto';
        } elseif ($buttonId === 'tipo_sitio' || preg_match('/\b(2|sitio|casa|oficina)\b/i', $contenido)) {
            $tipoServicio = 'en_sitio';
        } elseif ($buttonId === 'tipo_taller' || preg_match('/\b(3|taller|llev)\b/i', $contenido)) {
            $tipoServicio = 'taller';
        }

        if (!$tipoServicio) {
            $this->whatsapp->enviarTexto(
                $conv->telefono,
                "No entendi tu seleccion. Por favor elige:\n1. Remoto\n2. En sitio\n3. Taller",
                $conv->idticket, $conv->idpersona
            );
            return true;
        }

        // Actualizar ticket si existe
        if ($conv->idticket) {
            $sql = "UPDATE tickets SET tipo_servicio = ? WHERE idticket = ? AND negocio_id = ?";
            ejecutarConsulta($sql, 'sii', [$tipoServicio, $conv->idticket, Tenant::id()]);
        }

        // Mover a cotizacion
        $this->actualizarConversacion($conv->idconversacion, [
            'estado_flujo' => 'cotizacion'
        ]);

        // Enviar cotizacion
        if ($conv->idticket) {
            $ticket = $this->obtenerTicketCompleto($conv->idticket);
            $this->whatsapp->enviarDesdePlantilla('cotizacion_servicio', $conv->telefono, [
                'nombre' => $ticket->cliente ?? 'Cliente',
                'codigo_ticket' => $ticket->codigo_ticket,
                'tipo_servicio' => ucfirst(str_replace('_', ' ', $tipoServicio)),
                'moneda' => getConfigSoporte('moneda_simbolo') ?: '$',
                'costo' => number_format($ticket->costo_estimado, 0)
            ], $conv->idticket, $conv->idpersona);
        } else {
            $tarifaKey = 'costo_hora_' . str_replace('en_', '', $tipoServicio);
            $tarifa = getConfigSoporte($tarifaKey) ?: '25000';
            $moneda = getConfigSoporte('moneda_simbolo') ?: '$';
            $this->whatsapp->enviarTexto(
                $conv->telefono,
                "Servicio: " . ucfirst(str_replace('_', ' ', $tipoServicio)) . "\n"
                . "Tarifa por hora: {$moneda}" . number_format(floatval($tarifa), 0) . "\n\n"
                . "Quieres confirmar? Responde ACEPTAR o CANCELAR.",
                null, $conv->idpersona
            );
        }

        return true;
    }

    /**
     * Procesar aceptacion/rechazo de cotizacion
     */
    private function procesarCotizacion($conv, $contenido, $buttonId) {
        $acepta = $buttonId === 'aceptar_cotizacion'
            || preg_match('/\b(si|acepto|aceptar|ok|dale|confirmo)\b/i', $contenido);

        if ($acepta) {
            $this->cerrarConversacion($conv->idconversacion);

            // Cambiar estado del ticket a en_proceso
            if ($conv->idticket) {
                $sql = "UPDATE tickets SET estado = 'en_diagnostico' WHERE idticket = ? AND negocio_id = ? AND estado = 'abierto'";
                ejecutarConsulta($sql, 'ii', [$conv->idticket, Tenant::id()]);
            }

            $this->whatsapp->enviarTexto(
                $conv->telefono,
                "Excelente! Tu servicio ha sido confirmado. "
                . "Un tecnico se comunicara contigo pronto para coordinar. "
                . "Gracias por tu confianza!",
                $conv->idticket, $conv->idpersona
            );
        } else {
            $this->cerrarConversacion($conv->idconversacion);
            $this->whatsapp->enviarTexto(
                $conv->telefono,
                "Entendido. Si cambias de opinion, puedes escribirnos cuando quieras. Buen dia!",
                $conv->idticket, $conv->idpersona
            );
        }

        return true;
    }

    /**
     * Respuesta generica usando IA orientada a ventas
     */
    private function respuestaGenerica($conv, $contenido) {
        $contexto = [];
        $nombreCliente = null;

        if ($conv->idpersona) {
            $nombreCliente = $this->obtenerNombreCliente($conv->idpersona);
        }

        if ($conv->idticket) {
            $ticket = $this->obtenerTicketCompleto($conv->idticket);
            if ($ticket) {
                $contexto = $this->construirContextoCliente($ticket, $conv->idpersona, $nombreCliente);
            }
        } else {
            $contexto = [
                'nombre_cliente' => $nombreCliente,
                'historial_mensajes' => $this->obtenerHistorialReciente($conv->telefono)
            ];
        }

        $respuesta = $this->ai->responderWhatsApp($contenido, $contexto);
        $this->whatsapp->enviarTexto($conv->telefono, $respuesta, $conv->idticket, $conv->idpersona);

        // Modo asistido: evaluar si necesita intervención humana
        $contexto['estado_flujo'] = $conv->estado_flujo ?? '';
        $contexto['idconversacion'] = $conv->idconversacion ?? null;
        $contexto['idticket'] = $conv->idticket ?? null;
        ModoAsistido::evaluar($conv->telefono, $contenido, $respuesta, $contexto);

        return true;
    }

    // ============================================================
    // HELPERS
    // ============================================================

    private function obtenerConversacionActiva($telefono) {
        $sql = "SELECT * FROM conversaciones_whatsapp
                WHERE negocio_id = ? AND telefono = ? AND estado_flujo != 'cerrada'
                AND fecha_ultimo_mensaje >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY fecha_ultimo_mensaje DESC LIMIT 1";
        $result = ejecutarConsulta($sql, 'is', [Tenant::id(), $telefono]);
        return $result ? $result->fetch_object() : null;
    }

    private function actualizarConversacion($idconversacion, $campos) {
        $sets = [];
        $types = '';
        $params = [];

        foreach ($campos as $campo => $valor) {
            $sets[] = "$campo = ?";
            $types .= 's';
            $params[] = $valor;
        }

        $types .= 'i';
        $params[] = $idconversacion;

        $sql = "UPDATE conversaciones_whatsapp SET " . implode(', ', $sets) . " WHERE idconversacion = ?";
        ejecutarConsulta($sql, $types, $params);
    }

    private function cerrarConversacion($idconversacion) {
        $this->actualizarConversacion($idconversacion, ['estado_flujo' => 'cerrada']);
    }

    private function buscarPersonaPorTelefono($telefono) {
        $telefono = preg_replace('/[^0-9]/', '', $telefono);
        $telefono_corto = substr($telefono, -10);

        // Primero: buscar persona que ya tiene relación con este negocio (tickets/diagnosticos)
        $sql = "SELECT p.idpersona FROM persona p
                WHERE p.telefono LIKE ?
                AND (
                    EXISTS (SELECT 1 FROM tickets t WHERE t.idpersona = p.idpersona AND t.negocio_id = ?)
                    OR EXISTS (SELECT 1 FROM diagnosticos d WHERE d.idpersona = p.idpersona AND d.negocio_id = ?)
                    OR EXISTS (SELECT 1 FROM conversaciones_whatsapp c WHERE c.idpersona = p.idpersona AND c.negocio_id = ?)
                )
                LIMIT 1";
        $result = ejecutarConsulta($sql, 'siii', ['%' . $telefono_corto, Tenant::id(), Tenant::id(), Tenant::id()]);
        if ($result && $row = $result->fetch_object()) {
            return $row->idpersona;
        }

        // Fallback: buscar sin filtro de negocio (cliente nuevo)
        $sql = "SELECT idpersona FROM persona WHERE telefono LIKE ? LIMIT 1";
        $result = ejecutarConsulta($sql, 's', ['%' . $telefono_corto]);
        if ($result && $row = $result->fetch_object()) {
            return $row->idpersona;
        }

        return null;
    }

    private function buscarTicketActivo($idpersona) {
        $sql = "SELECT t.*, p.nombre AS cliente, u.login AS tecnico
                FROM tickets t
                LEFT JOIN persona p ON t.idpersona = p.idpersona
                LEFT JOIN usuario u ON t.idusuario = u.idusuario
                WHERE t.negocio_id = ? AND t.idpersona = ? AND t.estado NOT IN ('finalizado', 'cancelado')
                ORDER BY t.fecha_creacion DESC LIMIT 1";
        $result = ejecutarConsulta($sql, 'ii', [Tenant::id(), $idpersona]);
        return $result ? $result->fetch_object() : null;
    }

    private function obtenerTicketCompleto($idticket) {
        $query = $this->objTicket->ObtenerPorId($idticket);
        return $query ? $query->fetch_object() : null;
    }

    private function mapearBoton($buttonId) {
        $mapa = [
            'btn_si' => 'ACEPTAR_SERVICIO',
            'btn_no' => 'RECHAZAR_SERVICIO',
            'btn_precio' => 'CONSULTAR_PRECIO',
            'btn_estado' => 'CONSULTAR_ESTADO',
            'aceptar_cotizacion' => 'ACEPTAR_SERVICIO',
            'rechazar_cotizacion' => 'RECHAZAR_SERVICIO'
        ];
        return $mapa[$buttonId] ?? 'OTRO';
    }

    /**
     * Construir contexto completo del cliente para la IA (memoria de cliente)
     */
    private function construirContextoCliente($ticket, $idpersona, $nombreCliente = null) {
        $contexto = [];

        if ($ticket) {
            $ticketArr = (array)$ticket;
            $contexto = array_merge($contexto, $ticketArr);
        }

        if ($nombreCliente) {
            $contexto['nombre_cliente'] = $nombreCliente;
        }

        // Agregar historial de mensajes recientes para dar contexto conversacional
        if ($idpersona) {
            $contexto['historial_mensajes'] = $this->obtenerHistorialReciente(null, $idpersona);
        }

        return $contexto;
    }

    /**
     * Obtener nombre del cliente
     */
    private function obtenerNombreCliente($idpersona) {
        $sql = "SELECT nombre FROM persona WHERE idpersona = ? LIMIT 1";
        $result = ejecutarConsulta($sql, 'i', [$idpersona]);
        if ($result && $row = $result->fetch_object()) {
            return $row->nombre;
        }
        return null;
    }

    /**
     * Obtener historial reciente de mensajes para contexto
     */
    private function obtenerHistorialReciente($telefono = null, $idpersona = null) {
        if ($idpersona) {
            $sql = "SELECT direccion, contenido, fecha FROM mensajes_whatsapp
                    WHERE negocio_id = ? AND idpersona = ? ORDER BY fecha DESC LIMIT 6";
            $result = ejecutarConsulta($sql, 'ii', [Tenant::id(), $idpersona]);
        } elseif ($telefono) {
            $sql = "SELECT direccion, contenido, fecha FROM mensajes_whatsapp
                    WHERE negocio_id = ? AND telefono = ? ORDER BY fecha DESC LIMIT 6";
            $result = ejecutarConsulta($sql, 'is', [Tenant::id(), $telefono]);
        } else {
            return '';
        }

        if (!$result) return '';

        $mensajes = [];
        while ($row = $result->fetch_object()) {
            $quien = $row->direccion === 'recibido' ? 'Cliente' : 'Bot';
            $mensajes[] = "$quien: " . mb_substr($row->contenido, 0, 100);
        }

        // Invertir para orden cronológico
        return implode("\n", array_reverse($mensajes));
    }

    private function generarRespuestaIntencion($intencion, $telefono, $contexto) {
        $empresa = getConfigSoporte('empresa_nombre') ?: 'SSolutions';

        switch ($intencion) {
            case 'SALUDO':
                $this->whatsapp->enviarBotones(
                    $telefono, $empresa,
                    "Hola! Bienvenido a $empresa. En que podemos ayudarte?",
                    [
                        'btn_diagnostico' => 'Diagnostico PC',
                        'btn_estado' => 'Estado de ticket',
                        'btn_precio' => 'Precios'
                    ]
                );
                break;

            case 'CONSULTAR_PRECIO':
                $moneda = getConfigSoporte('moneda_simbolo') ?: '$';
                $remoto = getConfigSoporte('costo_hora_remoto') ?: '25000';
                $sitio = getConfigSoporte('costo_hora_sitio') ?: '40000';
                $taller = getConfigSoporte('costo_hora_taller') ?: '30000';

                $this->whatsapp->enviarTexto($telefono,
                    "Nuestras tarifas:\n\n"
                    . "- Remoto: {$moneda}" . number_format(floatval($remoto), 0) . "/hora\n"
                    . "- En sitio: {$moneda}" . number_format(floatval($sitio), 0) . "/hora\n"
                    . "- Taller: {$moneda}" . number_format(floatval($taller), 0) . "/hora\n\n"
                    . "Quieres agendar un servicio?"
                );
                break;

            default:
                $this->whatsapp->enviarTexto($telefono,
                    "Gracias por escribirnos a $empresa. "
                    . "Un asesor revisara tu mensaje y te respondera pronto."
                );
                break;
        }

        return true;
    }
}
