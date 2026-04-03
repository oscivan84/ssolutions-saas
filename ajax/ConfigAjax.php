<?php
/**
 * AJAX Handler: Configuracion del modulo de soporte
 * CRUD para la tabla configuracion_soporte (filtrado por negocio)
 */

session_start();
require_once "../config/database.php";
require_once "../config/tenant.php";
require_once "../services/AuditService.php";

// Config requiere rol admin o dueño
$op = $_GET["op"] ?? '';
if (in_array($op, ['guardar', 'guardarMultiple'])) {
    Tenant::requireAuth('admin');
}

switch ($op) {

    case "get":
        $clave = $_GET["clave"] ?? '';
        if (empty($clave)) {
            echo json_encode(["error" => "Clave requerida"]);
            break;
        }
        $result = ejecutarConsulta(
            "SELECT clave, valor, descripcion FROM configuracion_soporte WHERE clave = ? AND negocio_id = ?",
            'si', [$clave, Tenant::id()]
        );
        if ($result && $row = $result->fetch_object()) {
            echo json_encode($row);
        } else {
            echo json_encode(["clave" => $clave, "valor" => ""]);
        }
        break;

    case "guardar":
        $clave = $_POST["clave"] ?? '';
        $valor = $_POST["valor"] ?? '';

        if (empty($clave)) {
            echo "Clave requerida";
            break;
        }

        // Obtener valor anterior para audit
        $sqlAntes = "SELECT valor FROM configuracion_soporte WHERE clave = ? AND negocio_id = ?";
        $resAntes = ejecutarConsulta($sqlAntes, 'si', [$clave, Tenant::id()]);
        $valorAntes = ($resAntes && $r = $resAntes->fetch_object()) ? $r->valor : null;

        // Upsert con negocio_id
        $sql = "INSERT INTO configuracion_soporte (negocio_id, clave, valor) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE valor = ?";
        $result = ejecutarConsulta($sql, 'isss', [Tenant::id(), $clave, $valor, $valor]);

        // Audit log
        if ($result) {
            AuditService::log('config_update', 'configuracion_soporte', null,
                ['clave' => $clave, 'valor' => $valorAntes],
                ['clave' => $clave, 'valor' => $valor]
            );
        }

        echo $result ? "Guardado" : "Error al guardar";
        break;

    case "guardarMultiple":
        $configs = json_decode($_POST["configs"] ?? '[]', true);
        if (!is_array($configs)) {
            echo "Formato invalido";
            break;
        }

        $errores = 0;
        foreach ($configs as $cfg) {
            $clave = $cfg['clave'] ?? '';
            $valor = $cfg['valor'] ?? '';
            if (empty($clave)) continue;

            $sql = "INSERT INTO configuracion_soporte (negocio_id, clave, valor) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE valor = ?";
            $result = ejecutarConsulta($sql, 'isss', [Tenant::id(), $clave, $valor, $valor]);
            if (!$result) $errores++;
        }

        echo $errores === 0 ? "Configuracion guardada" : "Guardado con $errores errores";
        break;

    case "listar":
        $result = ejecutarConsulta(
            "SELECT * FROM configuracion_soporte WHERE negocio_id = ? ORDER BY clave",
            'i', [Tenant::id()]
        );
        $data = [];
        if ($result) {
            while ($row = $result->fetch_object()) {
                $data[] = $row;
            }
        }
        echo json_encode($data);
        break;
}
