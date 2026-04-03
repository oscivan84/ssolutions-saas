<?php
/**
 * AJAX Handler: Mensajes WhatsApp
 * Sigue el patron de SSolutions (switch $_GET["op"])
 */

session_start();
require_once "../model/MensajeWhatsApp.php";
require_once "../services/WhatsAppService.php";

$objMensaje = new MensajeWhatsApp();
$whatsapp = new WhatsAppService();

switch ($_GET["op"]) {

    case "enviarMensaje":
        $telefono = $_POST["telefono"];
        $mensaje = $_POST["mensaje"];
        $idticket = intval($_POST["idticket"] ?? 0) ?: null;
        $idpersona = intval($_POST["idpersona"] ?? 0) ?: null;

        if ($whatsapp->enviarTexto($telefono, $mensaje, $idticket, $idpersona)) {
            echo json_encode(["status" => "success", "message" => "Mensaje enviado"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Error al enviar. Verifique configuracion de WhatsApp."]);
        }
        break;

    case "enviarPlantilla":
        $nombre_plantilla = $_POST["plantilla"];
        $telefono = $_POST["telefono"];
        $variables = json_decode($_POST["variables"] ?? '{}', true) ?: [];
        $idticket = intval($_POST["idticket"] ?? 0) ?: null;
        $idpersona = intval($_POST["idpersona"] ?? 0) ?: null;

        if ($whatsapp->enviarDesdePlantilla($nombre_plantilla, $telefono, $variables, $idticket, $idpersona)) {
            echo json_encode(["status" => "success", "message" => "Plantilla enviada"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Error al enviar plantilla"]);
        }
        break;

    case "listPorTicket":
        $idticket = intval($_GET["idticket"]);
        $query = $objMensaje->ListarPorTicket($idticket);
        $data = [];
        if ($query) {
            while ($reg = $query->fetch_object()) {
                $data[] = [
                    "fecha" => $reg->fecha,
                    "direccion" => $reg->direccion,
                    "tipo" => $reg->tipo,
                    "contenido" => $reg->contenido,
                    "estado" => $reg->estado,
                    "telefono" => $reg->telefono
                ];
            }
        }
        echo json_encode($data);
        break;

    case "plantillas":
        require_once "../config/tenant.php";
        $sql = "SELECT * FROM plantillas_mensaje WHERE negocio_id = ? AND activo = 1 ORDER BY nombre";
        $result = ejecutarConsulta($sql, 'i', [Tenant::id()]);
        $data = [];
        if ($result) {
            while ($reg = $result->fetch_object()) {
                $data[] = $reg;
            }
        }
        echo json_encode($data);
        break;

    case "configuracion":
        $estado = $whatsapp->estaConfigurado();
        echo json_encode([
            "configurado" => $estado,
            "phone_id" => $estado ? substr(WHATSAPP_PHONE_ID, 0, 5) . '...' : ''
        ]);
        break;
}
