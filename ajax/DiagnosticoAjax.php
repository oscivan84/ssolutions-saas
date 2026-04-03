<?php
/**
 * AJAX Handler: Diagnósticos
 * Sigue el patrón de SSolutions (switch $_GET["op"])
 */

session_start();
require_once "../model/Diagnostico.php";

$objDiagnostico = new Diagnostico();

switch ($_GET["op"]) {

    case "list":
        $query = $objDiagnostico->Listar();
        $data = [];
        $i = 1;
        while ($reg = $query->fetch_object()) {
            $data[] = [
                "0" => $i,
                "1" => $reg->fecha_diagnostico,
                "2" => $reg->nombre_cliente,
                "3" => $reg->hostname,
                "4" => $reg->sistema_operativo,
                "5" => number_format($reg->cpu_uso_porcentaje, 1) . '%',
                "6" => number_format($reg->ram_uso_porcentaje, 1) . '%',
                "7" => number_format($reg->disco_uso_porcentaje, 1) . '%',
                "8" => '<span class="label label-' . nivelUrgenciaLabel($reg->nivel_urgencia) . '">' . ucfirst($reg->nivel_urgencia) . '</span>',
                "9" => '<button class="btn btn-info btn-xs" onclick="verDiagnostico(' . $reg->iddiagnostico . ')"><i class="fa fa-eye"></i></button>'
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
        $query = $objDiagnostico->ObtenerPorId($id);
        if ($reg = $query->fetch_object()) {
            echo json_encode($reg);
        } else {
            echo json_encode(["error" => "Diagnóstico no encontrado"]);
        }
        break;

    case "stats":
        $query = $objDiagnostico->Estadisticas();
        $reg = $query->fetch_object();
        echo json_encode($reg);
        break;

    case "porCliente":
        $idpersona = intval($_GET["idpersona"]);
        $query = $objDiagnostico->ListarPorCliente($idpersona);
        $data = [];
        while ($reg = $query->fetch_object()) {
            $data[] = $reg;
        }
        echo json_encode($data);
        break;
}

function nivelUrgenciaLabel($nivel) {
    $map = ['baja' => 'success', 'media' => 'warning', 'alta' => 'danger', 'critica' => 'danger'];
    return $map[$nivel] ?? 'default';
}
