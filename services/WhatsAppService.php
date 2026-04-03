<?php
/**
 * Servicio de WhatsApp Cloud API
 * Maneja envio de mensajes, plantillas y webhook de recepcion
 */

require_once __DIR__ . "/../config/whatsapp.php";
require_once __DIR__ . "/../model/MensajeWhatsApp.php";

class WhatsAppService {

    private $apiUrl;
    private $token;
    private $phoneId;
    private $objMensaje;

    public function __construct() {
        $this->apiUrl = WHATSAPP_API_URL;
        $this->token = WHATSAPP_TOKEN;
        $this->phoneId = WHATSAPP_PHONE_ID;
        $this->objMensaje = new MensajeWhatsApp();
    }

    /**
     * Verificar si WhatsApp esta configurado
     */
    public function estaConfigurado() {
        return !empty($this->token) && !empty($this->phoneId);
    }

    /**
     * Enviar mensaje de texto simple con reintentos automáticos.
     * Si falla después de 3 intentos → encola como job + genera alerta.
     */
    public function enviarTexto($telefono, $mensaje, $idticket = null, $idpersona = null) {
        if (!$this->estaConfigurado()) {
            // Si no está configurado, encolar para cuando se configure
            $this->encolarMensajeFallido($telefono, $mensaje, $idticket, $idpersona, 'WhatsApp no configurado');
            return false;
        }

        $telefono = $this->formatearTelefono($telefono);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $telefono,
            'type' => 'text',
            'text' => ['body' => $mensaje]
        ];

        // UN solo intento síncrono (sin sleep — no bloquear webhook)
        $response = $this->enviarRequest($payload);
        $messageId = $response['messages'][0]['id'] ?? null;
        $estado = $messageId ? 'enviado' : 'fallido';

        // Registrar en BD
        $this->objMensaje->Registrar(
            $idticket, $idpersona, $telefono, 'enviado', 'texto',
            $mensaje, $messageId, $estado
        );

        // Si falló → encolar reintento asíncrono via JobQueue (NO sleep)
        if (!$messageId) {
            $this->encolarMensajeFallido($telefono, $mensaje, $idticket, $idpersona, 'Fallo primer intento');
        }

        return $messageId !== null;
    }

    /**
     * Encolar mensaje fallido como job para reintento posterior + generar alerta
     */
    private function encolarMensajeFallido($telefono, $mensaje, $idticket, $idpersona, $razon) {
        // 1. Crear job de reintento (2 min delay, max 5 intentos = cubre outage ~2h)
        require_once __DIR__ . '/JobQueue.php';
        JobQueue::crear('enviar_whatsapp', [
            'telefono' => $telefono,
            'mensaje' => $mensaje,
            'idticket' => $idticket,
            'idpersona' => $idpersona,
            'razon_fallo' => $razon,
            'es_reintento' => true
        ], 'alta', 2); // 2 minutos, retry exponencial lo lleva a 2→5→15→30→60 min

        // 2. Generar notificación de alerta
        require_once __DIR__ . '/NotificacionService.php';
        NotificacionService::crear('alerta',
            "Fallo envio WhatsApp",
            "No se pudo enviar mensaje a $telefono. Razon: $razon. Reintento programado.",
            'soporte_automatizacion'
        );

        // 3. Log
        error_log("WhatsApp FALLO: $telefono — $razon — reintento encolado");
    }

    /**
     * Enviar mensaje interactivo con botones
     */
    public function enviarBotones($telefono, $textoHeader, $textoBody, $botones, $idticket = null, $idpersona = null) {
        if (!$this->estaConfigurado()) return false;

        $telefono = $this->formatearTelefono($telefono);

        $botonesFormateados = [];
        foreach ($botones as $id => $titulo) {
            $botonesFormateados[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $id,
                    'title' => substr($titulo, 0, 20)
                ]
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $telefono,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'header' => ['type' => 'text', 'text' => $textoHeader],
                'body' => ['text' => $textoBody],
                'action' => ['buttons' => $botonesFormateados]
            ]
        ];

        $response = $this->enviarRequest($payload);
        $messageId = $response['messages'][0]['id'] ?? null;
        $estado = $messageId ? 'enviado' : 'fallido';

        $this->objMensaje->Registrar(
            $idticket, $idpersona, $telefono, 'enviado', 'interactivo',
            $textoBody, $messageId, $estado
        );

        return $messageId !== null;
    }

    /**
     * Enviar mensaje con lista de opciones
     */
    public function enviarLista($telefono, $textoHeader, $textoBody, $textoBoton, $secciones, $idticket = null, $idpersona = null) {
        if (!$this->estaConfigurado()) return false;

        $telefono = $this->formatearTelefono($telefono);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $telefono,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => ['type' => 'text', 'text' => $textoHeader],
                'body' => ['text' => $textoBody],
                'action' => [
                    'button' => $textoBoton,
                    'sections' => $secciones
                ]
            ]
        ];

        $response = $this->enviarRequest($payload);
        $messageId = $response['messages'][0]['id'] ?? null;
        $estado = $messageId ? 'enviado' : 'fallido';

        $this->objMensaje->Registrar(
            $idticket, $idpersona, $telefono, 'enviado', 'interactivo',
            $textoBody, $messageId, $estado
        );

        return $messageId !== null;
    }

    /**
     * Enviar mensaje usando plantilla de BD
     */
    public function enviarDesdePlantilla($nombrePlantilla, $telefono, $variables, $idticket = null, $idpersona = null) {
        require_once __DIR__ . "/../config/tenant.php";
        $sql = "SELECT contenido FROM plantillas_mensaje WHERE nombre = ? AND negocio_id = ? AND activo = 1";
        $result = ejecutarConsulta($sql, 'si', [$nombrePlantilla, Tenant::id()]);

        if (!$result || !($row = $result->fetch_object())) {
            error_log("Plantilla no encontrada: $nombrePlantilla");
            return false;
        }

        $mensaje = $row->contenido;

        // Reemplazar variables en la plantilla
        foreach ($variables as $clave => $valor) {
            $mensaje = str_replace('{' . $clave . '}', $valor, $mensaje);
        }

        return $this->enviarTexto($telefono, $mensaje, $idticket, $idpersona);
    }

    /**
     * Procesar webhook de WhatsApp (mensajes entrantes)
     * Retorna array con datos del mensaje procesado
     */
    public function procesarWebhook($payload) {
        if (empty($payload['entry'])) return null;

        $resultados = [];

        foreach ($payload['entry'] as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];

                // Procesar mensajes entrantes
                if (!empty($value['messages'])) {
                    foreach ($value['messages'] as $msg) {
                        $telefono = $msg['from'] ?? '';
                        $tipo = $msg['type'] ?? 'text';
                        $contenido = '';
                        $buttonId = null;

                        switch ($tipo) {
                            case 'text':
                                $contenido = $msg['text']['body'] ?? '';
                                break;
                            case 'interactive':
                                $interactive = $msg['interactive'] ?? [];
                                if (isset($interactive['button_reply'])) {
                                    $contenido = $interactive['button_reply']['title'] ?? '';
                                    $buttonId = $interactive['button_reply']['id'] ?? '';
                                } elseif (isset($interactive['list_reply'])) {
                                    $contenido = $interactive['list_reply']['title'] ?? '';
                                    $buttonId = $interactive['list_reply']['id'] ?? '';
                                }
                                break;
                            case 'image':
                            case 'document':
                                $contenido = '[' . $tipo . ']';
                                break;
                        }

                        // Registrar mensaje recibido
                        $this->objMensaje->Registrar(
                            null, null, $telefono, 'recibido', 'texto',
                            $contenido, $msg['id'] ?? null, 'entregado'
                        );

                        $resultados[] = [
                            'telefono' => $telefono,
                            'contenido' => $contenido,
                            'tipo' => $tipo,
                            'button_id' => $buttonId,
                            'message_id' => $msg['id'] ?? null,
                            'timestamp' => $msg['timestamp'] ?? time()
                        ];
                    }
                }

                // Procesar actualizaciones de estado
                if (!empty($value['statuses'])) {
                    foreach ($value['statuses'] as $status) {
                        $messageId = $status['id'] ?? '';
                        $estadoWa = $status['status'] ?? '';

                        $mapaEstado = [
                            'sent' => 'enviado',
                            'delivered' => 'entregado',
                            'read' => 'leido',
                            'failed' => 'fallido'
                        ];

                        $estado = $mapaEstado[$estadoWa] ?? $estadoWa;
                        if (!empty($messageId)) {
                            $this->objMensaje->ActualizarEstado($messageId, $estado);
                        }
                    }
                }
            }
        }

        return $resultados;
    }

    /**
     * Marcar mensaje como leido
     */
    public function marcarComoLeido($messageId) {
        if (!$this->estaConfigurado()) return false;

        $payload = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId
        ];

        return $this->enviarRequest($payload) !== null;
    }

    // ============================================================
    // METODOS PRIVADOS
    // ============================================================

    private function formatearTelefono($telefono) {
        // Remover caracteres no numericos excepto +
        $telefono = preg_replace('/[^0-9+]/', '', $telefono);

        // Si no tiene codigo de pais, agregar +57 (Colombia)
        if (strlen($telefono) === 10 && $telefono[0] === '3') {
            $telefono = '57' . $telefono;
        }

        // Remover + si lo tiene
        $telefono = ltrim($telefono, '+');

        return $telefono;
    }

    private function enviarRequest($payload) {
        $url = $this->apiUrl . '/' . $this->phoneId . '/messages';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            error_log("WhatsApp cURL error: " . curl_error($ch));
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("WhatsApp HTTP $httpCode: $response");
            return null;
        }

        return json_decode($response, true);
    }
}
