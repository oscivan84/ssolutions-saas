<?php
/**
 * AJAX Handler: Gestión de flujos conversacionales y prompts
 * Validación, versionado, A/B testing
 */

session_start();
require_once "../config/database.php";
require_once "../config/tenant.php";
require_once "../services/FlowValidator.php";

Tenant::requireAuth('admin');
$nid = Tenant::id();

switch ($_GET["op"]) {

    // ============================================================
    // FLUJOS CONVERSACIONALES
    // ============================================================

    case "listarFlujos":
        $sql = "SELECT idflujo, nombre, version, tipo_negocio, activo, estabilidad, metricas_json,
                       notas_cambio, fecha_creacion
                FROM flujos_conversacion WHERE negocio_id = ? ORDER BY nombre, version DESC";
        $result = ejecutarConsulta($sql, 'i', [$nid]);
        $data = [];
        if ($result) while ($r = $result->fetch_object()) {
            $r->metricas_json = json_decode($r->metricas_json);
            $data[] = $r;
        }
        echo json_encode($data);
        break;

    case "getFlujo":
        $id = intval($_GET["id"]);
        $sql = "SELECT * FROM flujos_conversacion WHERE idflujo = ? AND negocio_id = ?";
        $result = ejecutarConsulta($sql, 'ii', [$id, $nid]);
        $flujo = $result ? $result->fetch_object() : null;
        if ($flujo) {
            $flujo->pasos = json_decode($flujo->pasos);
            $flujo->metricas_json = json_decode($flujo->metricas_json);
        }
        echo json_encode($flujo);
        break;

    case "validarFlujo":
        $json = $_POST["pasos"] ?? '';
        $resultado = FlowValidator::validar($json);
        echo json_encode($resultado);
        break;

    case "guardarFlujo":
        $nombre = $_POST["nombre"] ?? 'principal';
        $pasos = $_POST["pasos"] ?? '';
        $notas = $_POST["notas"] ?? '';
        $estabilidad = $_POST["estabilidad"] ?? 'experimental';

        // Validar antes de guardar
        $validacion = FlowValidator::validar($pasos);
        if (!$validacion['valido']) {
            echo json_encode(["success" => false, "errores" => $validacion['errores']]);
            break;
        }

        // Obtener siguiente versión
        $sqlVersion = "SELECT COALESCE(MAX(version), 0) + 1 as next_v FROM flujos_conversacion WHERE negocio_id = ? AND nombre = ?";
        $resV = ejecutarConsulta($sqlVersion, 'is', [$nid, $nombre]);
        $nextV = $resV ? $resV->fetch_object()->next_v : 1;

        // Desactivar versión anterior
        $sqlDesactivar = "UPDATE flujos_conversacion SET activo = 0 WHERE negocio_id = ? AND nombre = ? AND activo = 1";
        ejecutarConsulta($sqlDesactivar, 'is', [$nid, $nombre]);

        // Insertar nueva versión
        $negocio = Tenant::negocio();
        $tipo = $negocio->tipo_negocio ?? 'tecnico';
        $sql = "INSERT INTO flujos_conversacion (negocio_id, nombre, version, tipo_negocio, pasos, activo, estabilidad, creado_por, notas_cambio)
                VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)";
        $idusuario = intval($_SESSION['idusuario'] ?? 0);
        $result = ejecutarConsulta($sql, 'isisssss', [$nid, $nombre, $nextV, $tipo, $pasos, $estabilidad, $idusuario, $notas]);

        if ($result) {
            require_once "../services/AuditService.php";
            AuditService::log('flujo_update', 'flujos_conversacion', ultimoId(), null, ['version' => $nextV, 'estabilidad' => $estabilidad]);
        }

        echo json_encode([
            "success" => (bool)$result,
            "version" => $nextV,
            "warnings" => $validacion['warnings'],
            "stats" => $validacion['stats']
        ]);
        break;

    case "rollbackFlujo":
        $id = intval($_POST["id"]);

        // Verificar que pertenece al negocio
        $sql = "SELECT nombre, version FROM flujos_conversacion WHERE idflujo = ? AND negocio_id = ?";
        $result = ejecutarConsulta($sql, 'ii', [$id, $nid]);
        $flujo = $result ? $result->fetch_object() : null;
        if (!$flujo) {
            echo json_encode(["success" => false, "message" => "Flujo no encontrado"]);
            break;
        }

        // Desactivar todos del mismo nombre
        $sqlOff = "UPDATE flujos_conversacion SET activo = 0 WHERE negocio_id = ? AND nombre = ?";
        ejecutarConsulta($sqlOff, 'is', [$nid, $flujo->nombre]);

        // Activar la versión seleccionada
        $sqlOn = "UPDATE flujos_conversacion SET activo = 1 WHERE idflujo = ? AND negocio_id = ?";
        ejecutarConsulta($sqlOn, 'ii', [$id, $nid]);

        require_once "../services/AuditService.php";
        AuditService::log('flujo_rollback', 'flujos_conversacion', $id, null, ['version' => $flujo->version]);

        echo json_encode(["success" => true, "message" => "Rollback a versión {$flujo->version}"]);
        break;

    // ============================================================
    // PROMPTS
    // ============================================================

    case "listarPrompts":
        $sql = "SELECT idprompt, tipo, nombre, version, activo, estabilidad, es_default, tipo_negocio,
                       metricas_json, fecha_creacion
                FROM prompts_ia WHERE negocio_id = ? ORDER BY tipo, version DESC";
        $result = ejecutarConsulta($sql, 'i', [$nid]);
        $data = [];
        if ($result) while ($r = $result->fetch_object()) {
            $r->metricas_json = json_decode($r->metricas_json);
            $data[] = $r;
        }
        echo json_encode($data);
        break;

    case "guardarPrompt":
        $tipo = $_POST["tipo"] ?? 'ventas';
        $nombre = $_POST["nombre"] ?? 'default';
        $system_prompt = $_POST["system_prompt"] ?? '';
        $estabilidad = $_POST["estabilidad"] ?? 'experimental';
        $notas = $_POST["notas"] ?? '';

        // Validar prompt
        $validacion = FlowValidator::validarPrompt($system_prompt);
        if (!$validacion['valido']) {
            echo json_encode(["success" => false, "errores" => $validacion['errores']]);
            break;
        }

        // Siguiente versión
        $sqlV = "SELECT COALESCE(MAX(version), 0) + 1 as next_v FROM prompts_ia WHERE negocio_id = ? AND tipo = ? AND nombre = ?";
        $resV = ejecutarConsulta($sqlV, 'iss', [$nid, $tipo, $nombre]);
        $nextV = $resV ? $resV->fetch_object()->next_v : 1;

        // Desactivar versión anterior
        $sqlOff = "UPDATE prompts_ia SET activo = 0 WHERE negocio_id = ? AND tipo = ? AND nombre = ? AND activo = 1";
        ejecutarConsulta($sqlOff, 'iss', [$nid, $tipo, $nombre]);

        // Insertar
        $negocio = Tenant::negocio();
        $tipoNeg = $negocio->tipo_negocio ?? 'tecnico';
        $sql = "INSERT INTO prompts_ia (negocio_id, tipo_negocio, tipo, nombre, system_prompt, version, activo, estabilidad, notas, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)";
        $result = ejecutarConsulta($sql, 'issssisis', [
            $nid, $tipoNeg, $tipo, $nombre, $system_prompt, $nextV, $estabilidad, $notas, intval($_SESSION['idusuario'] ?? 0)
        ]);

        if ($result) {
            require_once "../services/AuditService.php";
            AuditService::log('prompt_update', 'prompts_ia', ultimoId(), null, ['tipo' => $tipo, 'version' => $nextV]);
        }

        echo json_encode([
            "success" => (bool)$result,
            "version" => $nextV,
            "warnings" => $validacion['warnings']
        ]);
        break;

    case "rollbackPrompt":
        $id = intval($_POST["id"]);
        $sql = "SELECT tipo, nombre, version FROM prompts_ia WHERE idprompt = ? AND negocio_id = ?";
        $result = ejecutarConsulta($sql, 'ii', [$id, $nid]);
        $prompt = $result ? $result->fetch_object() : null;
        if (!$prompt) {
            echo json_encode(["success" => false, "message" => "Prompt no encontrado"]);
            break;
        }

        ejecutarConsulta("UPDATE prompts_ia SET activo = 0 WHERE negocio_id = ? AND tipo = ? AND nombre = ?", 'iss', [$nid, $prompt->tipo, $prompt->nombre]);
        ejecutarConsulta("UPDATE prompts_ia SET activo = 1 WHERE idprompt = ? AND negocio_id = ?", 'ii', [$id, $nid]);

        echo json_encode(["success" => true, "message" => "Rollback a v{$prompt->version}"]);
        break;
}
