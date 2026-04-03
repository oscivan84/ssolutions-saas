<?php
/**
 * SSolutions SaaS — Entry point
 * Redirige al dashboard o muestra status de la API
 */

header('Content-Type: application/json');

echo json_encode([
    'status' => 'ok',
    'service' => 'SSolutions SaaS',
    'version' => '1.0.0',
    'endpoints' => [
        'dashboard' => '/api/dashboard.php',
        'webhook' => '/api/webhook_whatsapp.php',
        'diagnostico' => '/api/diagnostico.php',
        'worker' => '/api/worker_http.php?key=KEY',
    ],
    'timestamp' => date('Y-m-d H:i:s')
]);
