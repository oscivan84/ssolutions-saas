<?php
/**
 * AJAX Handler: Mantenimientos
 * Sigue el patrón de SSolutions (switch $_GET["op"])
 */

session_start();
require_once "../config/tenant.php";
require_once "../model/Mantenimiento.php";

$objMantenimiento = new Mantenimiento();
$op = $_GET["op"] ?? '';

if ($op === 'SaveOrUpdate') {
    Tenant::requireAuth('tecnico');
}

switch ($op) {

    case "SaveOrUpdate":
        $idticket = intval($_POST["idticket"]);
        $idusuario = intval($_SESSION["idusuario"] ?? $_POST["idusuario"] ?? 0);
        $tipo_accion = $_POST["tipo_accion"];
        $descripcion = $_POST["descripcion"];
        $repuestos_usados = $_POST["repuestos_usados"] ?? '[]';
        $duracion_minutos = intval($_POST["duracion_minutos"] ?? 0);
        $costo = floatval($_POST["costo"] ?? 0);

        if ($objMantenimiento->Registrar($idticket, $idusuario, $tipo_accion, $descripcion, $repuestos_usados, $duracion_minutos, $costo)) {
            echo "Mantenimiento registrado correctamente";
        } else {
            echo "Error al registrar. Verifique stock de repuestos.";
        }
        break;

    case "listPorTicket":
        $idticket = intval($_GET["idticket"]);
        $query = $objMantenimiento->ListarPorTicket($idticket);
        $data = [];
        $i = 1;
        while ($reg = $query->fetch_object()) {
            $data[] = [
                "0" => $i,
                "1" => $reg->fecha_accion,
                "2" => ucfirst($reg->tipo_accion),
                "3" => $reg->descripcion,
                "4" => $reg->tecnico ?? '-',
                "5" => $reg->duracion_minutos . ' min',
                "6" => '$' . number_format($reg->costo, 0)
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

    case "resumenCostos":
        $idticket = intval($_GET["idticket"]);
        $query = $objMantenimiento->ResumenCostos($idticket);
        $reg = $query->fetch_object();
        echo json_encode($reg);
        break;

    case "listPorTecnico":
        $idusuario = intval($_SESSION["idusuario"] ?? $_GET["idusuario"] ?? 0);
        $query = $objMantenimiento->ListarPorTecnico($idusuario);
        $data = [];
        while ($reg = $query->fetch_object()) {
            $data[] = $reg;
        }
        echo json_encode($data);
        break;
}
