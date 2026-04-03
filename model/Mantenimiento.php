<?php
/**
 * Modelo: Mantenimiento
 * Historial de acciones realizadas en tickets de soporte
 * Compatible con el patrón del sistema base (SSolutions)
 */

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/tenant.php";

class Mantenimiento {

    public function __construct() {}

    /**
     * Registrar acción de mantenimiento
     */
    public function Registrar($idticket, $idusuario, $tipo_accion, $descripcion, $repuestos_usados, $duracion_minutos, $costo) {
        global $conexion;

        $conexion->autocommit(false);
        $sw = true;

        try {
            // 1. Insertar registro de mantenimiento
            $sql = "INSERT INTO mantenimientos (negocio_id, idticket, idusuario, tipo_accion, descripcion, repuestos_usados, duracion_minutos, costo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $result = ejecutarConsulta($sql, 'iiisssid', [Tenant::id(), $idticket, $idusuario, $tipo_accion, $descripcion, $repuestos_usados, $duracion_minutos, $costo]);
            if (!$result) $sw = false;

            // 2. Si hay repuestos usados, descontar del inventario (tabla articulo)
            if ($sw && !empty($repuestos_usados)) {
                $repuestos = json_decode($repuestos_usados, true);
                if (is_array($repuestos)) {
                    foreach ($repuestos as $repuesto) {
                        $sql_stock = "UPDATE articulo SET stock_actual = stock_actual - ? WHERE idarticulo = ? AND stock_actual >= ?";
                        $result_stock = ejecutarConsulta($sql_stock, 'iii', [
                            $repuesto['cantidad'],
                            $repuesto['idarticulo'],
                            $repuesto['cantidad']
                        ]);
                        if (!$result_stock || $conexion->affected_rows === 0) {
                            $sw = false;
                            break;
                        }
                    }
                }
            }

            // 3. Actualizar estado del ticket a "en_proceso"
            if ($sw) {
                $sql_ticket = "UPDATE tickets SET estado = 'en_proceso' WHERE idticket = ? AND negocio_id = ? AND estado IN ('abierto', 'en_diagnostico')";
                ejecutarConsulta($sql_ticket, 'ii', [$idticket, Tenant::id()]);
            }

            if ($sw) {
                $conexion->commit();
            } else {
                $conexion->rollback();
            }
        } catch (Exception $e) {
            $conexion->rollback();
            $sw = false;
            error_log("Error en Mantenimiento::Registrar: " . $e->getMessage());
        }

        $conexion->autocommit(true);
        return $sw;
    }

    /**
     * Listar acciones de un ticket
     */
    public function ListarPorTicket($idticket) {
        $sql = "SELECT m.*, u.login AS tecnico
                FROM mantenimientos m
                LEFT JOIN usuario u ON m.idusuario = u.idusuario
                WHERE m.idticket = ? AND m.negocio_id = ?
                ORDER BY m.fecha_accion DESC";
        return ejecutarConsulta($sql, 'ii', [$idticket, Tenant::id()]);
    }

    /**
     * Obtener resumen de costos de un ticket
     */
    public function ResumenCostos($idticket) {
        $sql = "SELECT
                    SUM(costo) as costo_total_mano_obra,
                    SUM(duracion_minutos) as minutos_totales,
                    COUNT(*) as total_acciones
                FROM mantenimientos
                WHERE idticket = ? AND negocio_id = ?";
        return ejecutarConsulta($sql, 'ii', [$idticket, Tenant::id()]);
    }

    /**
     * Listar acciones recientes de un técnico
     */
    public function ListarPorTecnico($idusuario, $limite = 20) {
        $sql = "SELECT m.*, t.codigo_ticket, t.titulo AS titulo_ticket, p.nombre AS cliente
                FROM mantenimientos m
                INNER JOIN tickets t ON m.idticket = t.idticket
                LEFT JOIN persona p ON t.idpersona = p.idpersona
                WHERE m.idusuario = ? AND m.negocio_id = ?
                ORDER BY m.fecha_accion DESC
                LIMIT ?";
        return ejecutarConsulta($sql, 'iii', [$idusuario, Tenant::id(), $limite]);
    }
}
