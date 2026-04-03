<?php
/**
 * API Endpoint: Dashboard de estadisticas
 * GET /api/dashboard.php
 *
 * Retorna metricas del sistema de soporte para el panel administrativo
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metodo no permitido']);
    exit;
}

require_once __DIR__ . "/../config/tenant.php";
require_once __DIR__ . "/../services/TicketService.php";

try {
    $ticketService = new TicketService();
    $data = $ticketService->obtenerDashboard();

    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
