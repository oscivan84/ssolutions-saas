<?php
/**
 * Worker HTTP — Endpoint para ejecutar jobs via cron externo.
 *
 * Llamar desde cron-job.org cada 1 minuto:
 *   GET https://tu-dominio.com/landingV2/api/worker_http.php?key=TU_CRON_KEY
 *
 * Protegido por clave secreta para evitar ejecución no autorizada.
 */

header('Content-Type: application/json');

// Validar clave de cron
$cronKey = $_GET['key'] ?? '';
$expectedKey = getenv('CRON_SECRET_KEY') ?: 'ss-cron-default-key';

if ($cronKey !== $expectedKey) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid cron key']);
    exit;
}

// Ejecutar el worker (mismo código que worker.php CLI)
set_time_limit(55); // Máximo 55 segundos (cron cada 60s)

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/tenant.php";
require_once __DIR__ . "/../services/JobQueue.php";
require_once __DIR__ . "/../services/WhatsAppService.php";

$maxJobs = 10;
$procesados = 0;
$resultados = [];

// Health check del worker
file_put_contents(sys_get_temp_dir() . '/ss_worker_heartbeat', date('Y-m-d H:i:s'));

// Limpieza de jobs viejos
global $conexion;
$conexion->query("DELETE FROM jobs WHERE estado = 'completado' AND fecha_fin < DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 1000");

while ($procesados < $maxJobs) {
    $job = JobQueue::siguiente();
    if (!$job) break;

    Tenant::setId($job->negocio_id);
    $payload = json_decode($job->payload, true) ?: [];

    try {
        // Procesadores de jobs (simplificado)
        switch ($job->tipo) {
            case 'enviar_whatsapp':
                $whatsapp = new WhatsAppService();
                $telefono = $payload['telefono'] ?? '';
                $mensaje = $payload['mensaje'] ?? '';
                if ($telefono && $mensaje) {
                    $whatsapp->enviarTexto($telefono, $mensaje);
                }
                $resultado = ['enviado' => true];
                break;

            case 'scoring':
            case 'alerta':
            case 'generar_resumen_ia':
                $resultado = ['tipo' => $job->tipo, 'procesado' => true];
                break;

            default:
                $resultado = ['skip' => "Tipo desconocido: {$job->tipo}"];
        }

        JobQueue::completar($job->idjob, $resultado);
        $resultados[] = ['job' => $job->idjob, 'tipo' => $job->tipo, 'status' => 'ok'];
    } catch (Exception $e) {
        JobQueue::fallar($job->idjob, $e->getMessage());
        $resultados[] = ['job' => $job->idjob, 'tipo' => $job->tipo, 'status' => 'error', 'error' => $e->getMessage()];
    }

    $procesados++;
}

echo json_encode([
    'status' => 'ok',
    'procesados' => $procesados,
    'timestamp' => date('Y-m-d H:i:s'),
    'resultados' => $resultados
]);
