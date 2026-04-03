<?php
/**
 * Modelo: MensajeWhatsApp
 * Registro de mensajes enviados/recibidos por WhatsApp
 */

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/tenant.php";

class MensajeWhatsApp {

    public function __construct() {}

    public function Registrar($idticket, $idpersona, $telefono, $direccion, $tipo, $contenido, $message_id, $estado) {
        $sql = "INSERT INTO mensajes_whatsapp (negocio_id, idticket, idpersona, telefono, direccion, tipo, contenido, message_id, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        return ejecutarConsulta($sql, 'iiissssss', [Tenant::id(), $idticket, $idpersona, $telefono, $direccion, $tipo, $contenido, $message_id, $estado]);
    }

    public function ListarPorTicket($idticket) {
        $sql = "SELECT * FROM mensajes_whatsapp WHERE idticket = ? AND negocio_id = ? ORDER BY fecha ASC";
        return ejecutarConsulta($sql, 'ii', [$idticket, Tenant::id()]);
    }

    public function ActualizarEstado($message_id, $estado) {
        $sql = "UPDATE mensajes_whatsapp SET estado = ? WHERE message_id = ? AND negocio_id = ?";
        return ejecutarConsulta($sql, 'ssi', [$estado, $message_id, Tenant::id()]);
    }
}
