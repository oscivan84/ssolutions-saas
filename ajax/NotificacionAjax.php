<?php
/**
 * AJAX Handler: Notificaciones + Audit Log + Onboarding
 */

session_start();
require_once "../config/database.php";
require_once "../config/tenant.php";
require_once "../services/NotificacionService.php";

switch ($_GET["op"]) {

    // ============================================================
    // NOTIFICACIONES
    // ============================================================
    case "noLeidas":
        $result = NotificacionService::noLeidas(null, 15);
        $data = [];
        if ($result) while ($r = $result->fetch_object()) $data[] = $r;
        echo json_encode([
            'total' => NotificacionService::contarNoLeidas(),
            'items' => $data
        ]);
        break;

    case "marcarLeida":
        $id = intval($_POST["id"]);
        NotificacionService::marcarLeida($id);
        echo json_encode(["success" => true]);
        break;

    case "marcarTodasLeidas":
        NotificacionService::marcarTodasLeidas();
        echo json_encode(["success" => true]);
        break;

    // ============================================================
    // AUDIT LOG
    // ============================================================
    case "auditLog":
        Tenant::requireAuth('admin');
        $limit = intval($_GET["limit"] ?? 50);
        $sql = "SELECT a.*, u.login AS usuario_nombre
                FROM audit_log a
                LEFT JOIN usuario u ON a.idusuario = u.idusuario
                WHERE a.negocio_id = ?
                ORDER BY a.fecha DESC LIMIT ?";
        $result = ejecutarConsulta($sql, 'ii', [Tenant::id(), $limit]);
        $data = [];
        if ($result) while ($r = $result->fetch_object()) {
            $r->datos_antes = json_decode($r->datos_antes);
            $r->datos_despues = json_decode($r->datos_despues);
            $data[] = $r;
        }
        echo json_encode($data);
        break;

    // ============================================================
    // ONBOARDING
    // ============================================================
    case "onboardingEstado":
        $sql = "SELECT * FROM onboarding_estado WHERE negocio_id = ?";
        $result = ejecutarConsulta($sql, 'i', [Tenant::id()]);
        $estado = $result ? $result->fetch_object() : null;

        if (!$estado) {
            // Crear registro de onboarding
            $sql = "INSERT INTO onboarding_estado (negocio_id, paso_actual, datos_json) VALUES (?, 1, '{}')";
            ejecutarConsulta($sql, 'i', [Tenant::id()]);
            $estado = (object)['paso_actual' => 1, 'completado' => 0, 'datos_json' => '{}'];
        }

        $estado->datos_json = json_decode($estado->datos_json ?: '{}');

        // Calcular progreso real basado en configuración
        $pasos = [
            1 => ['nombre' => 'Datos del negocio', 'check' => !empty(Tenant::config('empresa_nombre'))],
            2 => ['nombre' => 'Configurar WhatsApp', 'check' => !empty(Tenant::config('whatsapp_token'))],
            3 => ['nombre' => 'Configurar IA', 'check' => !empty(Tenant::config('ai_api_key'))],
            4 => ['nombre' => 'Primer ticket', 'check' => false],
        ];

        // Verificar si hay al menos 1 ticket
        $sqlT = "SELECT COUNT(*) as total FROM tickets WHERE negocio_id = ?";
        $resT = ejecutarConsulta($sqlT, 'i', [Tenant::id()]);
        $pasos[4]['check'] = $resT && $resT->fetch_object()->total > 0;

        $completados = count(array_filter($pasos, fn($p) => $p['check']));
        $total = count($pasos);

        echo json_encode([
            'paso_actual' => intval($estado->paso_actual),
            'completado' => intval($estado->completado),
            'pasos' => $pasos,
            'progreso' => round(($completados / $total) * 100),
            'total_pasos' => $total,
            'pasos_completados' => $completados
        ]);
        break;

    case "onboardingAvanzar":
        $paso = intval($_POST["paso"]);
        $sql = "UPDATE onboarding_estado SET paso_actual = ? WHERE negocio_id = ?";
        ejecutarConsulta($sql, 'ii', [$paso, Tenant::id()]);

        if ($paso > 4) {
            $sql = "UPDATE onboarding_estado SET completado = 1, fecha_completado = NOW() WHERE negocio_id = ?";
            ejecutarConsulta($sql, 'i', [Tenant::id()]);
        }
        echo json_encode(["success" => true]);
        break;

    case "onboardingCompletar":
        $sql = "UPDATE onboarding_estado SET completado = 1, fecha_completado = NOW() WHERE negocio_id = ?";
        ejecutarConsulta($sql, 'i', [Tenant::id()]);
        echo json_encode(["success" => true]);
        break;
}
