<?php
/**
 * Sistema de Notificaciones en tiempo real
 *
 * Crea notificaciones visibles en el panel admin.
 * El frontend las consulta via polling (AJAX cada 30s).
 *
 * Uso:
 *   NotificacionService::crear('cliente_respondio', 'Carlos respondio!', 'Ver conversacion', 'soporte_tickets');
 *   NotificacionService::crear('cliente_caliente', 'Cliente caliente detectado', '...', null, $idpersona);
 */

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/tenant.php";

class NotificacionService {

    /**
     * Crear notificación
     */
    public static function crear($tipo, $titulo, $mensaje, $urlAccion = null, $referenciaId = null, $idusuario = null) {
        $iconos = [
            'cliente_respondio' => ['whatsapp', 'success'],
            'cliente_caliente' => ['fire', 'danger'],
            'ticket_estancado' => ['clock-o', 'warning'],
            'ticket_nuevo' => ['ticket', 'info'],
            'venta_cerrada' => ['dollar', 'success'],
            'alerta' => ['exclamation-triangle', 'warning'],
            'sistema' => ['cog', 'default'],
        ];

        $config = $iconos[$tipo] ?? ['bell', 'info'];

        $sql = "INSERT INTO notificaciones (negocio_id, idusuario, tipo, titulo, mensaje, icono, color, url_accion, referencia_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        ejecutarConsulta($sql, 'iissssssi', [
            Tenant::id(), $idusuario, $tipo, $titulo, $mensaje,
            $config[0], $config[1], $urlAccion, $referenciaId
        ]);

        return ultimoId();
    }

    /**
     * Obtener notificaciones no leídas del usuario/negocio
     */
    public static function noLeidas($idusuario = null, $limit = 10) {
        $sql = "SELECT * FROM notificaciones
                WHERE negocio_id = ? AND leida = 0
                AND (idusuario IS NULL OR idusuario = ?)
                ORDER BY fecha DESC LIMIT ?";
        $uid = $idusuario ?: ($_SESSION['idusuario'] ?? 0);
        return ejecutarConsulta($sql, 'iii', [Tenant::id(), $uid, $limit]);
    }

    /**
     * Contar notificaciones no leídas
     */
    public static function contarNoLeidas($idusuario = null) {
        $sql = "SELECT COUNT(*) as total FROM notificaciones
                WHERE negocio_id = ? AND leida = 0
                AND (idusuario IS NULL OR idusuario = ?)";
        $uid = $idusuario ?: ($_SESSION['idusuario'] ?? 0);
        $result = ejecutarConsulta($sql, 'ii', [Tenant::id(), $uid]);
        return ($result && $row = $result->fetch_object()) ? intval($row->total) : 0;
    }

    /**
     * Marcar como leída
     */
    public static function marcarLeida($idnotificacion) {
        $sql = "UPDATE notificaciones SET leida = 1 WHERE idnotificacion = ? AND negocio_id = ?";
        return ejecutarConsulta($sql, 'ii', [$idnotificacion, Tenant::id()]);
    }

    /**
     * Marcar todas como leídas
     */
    public static function marcarTodasLeidas($idusuario = null) {
        $uid = $idusuario ?: ($_SESSION['idusuario'] ?? 0);
        $sql = "UPDATE notificaciones SET leida = 1
                WHERE negocio_id = ? AND leida = 0
                AND (idusuario IS NULL OR idusuario = ?)";
        return ejecutarConsulta($sql, 'ii', [Tenant::id(), $uid]);
    }
}
