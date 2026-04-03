<?php
/**
 * Servicio de Tickets
 * Logica de negocio para el ciclo de vida completo del ticket:
 * - Creacion desde diagnostico o manual
 * - Transiciones de estado con auditoria
 * - Calculo de costos
 * - Conversion a venta
 * - Notificaciones al cliente
 */

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/tenant.php";
require_once __DIR__ . "/../model/Ticket.php";
require_once __DIR__ . "/../model/Mantenimiento.php";
require_once __DIR__ . "/../model/MensajeWhatsApp.php";
require_once __DIR__ . "/WhatsAppService.php";
require_once __DIR__ . "/EventService.php";
require_once __DIR__ . "/JobQueue.php";

class TicketService {

    private $objTicket;
    private $objMantenimiento;
    private $whatsapp;

    public function __construct() {
        $this->objTicket = new Ticket();
        $this->objMantenimiento = new Mantenimiento();
        $this->whatsapp = new WhatsAppService();
    }

    /**
     * Crear ticket desde diagnostico y notificar cliente
     */
    public function crearDesdeDiagnostico($iddiagnostico, $idpersona, $titulo, $descripcion, $prioridad, $tipo_servicio = 'remoto') {
        $resultado = $this->objTicket->CrearDesdeDiagnostico(
            $iddiagnostico, $idpersona, $titulo, $descripcion, $prioridad, $tipo_servicio
        );

        if ($resultado['success']) {
            // Registrar en historial
            $this->registrarHistorialEstado($resultado['idticket'], null, 'abierto', null, 'Ticket creado automaticamente desde diagnostico');

            // Estimar costo segun tipo de servicio
            $costo = $this->estimarCosto($tipo_servicio, $prioridad);
            $this->objTicket->ActualizarCosto($resultado['idticket'], $costo, 0);
            $resultado['costo_estimado'] = $costo;

            // Emitir evento
            EventService::emit('ticket_creado', [
                'idticket' => $resultado['idticket'],
                'codigo' => $resultado['codigo'],
                'idpersona' => $idpersona,
                'iddiagnostico' => $iddiagnostico,
                'prioridad' => $prioridad,
                'tipo_servicio' => $tipo_servicio
            ]);
        }

        return $resultado;
    }

    /**
     * Cambiar estado con auditoria y notificacion
     */
    public function cambiarEstado($idticket, $nuevoEstado, $idusuario = null, $observacion = '') {
        // Obtener estado actual
        $query = $this->objTicket->ObtenerPorId($idticket);
        $ticket = $query ? $query->fetch_object() : null;

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado'];
        }

        $estadoAnterior = $ticket->estado;

        // Validar transicion permitida
        if (!$this->transicionPermitida($estadoAnterior, $nuevoEstado)) {
            return [
                'success' => false,
                'message' => "No se puede cambiar de '$estadoAnterior' a '$nuevoEstado'"
            ];
        }

        // Ejecutar cambio
        $result = $this->objTicket->CambiarEstado($idticket, $nuevoEstado);
        if (!$result) {
            return ['success' => false, 'message' => 'Error al actualizar estado'];
        }

        // Registrar historial
        $this->registrarHistorialEstado($idticket, $estadoAnterior, $nuevoEstado, $idusuario, $observacion);

        // Notificar al cliente por WhatsApp
        $this->notificarCambioEstado($ticket, $nuevoEstado);

        // Emitir evento
        EventService::emit('estado_cambiado', [
            'idticket' => $idticket,
            'codigo_ticket' => $ticket->codigo_ticket,
            'estado_anterior' => $estadoAnterior,
            'estado' => $nuevoEstado,
            'idpersona' => $ticket->idpersona
        ]);

        return ['success' => true, 'estado_anterior' => $estadoAnterior, 'estado_nuevo' => $nuevoEstado];
    }

    /**
     * Validar que la transicion de estado sea permitida
     */
    private function transicionPermitida($estadoActual, $nuevoEstado) {
        $transiciones = [
            'abierto' => ['en_diagnostico', 'en_proceso', 'cancelado'],
            'en_diagnostico' => ['en_proceso', 'esperando_repuestos', 'cancelado'],
            'en_proceso' => ['esperando_repuestos', 'finalizado', 'cancelado'],
            'esperando_repuestos' => ['en_proceso', 'cancelado'],
            'finalizado' => [],
            'cancelado' => ['abierto']
        ];

        $permitidos = $transiciones[$estadoActual] ?? [];
        return in_array($nuevoEstado, $permitidos);
    }

    /**
     * Registrar cambio de estado en historial
     */
    private function registrarHistorialEstado($idticket, $estadoAnterior, $estadoNuevo, $idusuario, $observacion) {
        // Validar estados son valores permitidos
        $validos = ['abierto', 'en_diagnostico', 'en_proceso', 'esperando_repuestos', 'finalizado', 'cancelado'];
        if ($estadoNuevo && !in_array($estadoNuevo, $validos)) return;

        $sql = "INSERT INTO historial_estados_ticket (negocio_id, idticket, estado_anterior, estado_nuevo, idusuario, observacion)
                VALUES (?, ?, ?, ?, ?, ?)";
        ejecutarConsulta($sql, 'iissis', [Tenant::id(), $idticket, $estadoAnterior, $estadoNuevo, $idusuario ? intval($idusuario) : 0, $observacion]);
    }

    /**
     * Estimar costo segun tipo de servicio y prioridad
     */
    public function estimarCosto($tipo_servicio, $prioridad) {
        $tarifas = [
            'remoto' => floatval(getConfigSoporte('costo_hora_remoto') ?: 25000),
            'en_sitio' => floatval(getConfigSoporte('costo_hora_sitio') ?: 40000),
            'taller' => floatval(getConfigSoporte('costo_hora_taller') ?: 30000)
        ];

        $multiplicadores = [
            'baja' => 1.0,
            'media' => 1.0,
            'alta' => 1.3,
            'critica' => 1.5
        ];

        $tarifa = $tarifas[$tipo_servicio] ?? 25000;
        $multiplicador = $multiplicadores[$prioridad] ?? 1.0;
        $horasEstimadas = ($prioridad === 'critica' || $prioridad === 'alta') ? 2 : 1;

        return round($tarifa * $horasEstimadas * $multiplicador, 0);
    }

    /**
     * Calcular costo final de un ticket sumando mantenimientos
     */
    public function calcularCostoFinal($idticket) {
        $query = $this->objMantenimiento->ResumenCostos($idticket);
        $resumen = $query ? $query->fetch_object() : null;

        $costoManoObra = floatval($resumen->costo_total_mano_obra ?? 0);

        // Sumar costo de repuestos de los mantenimientos
        $sql = "SELECT repuestos_usados FROM mantenimientos WHERE idticket = ? AND negocio_id = ? AND repuestos_usados IS NOT NULL";
        $result = ejecutarConsulta($sql, 'ii', [$idticket, Tenant::id()]);

        $costoRepuestos = 0;
        if ($result) {
            while ($row = $result->fetch_object()) {
                $repuestos = json_decode($row->repuestos_usados, true);
                if (is_array($repuestos)) {
                    foreach ($repuestos as $r) {
                        $costoRepuestos += (floatval($r['precio'] ?? 0) * intval($r['cantidad'] ?? 1));
                    }
                }
            }
        }

        $costoTotal = $costoManoObra + $costoRepuestos;

        // Actualizar en el ticket
        $this->objTicket->ActualizarCosto($idticket, null, $costoTotal);

        return [
            'mano_obra' => $costoManoObra,
            'repuestos' => $costoRepuestos,
            'total' => $costoTotal,
            'minutos_totales' => intval($resumen->minutos_totales ?? 0),
            'total_acciones' => intval($resumen->total_acciones ?? 0)
        ];
    }

    /**
     * Convertir ticket finalizado a venta en el sistema base
     */
    public function convertirAVenta($idticket, $idventa) {
        $query = $this->objTicket->ObtenerPorId($idticket);
        $ticket = $query ? $query->fetch_object() : null;

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado'];
        }

        if ($ticket->idventa) {
            return ['success' => false, 'message' => 'Ticket ya tiene venta asociada'];
        }

        $result = $this->objTicket->ConvertirAVenta($idticket, $idventa);
        if ($result) {
            $this->registrarHistorialEstado($idticket, $ticket->estado, 'finalizado', null, "Convertido a venta #$idventa");

            // Notificar cliente
            if (!empty($ticket->tel_cliente)) {
                $this->whatsapp->enviarDesdePlantilla('ticket_finalizado', $ticket->tel_cliente, [
                    'nombre' => $ticket->cliente ?? 'Cliente',
                    'codigo_ticket' => $ticket->codigo_ticket,
                    'moneda' => getConfigSoporte('moneda_simbolo') ?: '$',
                    'costo' => number_format($ticket->costo_final, 0)
                ], $idticket, $ticket->idpersona);
            }

            return ['success' => true];
        }

        return ['success' => false, 'message' => 'Error al convertir a venta'];
    }

    /**
     * Notificar al cliente cuando se crea un ticket desde diagnostico
     */
    public function notificarClienteWhatsApp($telefono, $nombre, $codigoTicket, $urgencia, $resumen) {
        return $this->whatsapp->enviarDesdePlantilla('diagnostico_nuevo', $telefono, [
            'nombre' => $nombre,
            'codigo_ticket' => $codigoTicket,
            'urgencia' => ucfirst($urgencia),
            'resumen' => $resumen
        ]);
    }

    /**
     * Notificar cambio de estado al cliente
     */
    private function notificarCambioEstado($ticket, $nuevoEstado) {
        $telefono = $ticket->tel_cliente ?? '';
        if (empty($telefono)) return;

        $plantillaMap = [
            'en_proceso' => 'ticket_en_proceso',
            'finalizado' => 'ticket_finalizado'
        ];

        $plantilla = $plantillaMap[$nuevoEstado] ?? null;
        if (!$plantilla) return;

        $this->whatsapp->enviarDesdePlantilla($plantilla, $telefono, [
            'nombre' => $ticket->cliente ?? 'Cliente',
            'codigo_ticket' => $ticket->codigo_ticket,
            'tecnico' => $ticket->tecnico ?? 'Por asignar',
            'moneda' => getConfigSoporte('moneda_simbolo') ?: '$',
            'costo' => number_format(floatval($ticket->costo_final ?: $ticket->costo_estimado), 0)
        ], $ticket->idticket, $ticket->idpersona);
    }

    /**
     * Obtener historial de estados de un ticket
     */
    public function obtenerHistorialEstados($idticket) {
        $sql = "SELECT h.*, u.login AS usuario
                FROM historial_estados_ticket h
                LEFT JOIN usuario u ON h.idusuario = u.idusuario
                WHERE h.idticket = ? AND h.negocio_id = ?
                ORDER BY h.fecha_cambio ASC";
        return ejecutarConsulta($sql, 'ii', [$idticket, Tenant::id()]);
    }

    /**
     * Dashboard: estadisticas generales del sistema de soporte
     */
    public function obtenerDashboard() {
        $nid = Tenant::id();

        // Tickets
        $sqlTickets = "SELECT
            COUNT(*) as total_tickets,
            SUM(CASE WHEN estado IN ('abierto','en_diagnostico','en_proceso','esperando_repuestos') THEN 1 ELSE 0 END) as tickets_activos,
            SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as tickets_cerrados,
            SUM(CASE WHEN prioridad IN ('alta','critica') AND estado NOT IN ('finalizado','cancelado') THEN 1 ELSE 0 END) as tickets_urgentes,
            SUM(costo_final) as ingresos_total,
            AVG(CASE WHEN fecha_cierre IS NOT NULL THEN TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_cierre) END) as tiempo_promedio_horas
        FROM tickets WHERE negocio_id = ? AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

        // Diagnosticos recientes
        $sqlDiag = "SELECT COUNT(*) as total_diagnosticos,
            SUM(CASE WHEN nivel_urgencia IN ('alta','critica') THEN 1 ELSE 0 END) as diagnosticos_urgentes
        FROM diagnosticos WHERE negocio_id = ? AND fecha_diagnostico >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

        // Top tecnicos
        $sqlTecnicos = "SELECT u.login as tecnico, COUNT(m.idmantenimiento) as acciones, SUM(m.duracion_minutos) as minutos
            FROM mantenimientos m
            INNER JOIN usuario u ON m.idusuario = u.idusuario
            WHERE m.negocio_id = ? AND m.fecha_accion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY m.idusuario ORDER BY acciones DESC LIMIT 5";

        $tickets = ejecutarConsulta($sqlTickets, 'i', [$nid]);
        $diagnosticos = ejecutarConsulta($sqlDiag, 'i', [$nid]);
        $tecnicos = ejecutarConsulta($sqlTecnicos, 'i', [$nid]);

        $data = [
            'tickets' => $tickets ? $tickets->fetch_object() : null,
            'diagnosticos' => $diagnosticos ? $diagnosticos->fetch_object() : null,
            'tecnicos' => []
        ];

        if ($tecnicos) {
            while ($row = $tecnicos->fetch_object()) {
                $data['tecnicos'][] = $row;
            }
        }

        return $data;
    }
}
