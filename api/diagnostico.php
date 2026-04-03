<?php
/**
 * API Endpoint: Recibir diagnóstico del agente Python
 * POST /api/diagnostico.php
 *
 * Recibe JSON con datos del diagnóstico, crea registro en BD,
 * genera ticket automáticamente y notifica por WhatsApp.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . "/../config/tenant.php";
require_once __DIR__ . "/../config/security.php";
require_once __DIR__ . "/../model/Diagnostico.php";
require_once __DIR__ . "/../model/Ticket.php";
require_once __DIR__ . "/../services/TicketService.php";
require_once __DIR__ . "/../services/AIService.php";

// Rate limit: 30 req/min por IP
if (!checkRateLimit('api_diagnostico', 30, 60)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Rate limit excedido']);
    exit;
}

// Validar API Key (Tenant resuelve negocio_id desde X-API-Key automáticamente)
$api_key_header = $_SERVER['HTTP_X_API_KEY'] ?? '';
$api_key_db = Tenant::config('api_key_agente', '');

if (!empty($api_key_db) && $api_key_header !== $api_key_db) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'API Key inválida']);
    exit;
}

// Verificar que el negocio está activo
$negocio = Tenant::negocio();
if (!$negocio || $negocio->estado !== 'activo') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Negocio inactivo o no encontrado']);
    exit;
}

// Verificar limite de tickets del plan
if (!Tenant::puedeCrearTicket()) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Limite de tickets del plan alcanzado']);
    exit;
}

// Leer JSON del body
$input = file_get_contents('php://input');
$datos = json_decode($input, true);

if (!$datos) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'JSON inválido']);
    exit;
}

// Validar campos requeridos
$requeridos = ['cliente', 'sistema'];
foreach ($requeridos as $campo) {
    if (empty($datos[$campo])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Campo requerido: $campo"]);
        exit;
    }
}

try {
    $objDiagnostico = new Diagnostico();
    $objTicket = new Ticket();
    $aiService = new AIService();

    $cliente = $datos['cliente'];
    $sistema = $datos['sistema'];
    $cpu = $datos['cpu'] ?? [];
    $ram = $datos['ram'] ?? [];
    $disco = $datos['disco'] ?? [];
    $problemas = $datos['problemas'] ?? [];
    $recomendaciones = $datos['recomendaciones'] ?? [];
    $optimizaciones = $datos['optimizaciones'] ?? [];

    // Buscar si el cliente ya existe por teléfono o email
    $idpersona = null;
    $telefono = $cliente['telefono'] ?? '';
    $email = $cliente['email'] ?? '';

    if (!empty($telefono) || !empty($email)) {
        $clienteExistente = $objDiagnostico->BuscarCliente($telefono, $email);
        if ($clienteExistente && $reg = $clienteExistente->fetch_object()) {
            $idpersona = $reg->idpersona;
        }
    }

    // Determinar nivel de urgencia
    $nivel = determinarUrgencia($cpu, $ram, $disco, $problemas);

    // Registrar diagnóstico
    $result = $objDiagnostico->Registrar(
        $idpersona,
        $cliente['nombre'] ?? 'Sin nombre',
        $telefono,
        $email,
        $sistema['hostname'] ?? 'Desconocido',
        $sistema['os'] ?? 'Desconocido',
        $cpu['modelo'] ?? null,
        $cpu['uso_porcentaje'] ?? null,
        $cpu['nucleos'] ?? null,
        $ram['total_gb'] ?? null,
        $ram['usada_gb'] ?? null,
        $ram['uso_porcentaje'] ?? null,
        $disco['total_gb'] ?? null,
        $disco['usado_gb'] ?? null,
        $disco['uso_porcentaje'] ?? null,
        $datos['temperatura_cpu'] ?? null,
        $datos['procesos_activos'] ?? null,
        json_encode($problemas),
        json_encode($recomendaciones),
        json_encode($optimizaciones),
        $input, // reporte completo
        $nivel
    );

    if (!$result) {
        throw new Exception("Error al guardar diagnóstico");
    }

    $iddiagnostico = ultimoId();

    // Generar resumen con IA (si está configurado)
    $resumen_ia = '';
    try {
        $resumen_ia = $aiService->generarResumenDiagnostico($datos);
        if ($resumen_ia) {
            $objDiagnostico->ActualizarResumenIA($iddiagnostico, $resumen_ia);
        }
    } catch (Exception $e) {
        error_log("IA no disponible: " . $e->getMessage());
    }

    // Crear ticket automático
    $titulo = "Diagnóstico automático - " . ($sistema['hostname'] ?? 'PC');
    $descripcion = generarDescripcionTicket($datos, $resumen_ia);

    $ticketResult = $objTicket->CrearDesdeDiagnostico(
        $iddiagnostico, $idpersona, $titulo, $descripcion, $nivel
    );

    // Notificar por WhatsApp (si está configurado y hay teléfono)
    $whatsapp_enviado = false;
    if (!empty($telefono)) {
        try {
            $ticketService = new TicketService();
            $whatsapp_enviado = $ticketService->notificarClienteWhatsApp(
                $telefono,
                $cliente['nombre'] ?? 'Cliente',
                $ticketResult['codigo'] ?? '',
                $nivel,
                $resumen_ia ?: $descripcion
            );
        } catch (Exception $e) {
            error_log("WhatsApp no enviado: " . $e->getMessage());
        }
    }

    // Respuesta exitosa
    echo json_encode([
        'status' => 'success',
        'message' => 'Diagnóstico recibido y procesado',
        'data' => [
            'iddiagnostico' => $iddiagnostico,
            'ticket' => $ticketResult ?? null,
            'nivel_urgencia' => $nivel,
            'resumen_ia' => $resumen_ia,
            'whatsapp_enviado' => $whatsapp_enviado
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno: ' . $e->getMessage()
    ]);
}

// ============================================================
// Funciones auxiliares
// ============================================================

function determinarUrgencia($cpu, $ram, $disco, $problemas) {
    $score = 0;

    if (($cpu['uso_porcentaje'] ?? 0) > 90) $score += 3;
    elseif (($cpu['uso_porcentaje'] ?? 0) > 75) $score += 1;

    if (($ram['uso_porcentaje'] ?? 0) > 90) $score += 3;
    elseif (($ram['uso_porcentaje'] ?? 0) > 80) $score += 1;

    if (($disco['uso_porcentaje'] ?? 0) > 95) $score += 3;
    elseif (($disco['uso_porcentaje'] ?? 0) > 85) $score += 1;

    $score += count($problemas);

    if ($score >= 8) return 'critica';
    if ($score >= 5) return 'alta';
    if ($score >= 2) return 'media';
    return 'baja';
}

function generarDescripcionTicket($datos, $resumen_ia) {
    $desc = "=== DIAGNÓSTICO AUTOMÁTICO ===\n";
    $desc .= "Equipo: " . ($datos['sistema']['hostname'] ?? 'N/A') . "\n";
    $desc .= "SO: " . ($datos['sistema']['os'] ?? 'N/A') . "\n";
    $desc .= "CPU: " . ($datos['cpu']['uso_porcentaje'] ?? '?') . "% uso\n";
    $desc .= "RAM: " . ($datos['ram']['uso_porcentaje'] ?? '?') . "% uso\n";
    $desc .= "Disco: " . ($datos['disco']['uso_porcentaje'] ?? '?') . "% uso\n\n";

    if (!empty($datos['problemas'])) {
        $desc .= "PROBLEMAS DETECTADOS:\n";
        foreach ($datos['problemas'] as $p) {
            $desc .= "- $p\n";
        }
    }

    if ($resumen_ia) {
        $desc .= "\nRESUMEN IA:\n$resumen_ia\n";
    }

    return $desc;
}
