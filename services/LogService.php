<?php
/**
 * Log centralizado del sistema
 * Reemplaza error_log() disperso con un log estructurado en BD.
 *
 * Uso:
 *   LogService::info('whatsapp', 'Mensaje enviado', ['telefono' => '57300...']);
 *   LogService::error('ia_service', 'Timeout', ['endpoint' => '/responder']);
 */

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/tenant.php";

class LogService {

    public static function info($origen, $mensaje, $contexto = []) {
        self::log('info', $origen, $mensaje, $contexto);
    }

    public static function warning($origen, $mensaje, $contexto = []) {
        self::log('warning', $origen, $mensaje, $contexto);
    }

    public static function error($origen, $mensaje, $contexto = []) {
        self::log('error', $origen, $mensaje, $contexto);
    }

    public static function critical($origen, $mensaje, $contexto = []) {
        self::log('critical', $origen, $mensaje, $contexto);
    }

    private static function log($nivel, $origen, $mensaje, $contexto) {
        $negocioId = null;
        try { $negocioId = Tenant::id(); } catch (\Exception $e) {}

        $sql = "INSERT INTO log_sistema (negocio_id, nivel, origen, mensaje, contexto) VALUES (?, ?, ?, ?, ?)";
        ejecutarConsulta($sql, 'issss', [
            $negocioId, $nivel, $origen, $mensaje, json_encode($contexto)
        ]);

        // También al error_log para acceso por archivo
        error_log("[{$nivel}][{$origen}] {$mensaje}");
    }
}
