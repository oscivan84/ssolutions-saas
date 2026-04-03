<?php
/**
 * Servicio de Inteligencia Artificial (v2 — Cliente del microservicio FastAPI)
 *
 * Ya NO llama directamente a Anthropic/OpenAI.
 * Toda la lógica de IA está en ai-service/ (FastAPI).
 * Este archivo es ahora un cliente HTTP ligero.
 *
 * Si el microservicio no está disponible → fallback local (mantiene PHP funcionando).
 */

require_once __DIR__ . "/../config/ai.php";
require_once __DIR__ . "/../config/tenant.php";

class AIService {

    private $provider;
    private $apiKey;
    private $model;
    private $serviceUrl;
    private $internalKey;

    // Circuit breaker: si falla N veces seguidas, skip HTTP por M segundos
    private static int $failureCount = 0;
    private static float $lastFailureTime = 0;
    private const CIRCUIT_BREAKER_THRESHOLD = 3;   // Fallos antes de abrir circuito
    private const CIRCUIT_BREAKER_COOLDOWN = 60;    // Segundos antes de reintentar

    public function __construct() {
        $this->provider = AI_PROVIDER;
        $this->apiKey = AI_API_KEY;
        $this->model = AI_MODEL;
        $this->serviceUrl = getenv('AI_SERVICE_URL') ?: 'http://127.0.0.1:8100';
        $this->internalKey = getenv('AI_SERVICE_INTERNAL_KEY') ?: 'ss-internal-dev-key';
    }

    /**
     * Circuit breaker: si el microservicio falló N veces seguidas, no intentar por M segundos
     */
    private function circuitoAbierto(): bool {
        if (self::$failureCount < self::CIRCUIT_BREAKER_THRESHOLD) {
            return false; // Circuito cerrado, permitir llamadas
        }
        // Circuito abierto — verificar si pasó el cooldown
        if ((microtime(true) - self::$lastFailureTime) > self::CIRCUIT_BREAKER_COOLDOWN) {
            self::$failureCount = 0; // Reset — permitir un intento
            return false;
        }
        return true; // Circuito abierto — skip HTTP
    }

    private function registrarFallo() {
        self::$failureCount++;
        self::$lastFailureTime = microtime(true);
    }

    private function registrarExito() {
        self::$failureCount = 0;
    }

    /**
     * Verificar si el servicio esta configurado
     */
    public function estaConfigurado() {
        return !empty($this->apiKey);
    }

    // ============================================================
    // MÉTODOS PÚBLICOS (misma interfaz que antes)
    // ============================================================

    /**
     * Generar resumen de diagnostico en lenguaje humano
     */
    public function generarResumenDiagnostico($datosDiagnostico) {
        $response = $this->callService('/diagnostico', [
            'datos' => $datosDiagnostico,
            'negocio' => $this->negocioConfig(),
            'ai' => $this->aiConfig()
        ]);

        return $response['respuesta'] ?? $this->generarResumenLocal($datosDiagnostico);
    }

    /**
     * Generar recomendaciones de servicio
     */
    public function generarRecomendacionesServicio($datosDiagnostico, $tipoServicio = 'remoto') {
        $response = $this->callService('/recomendaciones', [
            'datos' => $datosDiagnostico,
            'tipo_servicio' => $tipoServicio,
            'negocio' => $this->negocioConfig(),
            'ai' => $this->aiConfig()
        ]);

        return $response['recomendaciones'] ?? $this->recomendacionesLocales($datosDiagnostico);
    }

    /**
     * Generar respuesta para el bot de WhatsApp (legacy, usa responderWhatsApp)
     */
    public function generarRespuestaBot($mensajeCliente, $contextoTicket = []) {
        return $this->responderWhatsApp($mensajeCliente, $contextoTicket);
    }

    /**
     * Responder WhatsApp con enfoque de ventas (método principal)
     */
    public function responderWhatsApp($mensaje, $contexto = []) {
        $payload = [
            'mensaje' => $mensaje,
            'contexto' => $contexto,
            'negocio' => $this->negocioConfig(),
            'ai' => $this->aiConfig()
        ];

        // Soporte para prompt override (usado por SIMULADOR/optimizador)
        if (!empty($contexto['_system_prompt_override'])) {
            $payload['system_prompt_override'] = $contexto['_system_prompt_override'];
        }

        $response = $this->callService('/responder', $payload);

        return $response['respuesta'] ?? $this->respuestaBotLocal($mensaje, $contexto);
    }

    /**
     * Clasificar intencion del mensaje del cliente
     */
    public function clasificarIntencion($mensaje) {
        $response = $this->callService('/clasificar', [
            'mensaje' => $mensaje,
            'ai' => $this->aiConfig()
        ]);

        $intencion = $response['intencion'] ?? null;
        if ($intencion) return $intencion;

        return $this->clasificarIntencionLocal($mensaje);
    }

    /**
     * Score de cliente: caliente/tibio/frio
     */
    public function scorearCliente($contexto = [], $historial = '') {
        $response = $this->callService('/score-cliente', [
            'contexto' => $contexto,
            'historial' => $historial,
            'ai' => $this->aiConfig()
        ]);

        return $response ?: ['score' => 'tibio', 'razon' => 'Sin datos', 'source' => 'fallback'];
    }

    /**
     * Sugerencia para técnico
     */
    public function sugerirTecnico($ticket = [], $diagnosticoData = []) {
        $response = $this->callService('/sugerencia-tecnico', [
            'ticket' => $ticket,
            'diagnostico_data' => $diagnosticoData,
            'ai' => $this->aiConfig()
        ]);

        return $response ?: ['mensaje_sugerido' => 'Contactar al cliente', 'acciones' => [], 'upsell' => []];
    }

    // ============================================================
    // COMUNICACIÓN CON MICROSERVICIO
    // ============================================================

    private function callService($endpoint, $payload) {
        // Circuit breaker: si el servicio está caído, no intentar
        if ($this->circuitoAbierto()) {
            return null; // Caller usa fallback local
        }

        $url = $this->serviceUrl . $endpoint;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Internal-Key: ' . $this->internalKey
            ],
            CURLOPT_TIMEOUT => 10,      // 10s max — balance entre velocidad y funcionalidad
            CURLOPT_CONNECTTIMEOUT => 3  // 3s para conectar
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            error_log("AIService circuit breaker: " . curl_error($ch));
            $this->registrarFallo();
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("AIService HTTP $httpCode: $response");
            $this->registrarFallo();
            return null;
        }

        $this->registrarExito();

        return json_decode($response, true);
    }

    /**
     * Configuración de IA para pasar al microservicio
     */
    private function aiConfig() {
        return [
            'provider' => $this->provider,
            'api_key' => $this->apiKey,
            'model' => $this->model
        ];
    }

    /**
     * Configuración del negocio para personalizar prompts
     */
    private function negocioConfig() {
        return [
            'empresa_nombre' => Tenant::config('empresa_nombre', 'SSolutions'),
            'moneda_simbolo' => Tenant::config('moneda_simbolo', '$'),
            'costo_hora_remoto' => Tenant::config('costo_hora_remoto', '25000'),
            'costo_hora_sitio' => Tenant::config('costo_hora_sitio', '40000'),
            'costo_hora_taller' => Tenant::config('costo_hora_taller', '30000')
        ];
    }

    // ============================================================
    // FALLBACKS LOCALES (cuando el microservicio no está disponible)
    // ============================================================

    private function generarResumenLocal($datos) {
        $cpu = $datos['cpu'] ?? [];
        $ram = $datos['ram'] ?? [];
        $disco = $datos['disco'] ?? [];
        $problemas = $datos['problemas'] ?? [];

        $resumen = "Reporte de tu equipo:\n\n";

        $cpu_uso = floatval($cpu['uso_porcentaje'] ?? 0);
        if ($cpu_uso > 90) $resumen .= "- Tu procesador esta al limite ({$cpu_uso}%). Causa lentitud.\n";
        elseif ($cpu_uso > 70) $resumen .= "- Tu procesador esta algo exigido ({$cpu_uso}%).\n";
        else $resumen .= "- Tu procesador funciona bien ({$cpu_uso}%).\n";

        $ram_uso = floatval($ram['uso_porcentaje'] ?? 0);
        if ($ram_uso > 85) $resumen .= "- RAM casi llena ({$ram_uso}%). Necesita mas memoria.\n";
        elseif ($ram_uso > 70) $resumen .= "- RAM en uso moderado ({$ram_uso}%).\n";
        else $resumen .= "- RAM en buen estado ({$ram_uso}%).\n";

        $disco_uso = floatval($disco['uso_porcentaje'] ?? 0);
        if ($disco_uso > 90) $resumen .= "- Disco casi lleno ({$disco_uso}%). Liberar espacio.\n";
        elseif ($disco_uso > 75) $resumen .= "- Disco con espacio moderado ({$disco_uso}%).\n";
        else $resumen .= "- Disco con suficiente espacio ({$disco_uso}%).\n";

        if (!empty($problemas)) {
            $resumen .= "\nProblemas: " . implode(', ', $problemas) . ".\n";
        }

        $resumen .= "\nRecomendamos una revision profesional.";
        return $resumen;
    }

    private function recomendacionesLocales($datos) {
        $acciones = [];
        $cpu = floatval($datos['cpu']['uso_porcentaje'] ?? 0);
        $ram = floatval($datos['ram']['uso_porcentaje'] ?? 0);
        $disco = floatval($datos['disco']['uso_porcentaje'] ?? 0);

        if ($cpu > 80) $acciones[] = ['accion' => 'Optimizar procesos', 'prioridad' => $cpu > 90 ? 'alta' : 'media', 'tiempo_estimado_min' => 30, 'requiere_repuesto' => false];
        if ($ram > 85) $acciones[] = ['accion' => 'Ampliacion de RAM', 'prioridad' => 'alta', 'tiempo_estimado_min' => 20, 'requiere_repuesto' => true];
        if ($disco > 85) $acciones[] = ['accion' => 'Limpieza de disco', 'prioridad' => $disco > 95 ? 'alta' : 'media', 'tiempo_estimado_min' => 45, 'requiere_repuesto' => false];
        if (empty($acciones)) $acciones[] = ['accion' => 'Mantenimiento preventivo', 'prioridad' => 'baja', 'tiempo_estimado_min' => 60, 'requiere_repuesto' => false];

        return $acciones;
    }

    private function respuestaBotLocal($mensaje, $contexto) {
        $msg = mb_strtolower($mensaje);

        if (preg_match('/\b(si|acepto|aceptar|ok|dale|confirmo)\b/', $msg)) {
            $codigo = $contexto['codigo_ticket'] ?? '';
            return "Perfecto! Hemos registrado tu solicitud" . ($codigo ? " (Ticket: $codigo)" : "") . ". Un tecnico te contactara pronto.";
        }
        if (preg_match('/\b(no|rechaz|cancel|despues|luego)\b/', $msg)) return "Entendido. Si cambias de opinion, aqui estamos.";
        if (preg_match('/\b(precio|costo|cuanto|valor|cobr)\b/', $msg)) {
            $costo = $contexto['costo_estimado'] ?? 'por definir';
            return "Costo estimado: $" . number_format(floatval($costo), 0) . ". Quieres proceder?";
        }
        if (preg_match('/\b(estado|como va|avance)\b/', $msg)) {
            $estado = ucwords(str_replace('_', ' ', $contexto['estado'] ?? 'en revision'));
            return "Tu ticket esta en: $estado. Te notificaremos de novedades.";
        }
        if (preg_match('/\b(hola|buenos|buenas|hey)\b/', $msg)) {
            $nombre = $contexto['nombre_cliente'] ?? 'cliente';
            return "Hola $nombre! Soy el asistente de SSolutions. En que puedo ayudarte?";
        }

        return "Gracias por tu mensaje. Un asesor te respondera pronto.";
    }

    private function clasificarIntencionLocal($mensaje) {
        $msg = mb_strtolower($mensaje);
        if (preg_match('/\b(si|acepto|ok|dale|confirmo|quiero)\b/', $msg)) return 'ACEPTAR_SERVICIO';
        if (preg_match('/\b(no|rechaz|cancel|despues|luego)\b/', $msg)) return 'RECHAZAR_SERVICIO';
        if (preg_match('/\b(precio|costo|cuanto|valor|cobr)\b/', $msg)) return 'CONSULTAR_PRECIO';
        if (preg_match('/\b(estado|como va|avance|progreso)\b/', $msg)) return 'CONSULTAR_ESTADO';
        if (preg_match('/\b(agendar|cita|cuando|horario)\b/', $msg)) return 'AGENDAR_CITA';
        if (preg_match('/\b(queja|reclamo|molest)\b/', $msg)) return 'QUEJA';
        if (preg_match('/\b(hola|buenos|buenas|hey)\b/', $msg)) return 'SALUDO';
        if (preg_match('/\b(gracias|adios|chao|bye)\b/', $msg)) return 'DESPEDIDA';
        return 'OTRO';
    }
}
