<?php
/**
 * API Endpoint: Crear ticket automático
 * POST /api/ticket_auto.php
 *
 * Crea un ticket vinculado a un diagnóstico existente.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../config/tenant.php";
require_once __DIR__ . "/../config/security.php";
require_once __DIR__ . "/../model/Ticket.php";

// Rate limit
if (!checkRateLimit('api_ticket', 30, 60)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Rate limit excedido']);
    exit;
}

// Validar que hay un tenant resuelto
if (!Tenant::id()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Negocio no identificado. Incluir X-API-Key header.']);
    exit;
}

$objTicket = new Ticket();

// GET: Consultar ticket por código
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $codigo = $_GET['codigo'] ?? '';
    if (empty($codigo)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Código de ticket requerido']);
        exit;
    }

    $query = $objTicket->ObtenerPorCodigo($codigo);
    if ($reg = $query->fetch_object()) {
        echo json_encode(['status' => 'success', 'data' => $reg]);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Ticket no encontrado']);
    }
    exit;
}

// POST: Crear ticket
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$input = file_get_contents('php://input');
$datos = json_decode($input, true);

if (!$datos) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'JSON inválido']);
    exit;
}

$iddiagnostico = intval($datos['iddiagnostico'] ?? 0);
$idpersona = intval($datos['idpersona'] ?? 0);
$titulo = $datos['titulo'] ?? 'Ticket de soporte';
$descripcion = $datos['descripcion'] ?? '';
$prioridad = $datos['prioridad'] ?? 'media';
$tipo_servicio = $datos['tipo_servicio'] ?? 'remoto';

if ($iddiagnostico > 0) {
    $result = $objTicket->CrearDesdeDiagnostico($iddiagnostico, $idpersona, $titulo, $descripcion, $prioridad, $tipo_servicio);
} else {
    $idusuario = intval($datos['idusuario'] ?? 0);
    $costo = floatval($datos['costo_estimado'] ?? 0);
    $result = $objTicket->Registrar($idpersona, $idusuario, $titulo, $descripcion, $tipo_servicio, $prioridad, $costo);
}

if ($result['success']) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Ticket creado exitosamente',
        'data' => $result
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al crear ticket']);
}
