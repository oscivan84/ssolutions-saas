<?php
/**
 * Modelo: Ticket
 * Gestión de tickets de soporte técnico
 * Compatible con el patrón del sistema base (SSolutions)
 */

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/tenant.php";

class Ticket {

    public function __construct() {}

    /**
     * Generar código de ticket único
     */
    private function GenerarCodigo() {
        $prefix = Tenant::config('ticket_prefix', 'TKT');
        // uniqid con entropy=true + random bytes para evitar colisiones en alta concurrencia
        $unique = strtoupper(substr(md5(uniqid('', true) . bin2hex(random_bytes(4))), 0, 7));
        return $prefix . '-' . date('Ymd') . '-' . $unique;
    }

    /**
     * Crear ticket automáticamente desde un diagnóstico
     */
    public function CrearDesdeDiagnostico($iddiagnostico, $idpersona, $titulo, $descripcion, $prioridad, $tipo_servicio = 'remoto') {
        $codigo = $this->GenerarCodigo();
        $sql = "INSERT INTO tickets (
            negocio_id, iddiagnostico, idpersona, codigo_ticket, titulo, descripcion,
            tipo_servicio, estado, prioridad
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'abierto', ?)";

        $result = ejecutarConsulta($sql, 'iiisssss', [
            Tenant::id(),
            $iddiagnostico, $idpersona, $codigo, $titulo, $descripcion,
            $tipo_servicio, $prioridad
        ]);

        if ($result) {
            return ['success' => true, 'idticket' => ultimoId(), 'codigo' => $codigo];
        }
        return ['success' => false];
    }

    /**
     * Crear ticket manual
     */
    public function Registrar($idpersona, $idusuario, $titulo, $descripcion, $tipo_servicio, $prioridad, $costo_estimado) {
        $codigo = $this->GenerarCodigo();
        $sql = "INSERT INTO tickets (
            negocio_id, idpersona, idusuario, codigo_ticket, titulo, descripcion,
            tipo_servicio, prioridad, costo_estimado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $result = ejecutarConsulta($sql, 'iiisssssd', [
            Tenant::id(),
            $idpersona, $idusuario, $codigo, $titulo, $descripcion,
            $tipo_servicio, $prioridad, $costo_estimado
        ]);

        if ($result) {
            return ['success' => true, 'idticket' => ultimoId(), 'codigo' => $codigo];
        }
        return ['success' => false];
    }

    /**
     * Listar tickets del negocio con filtros
     */
    public function Listar($estado = null) {
        $sql = "SELECT t.*, p.nombre AS cliente, u.login AS tecnico,
                       d.hostname, d.nivel_urgencia AS urgencia_diagnostico
                FROM tickets t
                LEFT JOIN persona p ON t.idpersona = p.idpersona
                LEFT JOIN usuario u ON t.idusuario = u.idusuario
                LEFT JOIN diagnosticos d ON t.iddiagnostico = d.iddiagnostico
                WHERE t.negocio_id = ?";

        if ($estado) {
            $sql .= " AND t.estado = ?";
            $sql .= " ORDER BY t.fecha_creacion DESC";
            return ejecutarConsulta($sql, 'is', [Tenant::id(), $estado]);
        }

        $sql .= " ORDER BY t.fecha_creacion DESC";
        return ejecutarConsulta($sql, 'i', [Tenant::id()]);
    }

    /**
     * Obtener ticket por ID con detalle completo (con validación de tenant)
     */
    public function ObtenerPorId($idticket) {
        $sql = "SELECT t.*, p.nombre AS cliente, p.telefono AS tel_cliente, p.email AS email_cliente,
                       u.login AS tecnico,
                       d.hostname, d.sistema_operativo, d.reporte_completo, d.resumen_ia
                FROM tickets t
                LEFT JOIN persona p ON t.idpersona = p.idpersona
                LEFT JOIN usuario u ON t.idusuario = u.idusuario
                LEFT JOIN diagnosticos d ON t.iddiagnostico = d.iddiagnostico
                WHERE t.idticket = ? AND t.negocio_id = ?";
        return ejecutarConsulta($sql, 'ii', [$idticket, Tenant::id()]);
    }

    /**
     * Obtener ticket por código (con validación de tenant)
     */
    public function ObtenerPorCodigo($codigo) {
        $sql = "SELECT t.*, p.nombre AS cliente, p.telefono AS tel_cliente
                FROM tickets t
                LEFT JOIN persona p ON t.idpersona = p.idpersona
                WHERE t.codigo_ticket = ? AND t.negocio_id = ?";
        return ejecutarConsulta($sql, 'si', [$codigo, Tenant::id()]);
    }

    /**
     * Actualizar estado del ticket
     */
    public function CambiarEstado($idticket, $estado) {
        $sql = "UPDATE tickets SET estado = ?";
        if ($estado === 'finalizado') {
            $sql .= ", fecha_cierre = NOW()";
        }
        $sql .= " WHERE idticket = ? AND negocio_id = ?";
        return ejecutarConsulta($sql, 'sii', [$estado, $idticket, Tenant::id()]);
    }

    /**
     * Asignar técnico
     */
    public function AsignarTecnico($idticket, $idusuario) {
        $sql = "UPDATE tickets SET idusuario = ? WHERE idticket = ? AND negocio_id = ?";
        return ejecutarConsulta($sql, 'iii', [$idusuario, $idticket, Tenant::id()]);
    }

    /**
     * Actualizar costos
     */
    public function ActualizarCosto($idticket, $costo_estimado, $costo_final) {
        $sql = "UPDATE tickets SET costo_estimado = ?, costo_final = ? WHERE idticket = ? AND negocio_id = ?";
        return ejecutarConsulta($sql, 'ddii', [$costo_estimado, $costo_final, $idticket, Tenant::id()]);
    }

    /**
     * Convertir ticket a venta
     */
    public function ConvertirAVenta($idticket, $idventa) {
        $sql = "UPDATE tickets SET idventa = ?, estado = 'finalizado', fecha_cierre = NOW() WHERE idticket = ? AND negocio_id = ?";
        return ejecutarConsulta($sql, 'iii', [$idventa, $idticket, Tenant::id()]);
    }

    /**
     * Agregar notas al ticket
     */
    public function AgregarNota($idticket, $nota) {
        $sql = "UPDATE tickets SET notas = CONCAT(IFNULL(notas, ''), '\n[', NOW(), '] ', ?) WHERE idticket = ? AND negocio_id = ?";
        return ejecutarConsulta($sql, 'sii', [$nota, $idticket, Tenant::id()]);
    }

    /**
     * Estadísticas de tickets del negocio
     */
    public function Estadisticas() {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'abierto' THEN 1 ELSE 0 END) as abiertos,
                    SUM(CASE WHEN estado IN ('en_diagnostico','en_proceso','esperando_repuestos') THEN 1 ELSE 0 END) as en_progreso,
                    SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
                    SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
                    SUM(CASE WHEN idventa IS NOT NULL THEN 1 ELSE 0 END) as convertidos_venta,
                    AVG(CASE WHEN fecha_cierre IS NOT NULL THEN TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_cierre) END) as promedio_horas_resolucion,
                    SUM(costo_final) as ingresos_total
                FROM tickets
                WHERE negocio_id = ? AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        return ejecutarConsulta($sql, 'i', [Tenant::id()]);
    }
}
