<?php
/**
 * IntegracionRealService — Envía mensajes al webhook real del sistema.
 *
 * MODO REAL: Simula un mensaje de WhatsApp haciendo POST al webhook.
 * El sistema lo procesa como si fuera un mensaje real de Meta.
 *
 * PELIGRO: NO usar con números reales de clientes.
 * Solo con teléfonos fake (57300XXXXXXXX).
 */

class IntegracionRealService {

    private string $webhookUrl;

    public function __construct(string $webhookUrl = null) {
        $this->webhookUrl = $webhookUrl ?: (getenv('WEBHOOK_URL') ?: 'http://localhost/landingV2/api/webhook_whatsapp.php');
    }

    /**
     * Enviar mensaje simulando payload de Meta Cloud API.
     * Después consulta la respuesta enviada por el sistema.
     */
    public function enviarYRecibir(string $telefono, string $mensaje): ?string {
        // Validar que es teléfono fake
        if (!$this->esTelefonoFake($telefono)) {
            throw new \Exception("SEGURIDAD: Solo se permiten teléfonos fake (57300...). Recibido: $telefono");
        }

        // Construir payload como lo envía Meta
        $payload = $this->construirPayloadMeta($telefono, $mensaje);

        // POST al webhook
        $ch = curl_init($this->webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return null;
        }

        // Esperar un momento y consultar la última respuesta enviada
        usleep(500000); // 500ms

        return $this->obtenerUltimaRespuesta($telefono);
    }

    /**
     * Construir payload idéntico al de Meta Cloud API
     */
    private function construirPayloadMeta(string $telefono, string $mensaje): array {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'SIMULADOR',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '15551234567',
                            'phone_number_id' => Tenant::config('whatsapp_phone_id', 'FAKE_PHONE_ID')
                        ],
                        'messages' => [[
                            'from' => $telefono,
                            'id' => 'sim_' . uniqid(),
                            'timestamp' => time(),
                            'type' => 'text',
                            'text' => ['body' => $mensaje]
                        ]]
                    ],
                    'field' => 'messages'
                ]]
            ]]
        ];
    }

    /**
     * Consultar el último mensaje enviado al teléfono simulado
     */
    private function obtenerUltimaRespuesta(string $telefono): ?string {
        require_once __DIR__ . '/../../config/database.php';
        require_once __DIR__ . '/../../config/tenant.php';

        $sql = "SELECT contenido FROM mensajes_whatsapp
                WHERE negocio_id = ? AND telefono = ? AND direccion = 'enviado'
                ORDER BY fecha DESC LIMIT 1";
        $result = ejecutarConsulta($sql, 'is', [Tenant::id(), $telefono]);
        if ($result && $row = $result->fetch_object()) {
            return $row->contenido;
        }
        return null;
    }

    private function esTelefonoFake(string $telefono): bool {
        // Solo permitir teléfonos que empiecen con 57300 (fake)
        return (bool)preg_match('/^5730[0-9]{8,}$/', $telefono);
    }
}
