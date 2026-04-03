<?php
/**
 * WhatsAppFakeService — Simula envío/recepción SIN Meta real.
 *
 * Llama directamente a ConversacionService (modo interno).
 * NO envía mensajes reales. NO toca WhatsApp Cloud API.
 * Intercepta la respuesta que el bot generaría.
 */

// Cargar sistema SSolutions
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/tenant.php';
require_once __DIR__ . '/../../services/ConversacionService.php';
require_once __DIR__ . '/../../services/AIService.php';

class WhatsAppFakeService {

    private ConversacionService $conversacion;
    private AIService $ai;
    private string $ultimaRespuesta = '';
    private ?string $promptOverride = null;

    public function __construct() {
        $this->conversacion = new ConversacionService();
        $this->ai = new AIService();
    }

    /**
     * Inyectar prompt custom para optimización/A/B testing
     */
    public function setPromptOverride(?string $prompt) {
        $this->promptOverride = $prompt;
    }

    /**
     * Simular envío de mensaje y capturar respuesta del bot.
     *
     * En vez de enviar por WhatsApp real:
     * 1. Pasa el mensaje por ConversacionService (como si fuera webhook)
     * 2. Intercepta la respuesta que se hubiera enviado
     *
     * @return string|null Respuesta del bot
     */
    public function enviarYRecibir(string $telefono, string $mensaje): ?string {
        // 1. Buscar contexto existente
        $contexto = $this->construirContexto($telefono);

        // 2. Si hay prompt override (optimización), usarlo como system prompt en contexto
        if ($this->promptOverride) {
            $contexto['_system_prompt_override'] = $this->promptOverride;
        }

        // 3. Clasificar intención
        $intencion = $this->ai->clasificarIntencion($mensaje);

        // 4. Generar respuesta del bot
        $contexto['intencion'] = $intencion;
        $respuesta = $this->ai->responderWhatsApp($mensaje, $contexto);

        if ($respuesta) {
            $this->ultimaRespuesta = $respuesta;
            return $respuesta;
        }

        // Fallback: respuesta genérica del sistema
        return "Gracias por tu mensaje. Un asesor te contactará pronto.";
    }

    /**
     * Construir contexto del cliente simulado
     */
    private function construirContexto(string $telefono): array {
        // Buscar si hay conversación activa en BD para este teléfono
        $sql = "SELECT c.*, t.codigo_ticket, t.estado, t.costo_estimado, t.tipo_servicio,
                       p.nombre AS nombre_cliente
                FROM conversaciones_whatsapp c
                LEFT JOIN tickets t ON c.idticket = t.idticket
                LEFT JOIN persona p ON c.idpersona = p.idpersona
                WHERE c.negocio_id = ? AND c.telefono = ?
                AND c.estado_flujo != 'cerrada'
                ORDER BY c.fecha_ultimo_mensaje DESC LIMIT 1";
        $result = ejecutarConsulta($sql, 'is', [Tenant::id(), $telefono]);
        $conv = $result ? $result->fetch_object() : null;

        if ($conv) {
            return (array)$conv;
        }

        return ['telefono' => $telefono];
    }

    public function getUltimaRespuesta(): string {
        return $this->ultimaRespuesta;
    }
}
