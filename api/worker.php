<?php
/**
 * Worker: Procesa jobs de la cola
 *
 * Ejecutar como cron cada minuto:
 *   * * * * * php /path/to/landingV2/api/worker.php
 *
 * O como daemon:
 *   while true; do php /path/to/landingV2/api/worker.php; sleep 5; done
 */

// Sin límite de tiempo para el worker
set_time_limit(0);

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/tenant.php";
require_once __DIR__ . "/../services/JobQueue.php";
require_once __DIR__ . "/../services/WhatsAppService.php";

$maxJobs = 10;
$procesados = 0;

// Health check: registrar que el worker está vivo
file_put_contents(sys_get_temp_dir() . '/ss_worker_heartbeat', date('Y-m-d H:i:s'));

// Limpieza: eliminar jobs completados con más de 7 días (evitar tabla infinita)
global $conexion;
$conexion->query("DELETE FROM jobs WHERE estado = 'completado' AND fecha_fin < DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 1000");

while ($procesados < $maxJobs) {
    $job = JobQueue::siguiente();
    if (!$job) break; // No hay más jobs

    // Setear tenant del job
    Tenant::setId($job->negocio_id);

    $payload = json_decode($job->payload, true) ?: [];

    try {
        $resultado = procesarJob($job->tipo, $payload);
        JobQueue::completar($job->idjob, $resultado);
        echo "[OK] Job #{$job->idjob} ({$job->tipo})\n";
    } catch (Exception $e) {
        JobQueue::fallar($job->idjob, $e->getMessage());
        echo "[FAIL] Job #{$job->idjob}: {$e->getMessage()}\n";
    }

    $procesados++;
}

if ($procesados === 0) {
    echo "[IDLE] No hay jobs pendientes\n";
} else {
    echo "[DONE] $procesados jobs procesados\n";
}

// ============================================================
// PROCESADORES DE JOBS
// ============================================================

function procesarJob($tipo, $payload) {
    switch ($tipo) {

        case 'enviar_whatsapp':
            $whatsapp = new WhatsAppService();
            $telefono = $payload['telefono'] ?? '';
            $mensaje = $payload['mensaje'] ?? '';
            $plantilla = $payload['plantilla'] ?? '';

            if ($plantilla) {
                $variables = $payload['variables'] ?? [];
                $whatsapp->enviarDesdePlantilla($plantilla, $telefono, $variables);
            } else {
                $whatsapp->enviarTexto($telefono, $mensaje);
            }
            return ['enviado' => true];

        case 'scoring':
            return procesarScoring($payload);

        case 'alerta':
            return procesarAlerta($payload);

        case 'generar_resumen_ia':
            return procesarResumenIA($payload);

        default:
            throw new Exception("Tipo de job desconocido: $tipo");
    }
}

function procesarScoring($payload) {
    $idpersona = $payload['payload_evento']['idpersona'] ?? null;
    if (!$idpersona) return ['skip' => 'sin idpersona'];

    // Llamar al microservicio de IA para scoring
    $aiServiceUrl = getenv('AI_SERVICE_URL') ?: 'http://127.0.0.1:8100';
    $internalKey = getenv('AI_SERVICE_INTERNAL_KEY') ?: 'ss-internal-dev-key';

    $contexto = $payload['payload_evento'] ?? [];

    $ch = curl_init("$aiServiceUrl/score-cliente");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'contexto' => $contexto,
            'historial' => '',
            'ai' => [
                'provider' => Tenant::config('ai_provider', 'anthropic'),
                'api_key' => Tenant::config('ai_api_key', ''),
                'model' => Tenant::config('ai_model', 'claude-haiku-4-5-20251001')
            ]
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            "X-Internal-Key: $internalKey"
        ],
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    if ($result && !empty($result['score'])) {
        $sql = "INSERT INTO score_clientes (negocio_id, idpersona, score, razon, accion_sugerida, source)
                VALUES (?, ?, ?, ?, ?, ?)";
        ejecutarConsulta($sql, 'iissss', [
            Tenant::id(), $idpersona,
            $result['score'], $result['razon'] ?? '', $result['accion_sugerida'] ?? '',
            $result['source'] ?? 'ia'
        ]);
    }

    return $result ?: ['score' => 'tibio'];
}

function procesarAlerta($payload) {
    // Por ahora, log. En el futuro: email, notificación push, etc.
    $mensaje = $payload['mensaje'] ?? 'Alerta sin mensaje';
    error_log("[ALERTA] Negocio " . Tenant::id() . ": $mensaje");
    return ['alerta_registrada' => true, 'mensaje' => $mensaje];
}

function procesarResumenIA($payload) {
    // Delegar al microservicio de IA
    $aiServiceUrl = getenv('AI_SERVICE_URL') ?: 'http://127.0.0.1:8100';
    $internalKey = getenv('AI_SERVICE_INTERNAL_KEY') ?: 'ss-internal-dev-key';

    $ch = curl_init("$aiServiceUrl/diagnostico");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'datos' => $payload['datos'] ?? [],
            'ai' => [
                'provider' => Tenant::config('ai_provider', 'anthropic'),
                'api_key' => Tenant::config('ai_api_key', ''),
                'model' => Tenant::config('ai_model', 'claude-haiku-4-5-20251001')
            ]
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            "X-Internal-Key: $internalKey"
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true) ?: ['error' => 'Sin respuesta del servicio IA'];
}
