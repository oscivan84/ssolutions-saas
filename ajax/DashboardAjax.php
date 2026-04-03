<?php
/**
 * AJAX Handler: Dashboard de Soporte
 * Estadisticas y metricas para el panel de control
 */

session_start();
require_once "../services/TicketService.php";
require_once "../services/AIService.php";

$ticketService = new TicketService();

switch ($_GET["op"]) {

    case "stats":
        $data = $ticketService->obtenerDashboard();
        echo json_encode($data);
        break;

    case "historialEstados":
        $idticket = intval($_GET["idticket"]);
        $query = $ticketService->obtenerHistorialEstados($idticket);
        $data = [];
        if ($query) {
            while ($reg = $query->fetch_object()) {
                $data[] = $reg;
            }
        }
        echo json_encode($data);
        break;

    case "costoFinal":
        $idticket = intval($_GET["idticket"]);
        $data = $ticketService->calcularCostoFinal($idticket);
        echo json_encode($data);
        break;

    case "cambiarEstado":
        $idticket = intval($_POST["idticket"]);
        $estado = $_POST["estado"];
        $observacion = $_POST["observacion"] ?? '';
        $idusuario = intval($_SESSION["idusuario"] ?? $_POST["idusuario"] ?? 0) ?: null;

        $result = $ticketService->cambiarEstado($idticket, $estado, $idusuario, $observacion);
        echo json_encode($result);
        break;

    case "convertirVenta":
        $idticket = intval($_POST["idticket"]);
        $idventa = intval($_POST["idventa"]);
        $result = $ticketService->convertirAVenta($idticket, $idventa);
        echo json_encode($result);
        break;

    case "recomendacionesIA":
        $iddiagnostico = intval($_GET["iddiagnostico"]);

        require_once "../model/Diagnostico.php";
        $objDiag = new Diagnostico();
        $query = $objDiag->ObtenerPorId($iddiagnostico);
        $diag = $query ? $query->fetch_object() : null;

        if (!$diag) {
            echo json_encode(["error" => "Diagnostico no encontrado"]);
            break;
        }

        $datos = json_decode($diag->reporte_completo, true);
        if (!$datos) {
            $datos = [
                'cpu' => ['uso_porcentaje' => $diag->cpu_uso_porcentaje, 'modelo' => $diag->cpu_modelo],
                'ram' => ['uso_porcentaje' => $diag->ram_uso_porcentaje, 'total_gb' => $diag->ram_total_gb],
                'disco' => ['uso_porcentaje' => $diag->disco_uso_porcentaje, 'total_gb' => $diag->disco_total_gb],
                'problemas' => json_decode($diag->problemas_detectados, true) ?: []
            ];
        }

        $aiService = new AIService();
        $recomendaciones = $aiService->generarRecomendacionesServicio($datos);
        echo json_encode(["recomendaciones" => $recomendaciones]);
        break;
}
