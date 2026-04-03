<?php
/**
 * Audit Log — registra operaciones sensibles
 *
 * Uso:
 *   AuditService::log('config_update', 'configuracion_soporte', $idconfig, $antes, $despues);
 *   AuditService::log('ticket_delete', 'tickets', $idticket);
 */

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/tenant.php";

class AuditService {

    public static function log($accion, $entidad = null, $entidadId = null, $antes = null, $despues = null) {
        $idusuario = intval($_SESSION['idusuario'] ?? 0);
        $ip = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown';

        try {
            $sql = "INSERT INTO audit_log (negocio_id, idusuario, accion, entidad, entidad_id, datos_antes, datos_despues, ip)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            ejecutarConsulta($sql, 'iississs', [
                Tenant::id(),
                $idusuario,
                $accion,
                $entidad ?: '',
                $entidadId ? intval($entidadId) : 0,
                $antes ? json_encode($antes) : '{}',
                $despues ? json_encode($despues) : '{}',
                $ip
            ]);
        } catch (\Exception $e) {
            error_log("AuditService error: " . $e->getMessage());
        }
    }
}
