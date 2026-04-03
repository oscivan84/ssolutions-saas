<?php
/**
 * AJAX Handler: Tickets de Soporte
 * Sigue el patrón de SSolutions (switch $_GET["op"])
 */

session_start();
require_once "../config/tenant.php";
require_once "../model/Ticket.php";

$objTicket = new Ticket();
$op = $_GET["op"] ?? '';

// Auth para operaciones de escritura
if (in_array($op, ['SaveOrUpdate', 'cambiarEstado', 'asignarTecnico', 'convertirVenta'])) {
    Tenant::requireAuth('tecnico');
}

switch ($op) {

    case "SaveOrUpdate":
        $idpersona = intval($_POST["idpersona"]);
        $idusuario = isset($_POST["idusuario"]) ? intval($_POST["idusuario"]) : $_SESSION["idusuario"] ?? null;
        $titulo = $_POST["titulo"];
        $descripcion = $_POST["descripcion"];
        $tipo_servicio = $_POST["tipo_servicio"];
        $prioridad = $_POST["prioridad"];
        $costo_estimado = floatval($_POST["costo_estimado"] ?? 0);

        if (empty($_POST["idticket"])) {
            $result = $objTicket->Registrar($idpersona, $idusuario, $titulo, $descripcion, $tipo_servicio, $prioridad, $costo_estimado);
            if ($result['success']) {
                echo json_encode(["message" => "Ticket creado: " . $result['codigo'], "codigo" => $result['codigo'], "idticket" => $result['idticket']]);
            } else {
                echo json_encode(["message" => "Error al crear ticket"]);
            }
        }
        break;

    case "list":
        $estado = $_GET["estado"] ?? null;
        $query = $objTicket->Listar($estado);
        $data = [];
        $i = 1;
        while ($reg = $query->fetch_object()) {
            $data[] = [
                "0" => $i,
                "1" => $reg->codigo_ticket,
                "2" => $reg->titulo,
                "3" => $reg->cliente ?? 'Sin asignar',
                "4" => $reg->tecnico ?? 'Sin asignar',
                "5" => '<span class="label label-' . estadoTicketLabel($reg->estado) . '">' . formatearEstado($reg->estado) . '</span>',
                "6" => $reg->tipo_servicio,
                "7" => '$' . number_format($reg->costo_estimado, 0),
                "8" => $reg->fecha_creacion,
                "9" => '<div class="btn-group">' .
                       '<button class="btn btn-info btn-xs" onclick="verTicket(' . $reg->idticket . ')"><i class="fa fa-eye"></i></button> ' .
                       '<button class="btn btn-warning btn-xs" onclick="editarTicket(' . $reg->idticket . ')"><i class="fa fa-edit"></i></button>' .
                       '</div>'
            ];
            $i++;
        }
        echo json_encode([
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        ]);
        break;

    case "get":
        $id = intval($_GET["id"]);
        $query = $objTicket->ObtenerPorId($id);
        if ($reg = $query->fetch_object()) {
            echo json_encode($reg);
        } else {
            echo json_encode(["error" => "Ticket no encontrado"]);
        }
        break;

    case "getPorCodigo":
        $codigo = $_GET["codigo"];
        $query = $objTicket->ObtenerPorCodigo($codigo);
        if ($reg = $query->fetch_object()) {
            echo json_encode($reg);
        } else {
            echo json_encode(["error" => "Ticket no encontrado"]);
        }
        break;

    case "cambiarEstado":
        $idticket = intval($_POST["idticket"]);
        $estado = $_POST["estado"];
        if ($objTicket->CambiarEstado($idticket, $estado)) {
            echo "Estado actualizado";
        } else {
            echo "Error al actualizar";
        }
        break;

    case "asignarTecnico":
        $idticket = intval($_POST["idticket"]);
        $idusuario = intval($_POST["idusuario"]);
        if ($objTicket->AsignarTecnico($idticket, $idusuario)) {
            echo "Técnico asignado";
        } else {
            echo "Error al asignar";
        }
        break;

    case "convertirVenta":
        $idticket = intval($_POST["idticket"]);
        $idventa = intval($_POST["idventa"]);
        if ($objTicket->ConvertirAVenta($idticket, $idventa)) {
            echo "Ticket convertido a venta";
        } else {
            echo "Error al convertir";
        }
        break;

    case "stats":
        $query = $objTicket->Estadisticas();
        $reg = $query->fetch_object();
        echo json_encode($reg);
        break;
}

function estadoTicketLabel($estado) {
    $map = [
        'abierto' => 'primary', 'en_diagnostico' => 'info', 'en_proceso' => 'warning',
        'esperando_repuestos' => 'default', 'finalizado' => 'success', 'cancelado' => 'danger'
    ];
    return $map[$estado] ?? 'default';
}

function formatearEstado($estado) {
    return ucwords(str_replace('_', ' ', $estado));
}
