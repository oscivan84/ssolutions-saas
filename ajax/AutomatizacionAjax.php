<?php
/**
 * AJAX Handler: Centro de Automatizacion
 * Eventos, jobs, automatizaciones, scores, log de conversacion, metricas ventas
 */

session_start();
require_once "../config/database.php";
require_once "../config/tenant.php";

$nid = Tenant::id();

switch ($_GET["op"]) {

    // ============================================================
    // EVENTOS
    // ============================================================
    case "eventos":
        $limit = intval($_GET["limit"] ?? 50);
        $sql = "SELECT * FROM eventos WHERE negocio_id = ? ORDER BY fecha_creacion DESC LIMIT ?";
        $result = ejecutarConsulta($sql, 'ii', [$nid, $limit]);
        $data = [];
        if ($result) while ($r = $result->fetch_object()) {
            $r->payload = json_decode($r->payload);
            $data[] = $r;
        }
        echo json_encode($data);
        break;

    case "eventosStats":
        $sql = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN procesado = 1 THEN 1 ELSE 0 END) as procesados,
            SUM(CASE WHEN procesado = 0 THEN 1 ELSE 0 END) as pendientes,
            COUNT(DISTINCT tipo) as tipos_unicos
        FROM eventos WHERE negocio_id = ? AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = ejecutarConsulta($sql, 'i', [$nid]);
        echo json_encode($result ? $result->fetch_object() : null);
        break;

    // ============================================================
    // JOBS
    // ============================================================
    case "jobs":
        $estado = $_GET["estado"] ?? '';
        $sql = "SELECT * FROM jobs WHERE negocio_id = ?";
        $types = 'i';
        $params = [$nid];
        if ($estado) {
            $sql .= " AND estado = ?";
            $types .= 's';
            $params[] = $estado;
        }
        $sql .= " ORDER BY fecha_creacion DESC LIMIT 100";
        $result = ejecutarConsulta($sql, $types, $params);
        $data = [];
        if ($result) while ($r = $result->fetch_object()) {
            $r->payload = json_decode($r->payload);
            $r->resultado = json_decode($r->resultado);
            $data[] = $r;
        }
        echo json_encode($data);
        break;

    case "jobsStats":
        $sql = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'procesando' THEN 1 ELSE 0 END) as procesando,
            SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
            SUM(CASE WHEN estado = 'fallido' THEN 1 ELSE 0 END) as fallidos
        FROM jobs WHERE negocio_id = ? AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = ejecutarConsulta($sql, 'i', [$nid]);
        echo json_encode($result ? $result->fetch_object() : null);
        break;

    // ============================================================
    // AUTOMATIZACIONES (CRUD + control)
    // ============================================================
    case "automatizaciones":
        $sql = "SELECT * FROM automatizaciones WHERE negocio_id = ? ORDER BY fecha_creacion DESC";
        $result = ejecutarConsulta($sql, 'i', [$nid]);
        $data = [];
        if ($result) while ($r = $result->fetch_object()) {
            $r->condicion_json = json_decode($r->condicion_json);
            $r->accion_config = json_decode($r->accion_config);
            $data[] = $r;
        }
        echo json_encode($data);
        break;

    case "toggleAutomatizacion":
        $id = intval($_POST["id"]);
        $activo = intval($_POST["activo"]);
        $sql = "UPDATE automatizaciones SET activo = ? WHERE idautomatizacion = ? AND negocio_id = ?";
        ejecutarConsulta($sql, 'iii', [$activo, $id, $nid]);
        echo json_encode(["success" => true]);
        break;

    case "probarAutomatizacion":
        $id = intval($_POST["id"]);
        $sql = "SELECT * FROM automatizaciones WHERE idautomatizacion = ? AND negocio_id = ?";
        $result = ejecutarConsulta($sql, 'ii', [$id, $nid]);
        $auto = $result ? $result->fetch_object() : null;
        if (!$auto) {
            echo json_encode(["success" => false, "message" => "No encontrada"]);
            break;
        }
        // Crear job de prueba
        require_once "../services/JobQueue.php";
        $config = json_decode($auto->accion_config, true) ?: [];
        $config['_test'] = true;
        $jobId = JobQueue::crear($auto->accion, $config);
        echo json_encode(["success" => true, "job_id" => $jobId, "message" => "Job de prueba #{$jobId} creado"]);
        break;

    // ============================================================
    // SCORES DE CLIENTES
    // ============================================================
    case "scores":
        $sql = "SELECT s.*, p.nombre AS cliente, p.telefono
                FROM score_clientes s
                LEFT JOIN persona p ON s.idpersona = p.idpersona
                WHERE s.negocio_id = ?
                ORDER BY s.fecha DESC LIMIT 50";
        $result = ejecutarConsulta($sql, 'i', [$nid]);
        $data = [];
        if ($result) while ($r = $result->fetch_object()) $data[] = $r;
        echo json_encode($data);
        break;

    case "scoresResumen":
        $sql = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN score = 'caliente' THEN 1 ELSE 0 END) as calientes,
            SUM(CASE WHEN score = 'tibio' THEN 1 ELSE 0 END) as tibios,
            SUM(CASE WHEN score = 'frio' THEN 1 ELSE 0 END) as frios
        FROM score_clientes WHERE negocio_id = ? AND fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $result = ejecutarConsulta($sql, 'i', [$nid]);
        echo json_encode($result ? $result->fetch_object() : null);
        break;

    // ============================================================
    // LOG DE CONVERSACION INTELIGENTE
    // ============================================================
    case "logConversacion":
        $idticket = intval($_GET["idticket"] ?? 0);
        $telefono = $_GET["telefono"] ?? '';

        $sql = "SELECT m.*, c.estado_flujo, c.contexto_json
                FROM mensajes_whatsapp m
                LEFT JOIN conversaciones_whatsapp c ON c.telefono = m.telefono AND c.negocio_id = m.negocio_id
                WHERE m.negocio_id = ?";
        $types = 'i';
        $params = [$nid];

        if ($idticket) {
            $sql .= " AND m.idticket = ?";
            $types .= 'i';
            $params[] = $idticket;
        } elseif ($telefono) {
            $sql .= " AND m.telefono = ?";
            $types .= 's';
            $params[] = $telefono;
        }

        $sql .= " GROUP BY m.idmensaje ORDER BY m.fecha DESC LIMIT 100";
        $result = ejecutarConsulta($sql, $types, $params);
        $data = [];
        if ($result) while ($r = $result->fetch_object()) $data[] = $r;
        echo json_encode($data);
        break;

    // ============================================================
    // METRICAS DE VENTAS (lo que vende el producto)
    // ============================================================
    case "metricasVentas":
        // Mensajes enviados automaticamente (30 dias)
        $sqlMsgs = "SELECT COUNT(*) as total FROM mensajes_whatsapp
                    WHERE negocio_id = ? AND direccion = 'enviado'
                    AND fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $msgs = ejecutarConsulta($sqlMsgs, 'i', [$nid]);
        $totalMsgs = $msgs ? $msgs->fetch_object()->total : 0;

        // Clientes recuperados (respondieron despues de seguimiento)
        $sqlRecuperados = "SELECT COUNT(DISTINCT m2.telefono) as total
            FROM mensajes_whatsapp m1
            JOIN mensajes_whatsapp m2 ON m1.telefono = m2.telefono AND m2.direccion = 'recibido' AND m2.fecha > m1.fecha
            WHERE m1.negocio_id = ? AND m1.direccion = 'enviado'
            AND m1.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $rec = ejecutarConsulta($sqlRecuperados, 'i', [$nid]);
        $totalRecuperados = $rec ? $rec->fetch_object()->total : 0;

        // Conversion: tickets finalizados / tickets totales
        $sqlConversion = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizados,
            SUM(CASE WHEN idventa IS NOT NULL THEN 1 ELSE 0 END) as convertidos
        FROM tickets WHERE negocio_id = ? AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $conv = ejecutarConsulta($sqlConversion, 'i', [$nid]);
        $convData = $conv ? $conv->fetch_object() : (object)['total' => 0, 'finalizados' => 0, 'convertidos' => 0];

        $tasa = $convData->total > 0 ? round(($convData->finalizados / $convData->total) * 100, 1) : 0;

        // Ingresos por WhatsApp (tickets que tuvieron conversacion WhatsApp)
        $sqlIngresosWA = "SELECT COALESCE(SUM(t.costo_final), 0) as total
            FROM tickets t
            WHERE t.negocio_id = ? AND t.estado = 'finalizado'
            AND t.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND EXISTS (SELECT 1 FROM mensajes_whatsapp m WHERE m.idticket = t.idticket)";
        $ingWA = ejecutarConsulta($sqlIngresosWA, 'i', [$nid]);
        $totalIngresosWA = $ingWA ? $ingWA->fetch_object()->total : 0;

        echo json_encode([
            'mensajes_enviados' => intval($totalMsgs),
            'clientes_recuperados' => intval($totalRecuperados),
            'tasa_conversion' => $tasa,
            'tickets_total' => intval($convData->total),
            'tickets_finalizados' => intval($convData->finalizados),
            'ingresos_whatsapp' => floatval($totalIngresosWA)
        ]);
        break;

    // ============================================================
    // RECUPERAR CLIENTES (sin respuesta)
    // ============================================================
    case "clientesSinRespuesta":
        $sql = "SELECT DISTINCT c.telefono, c.idpersona, p.nombre,
                    c.ultimo_mensaje_enviado, c.fecha_ultimo_mensaje, c.idticket
                FROM conversaciones_whatsapp c
                LEFT JOIN persona p ON c.idpersona = p.idpersona
                WHERE c.negocio_id = ?
                AND c.estado_flujo IN ('esperando_respuesta', 'inicio')
                AND c.fecha_ultimo_mensaje < DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND c.fecha_ultimo_mensaje >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY c.fecha_ultimo_mensaje DESC
                LIMIT 50";
        $result = ejecutarConsulta($sql, 'i', [$nid]);
        $data = [];
        if ($result) while ($r = $result->fetch_object()) $data[] = $r;
        echo json_encode($data);
        break;

    case "recuperarClientes":
        $telefonos = json_decode($_POST["telefonos"] ?? '[]', true);
        if (empty($telefonos)) {
            echo json_encode(["success" => false, "message" => "Sin telefonos"]);
            break;
        }

        require_once "../services/JobQueue.php";
        $empresa = Tenant::config('empresa_nombre', 'SSolutions');
        $enviados = 0;

        foreach ($telefonos as $tel) {
            $telefono = $tel['telefono'] ?? '';
            $nombre = $tel['nombre'] ?? 'Cliente';
            if (empty($telefono)) continue;

            JobQueue::crear('enviar_whatsapp', [
                'telefono' => $telefono,
                'mensaje' => "Hola $nombre! Somos $empresa. Vimos que no pudiste responder a nuestro ultimo mensaje. Seguimos disponibles para ayudarte con tu equipo. Escribe HOLA para retomar la conversacion."
            ]);
            $enviados++;
        }

        echo json_encode(["success" => true, "enviados" => $enviados]);
        break;

    // ============================================================
    // ULTIMO CLIENTE RECUPERADO (prueba social interna)
    // ============================================================
    case "ultimoRecuperado":
        // Buscar último cliente que respondió después de un mensaje automático enviado
        $sql = "SELECT m_resp.contenido AS respuesta, m_resp.fecha AS fecha_respuesta,
                       m_resp.telefono, p.nombre AS cliente,
                       TIMESTAMPDIFF(MINUTE, m_resp.fecha, NOW()) AS hace_minutos
                FROM mensajes_whatsapp m_env
                JOIN mensajes_whatsapp m_resp
                    ON m_resp.telefono = m_env.telefono
                    AND m_resp.negocio_id = m_env.negocio_id
                    AND m_resp.direccion = 'recibido'
                    AND m_resp.fecha > m_env.fecha
                    AND m_resp.fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                LEFT JOIN persona p ON m_resp.idpersona = p.idpersona
                WHERE m_env.negocio_id = ?
                AND m_env.direccion = 'enviado'
                AND m_env.fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY m_resp.fecha DESC
                LIMIT 1";
        $result = ejecutarConsulta($sql, 'i', [$nid]);
        $ultimo = $result ? $result->fetch_object() : null;

        if ($ultimo) {
            echo json_encode([
                'encontrado' => true,
                'cliente' => $ultimo->cliente ?: 'Cliente',
                'respuesta' => mb_substr($ultimo->respuesta, 0, 120),
                'hace_minutos' => intval($ultimo->hace_minutos),
                'telefono' => $ultimo->telefono
            ]);
        } else {
            echo json_encode(['encontrado' => false]);
        }
        break;
}
