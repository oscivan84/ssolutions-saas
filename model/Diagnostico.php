<?php
/**
 * Modelo: Diagnostico
 * Gestiona diagnósticos de PC enviados por el agente Python
 * Compatible con el patrón del sistema base (SSolutions)
 */

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/tenant.php";

class Diagnostico {

    public function __construct() {}

    /**
     * Registrar nuevo diagnóstico desde el agente
     */
    public function Registrar(
        $idpersona, $nombre_cliente, $telefono_cliente, $email_cliente,
        $hostname, $sistema_operativo, $cpu_modelo, $cpu_uso, $cpu_nucleos,
        $ram_total, $ram_usada, $ram_uso, $disco_total, $disco_usado, $disco_uso,
        $temperatura_cpu, $procesos_activos, $problemas, $recomendaciones,
        $optimizaciones, $reporte_completo, $nivel_urgencia
    ) {
        $sql = "INSERT INTO diagnosticos (
            negocio_id, idpersona, nombre_cliente, telefono_cliente, email_cliente,
            hostname, sistema_operativo, cpu_modelo, cpu_uso_porcentaje, cpu_nucleos,
            ram_total_gb, ram_usada_gb, ram_uso_porcentaje,
            disco_total_gb, disco_usado_gb, disco_uso_porcentaje,
            temperatura_cpu, procesos_activos, problemas_detectados, recomendaciones,
            optimizaciones_realizadas, reporte_completo, nivel_urgencia
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        // Types: i=negocio, i=persona, s=nombre, s=tel, s=email, s=host, s=os, s=cpu_mod,
        //        d=cpu_uso, i=cpu_nucleos, d=ram_tot, d=ram_usa, d=ram_uso,
        //        d=disco_tot, d=disco_usa, d=disco_uso, d=temp, i=procesos,
        //        s=problemas, s=recomendaciones, s=optimizaciones, s=reporte, s=nivel
        return ejecutarConsulta($sql, 'iissssssdidddddddisssss', [
            Tenant::id(),
            $idpersona, $nombre_cliente, $telefono_cliente, $email_cliente,
            $hostname, $sistema_operativo, $cpu_modelo, $cpu_uso, $cpu_nucleos,
            $ram_total, $ram_usada, $ram_uso, $disco_total, $disco_usado, $disco_uso,
            $temperatura_cpu, $procesos_activos, $problemas, $recomendaciones,
            $optimizaciones, $reporte_completo, $nivel_urgencia
        ]);
    }

    /**
     * Listar todos los diagnósticos del negocio
     */
    public function Listar() {
        $sql = "SELECT d.*, p.nombre AS cliente_registrado
                FROM diagnosticos d
                LEFT JOIN persona p ON d.idpersona = p.idpersona
                WHERE d.negocio_id = ?
                ORDER BY d.fecha_diagnostico DESC";
        return ejecutarConsulta($sql, 'i', [Tenant::id()]);
    }

    /**
     * Obtener diagnóstico por ID (con validación de tenant)
     */
    public function ObtenerPorId($iddiagnostico) {
        $sql = "SELECT d.*, p.nombre AS cliente_registrado, p.telefono AS tel_registrado
                FROM diagnosticos d
                LEFT JOIN persona p ON d.idpersona = p.idpersona
                WHERE d.iddiagnostico = ? AND d.negocio_id = ?";
        return ejecutarConsulta($sql, 'ii', [$iddiagnostico, Tenant::id()]);
    }

    /**
     * Obtener diagnósticos de un cliente
     */
    public function ListarPorCliente($idpersona) {
        $sql = "SELECT * FROM diagnosticos WHERE idpersona = ? AND negocio_id = ? ORDER BY fecha_diagnostico DESC";
        return ejecutarConsulta($sql, 'ii', [$idpersona, Tenant::id()]);
    }

    /**
     * Actualizar resumen de IA
     */
    public function ActualizarResumenIA($iddiagnostico, $resumen) {
        $sql = "UPDATE diagnosticos SET resumen_ia = ? WHERE iddiagnostico = ? AND negocio_id = ?";
        return ejecutarConsulta($sql, 'sii', [$resumen, $iddiagnostico, Tenant::id()]);
    }

    /**
     * Buscar cliente existente por teléfono o email
     * Prioriza clientes que ya tienen relación con este negocio
     */
    public function BuscarCliente($telefono, $email) {
        // Primero buscar entre clientes que ya tienen interacción con este negocio
        $sql = "SELECT p.idpersona, p.nombre, p.telefono, p.email
                FROM persona p
                WHERE (p.telefono = ? OR p.email = ?)
                AND (
                    EXISTS (SELECT 1 FROM tickets t WHERE t.idpersona = p.idpersona AND t.negocio_id = ?)
                    OR EXISTS (SELECT 1 FROM diagnosticos d WHERE d.idpersona = p.idpersona AND d.negocio_id = ?)
                )
                LIMIT 1";
        $result = ejecutarConsulta($sql, 'ssii', [$telefono, $email, Tenant::id(), Tenant::id()]);
        if ($result && $result->num_rows > 0) {
            return $result;
        }

        // Si no tiene relación previa, buscar en tabla persona general
        $sql = "SELECT idpersona, nombre, telefono, email
                FROM persona
                WHERE telefono = ? OR email = ?
                LIMIT 1";
        return ejecutarConsulta($sql, 'ss', [$telefono, $email]);
    }

    /**
     * Estadísticas de diagnósticos del negocio
     */
    public function Estadisticas() {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN nivel_urgencia = 'critica' THEN 1 ELSE 0 END) as criticos,
                    SUM(CASE WHEN nivel_urgencia = 'alta' THEN 1 ELSE 0 END) as altos,
                    SUM(CASE WHEN nivel_urgencia = 'media' THEN 1 ELSE 0 END) as medios,
                    SUM(CASE WHEN nivel_urgencia = 'baja' THEN 1 ELSE 0 END) as bajos,
                    AVG(cpu_uso_porcentaje) as avg_cpu,
                    AVG(ram_uso_porcentaje) as avg_ram,
                    AVG(disco_uso_porcentaje) as avg_disco
                FROM diagnosticos
                WHERE negocio_id = ? AND fecha_diagnostico >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        return ejecutarConsulta($sql, 'i', [Tenant::id()]);
    }
}
