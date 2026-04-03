<?php
/**
 * API Endpoint: Webhook de WhatsApp Cloud API
 * GET  → Verificacion del webhook (challenge)
 * POST → Recepcion de mensajes y actualizaciones de estado
 *
 * Configura esta URL en Meta Business Suite como webhook de WhatsApp.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . "/../config/whatsapp.php";
require_once __DIR__ . "/../config/tenant.php";
require_once __DIR__ . "/../services/WhatsAppService.php";
require_once __DIR__ . "/../services/ConversacionService.php";

// ============================================================
// GET: Verificacion del webhook (Meta lo usa para validar)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($mode === 'subscribe' && $token === WHATSAPP_VERIFY_TOKEN) {
        http_response_code(200);
        echo $challenge;
    } else {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Token de verificacion invalido']);
    }
    exit;
}

// ============================================================
// POST: Recepcion de mensajes
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metodo no permitido']);
    exit;
}

$input = file_get_contents('php://input');
$payload = json_decode($input, true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Payload invalido']);
    exit;
}

// Log del webhook para debug
error_log("WhatsApp Webhook: " . $input);

// Resolver tenant desde el phone_id del payload
$phoneIdPayload = '';
if (!empty($payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'])) {
    $phoneIdPayload = $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'];
}
if ($phoneIdPayload) {
    // Buscar qué negocio tiene este phone_id configurado
    $sqlTenant = "SELECT negocio_id FROM configuracion_soporte WHERE clave = 'whatsapp_phone_id' AND valor = ? LIMIT 1";
    $resTenant = ejecutarConsulta($sqlTenant, 's', [$phoneIdPayload]);
    if ($resTenant && $rowTenant = $resTenant->fetch_object()) {
        Tenant::setId($rowTenant->negocio_id);
    }
}

// Verificar que se resolvió un tenant válido
if (!Tenant::id() || !Tenant::negocio()) {
    error_log("Webhook WhatsApp: no se pudo resolver tenant para phone_id=$phoneIdPayload");
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

try {
    $whatsapp = new WhatsAppService();
    $conversacion = new ConversacionService();

    // Procesar el webhook (extrae mensajes y actualizaciones)
    $mensajes = $whatsapp->procesarWebhook($payload);

    if (!empty($mensajes)) {
        foreach ($mensajes as $msg) {
            // Marcar como leido
            if (!empty($msg['message_id'])) {
                $whatsapp->marcarComoLeido($msg['message_id']);
            }

            // Procesar mensaje entrante con el flujo conversacional
            $conversacion->procesarMensajeEntrante(
                $msg['telefono'],
                $msg['contenido'],
                $msg['button_id'] ?? null
            );
        }
    }

    // WhatsApp espera respuesta 200 siempre
    http_response_code(200);
    echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
    error_log("Error webhook WhatsApp: " . $e->getMessage());
    // Responder 200 para que WhatsApp no reintente
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
}
