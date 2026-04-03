<?php
/**
 * Cola de Trabajos Simple (tabla jobs)
 *
 * Crea jobs desde PHP, los procesa un worker (cron o daemon).
 * Sin Redis, sin dependencias — solo MySQL.
 *
 * Uso:
 *   JobQueue::crear('enviar_whatsapp', ['telefono' => '...', 'mensaje' => '...']);
 *   JobQueue::crear('scoring', ['idpersona' => 5], 'alta', 60); // en 60 min
 */

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/tenant.php";

class JobQueue {

    /**
     * Crear un nuevo job
     *
     * @param string $tipo Tipo de job (enviar_whatsapp, scoring, alerta, etc.)
     * @param array $payload Datos del job
     * @param string $prioridad alta|media|baja
     * @param int $delayMinutos 0 = ahora, >0 = programar para el futuro
     * @return int ID del job creado
     */
    public static function crear($tipo, $payload = [], $prioridad = 'media', $delayMinutos = 0, $maxIntentos = null) {
        $programado = null;
        if ($delayMinutos > 0) {
            $programado = date('Y-m-d H:i:s', strtotime("+{$delayMinutos} minutes"));
        }

        // Jobs de WhatsApp necesitan más reintentos (outages de Meta duran 30-60 min)
        if ($maxIntentos === null) {
            $maxIntentos = ($tipo === 'enviar_whatsapp') ? 5 : 3;
        }

        $sql = "INSERT INTO jobs (negocio_id, tipo, payload, prioridad, programado_para, max_intentos)
                VALUES (?, ?, ?, ?, ?, ?)";
        ejecutarConsulta($sql, 'issssi', [
            Tenant::id(), $tipo, json_encode($payload), $prioridad, $programado, $maxIntentos
        ]);

        return ultimoId();
    }

    /**
     * Obtener siguiente job pendiente para procesar
     * Usa SELECT FOR UPDATE para evitar race conditions
     */
    public static function siguiente() {
        global $conexion;

        // Recuperar jobs stuck en 'procesando' por más de 10 minutos (worker crash)
        $conexion->query("UPDATE jobs SET estado = 'pendiente'
            WHERE estado = 'procesando' AND fecha_inicio < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");

        // Atomic claim con transacción explícita (sin user variables)
        $conexion->autocommit(false);

        try {
            $sql = "SELECT idjob FROM jobs
                    WHERE estado = 'pendiente'
                    AND (programado_para IS NULL OR programado_para <= NOW())
                    AND intentos < max_intentos
                    ORDER BY FIELD(prioridad, 'alta', 'media', 'baja'), fecha_creacion ASC
                    LIMIT 1
                    FOR UPDATE SKIP LOCKED";
            $result = $conexion->query($sql);
            $row = $result ? $result->fetch_object() : null;

            if (!$row) {
                $conexion->rollback();
                $conexion->autocommit(true);
                return null;
            }

            $conexion->query("UPDATE jobs SET estado = 'procesando', intentos = intentos + 1, fecha_inicio = NOW()
                              WHERE idjob = {$row->idjob}");
            $conexion->commit();
            $conexion->autocommit(true);

            // Cargar job completo
            $result = ejecutarConsulta("SELECT * FROM jobs WHERE idjob = ?", 'i', [$row->idjob]);
            return $result ? $result->fetch_object() : null;

        } catch (\Exception $e) {
            $conexion->rollback();
            $conexion->autocommit(true);
            error_log("JobQueue::siguiente error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Marcar job como completado
     */
    public static function completar($idjob, $resultado = null) {
        $sql = "UPDATE jobs SET estado = 'completado', resultado = ?, fecha_fin = NOW() WHERE idjob = ?";
        ejecutarConsulta($sql, 'si', [json_encode($resultado), $idjob]);
    }

    /**
     * Marcar job como fallido (con retry exponencial: 1m, 5m, 15m)
     */
    public static function fallar($idjob, $error) {
        // Obtener intentos actuales para calcular backoff
        $sql = "SELECT intentos, max_intentos FROM jobs WHERE idjob = ?";
        $result = ejecutarConsulta($sql, 'i', [$idjob]);
        $job = $result ? $result->fetch_object() : null;

        if ($job && $job->intentos < $job->max_intentos) {
            // Retry exponencial: 1min, 5min, 15min
            $delays = [1, 5, 15, 30, 60];
            $delay = $delays[min($job->intentos - 1, count($delays) - 1)];
            $programado = date('Y-m-d H:i:s', strtotime("+{$delay} minutes"));

            $sql = "UPDATE jobs SET estado = 'pendiente', error = ?, programado_para = ? WHERE idjob = ?";
            ejecutarConsulta($sql, 'ssi', [$error, $programado, $idjob]);
        } else {
            $sql = "UPDATE jobs SET estado = 'fallido', error = ?, fecha_fin = NOW() WHERE idjob = ?";
            ejecutarConsulta($sql, 'si', [$error, $idjob]);
        }
    }

    /**
     * Estadísticas de la cola
     */
    public static function stats() {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN estado = 'procesando' THEN 1 ELSE 0 END) as procesando,
                    SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
                    SUM(CASE WHEN estado = 'fallido' THEN 1 ELSE 0 END) as fallidos
                FROM jobs WHERE negocio_id = ?";
        return ejecutarConsulta($sql, 'i', [Tenant::id()]);
    }
}
