<?php
/**
 * MotorConversacion — Orquesta el ida y vuelta entre cliente simulado y el sistema.
 *
 * Flujo por turno:
 * 1. Cliente envía mensaje
 * 2. Sistema procesa y responde
 * 3. Motor evalúa respuesta del bot
 * 4. Motor decide siguiente mensaje del cliente
 * 5. Actualiza estado del cliente
 */

require_once __DIR__ . '/ClienteSimulado.php';
require_once __DIR__ . '/Escenario.php';

class MotorConversacion {

    private ClienteSimulado $cliente;
    private Escenario $escenario;
    private $servicio;
    private array $log = [];

    // Tracking de calidad
    private array $mismatches = [];      // Promesas de anuncio no cumplidas
    private array $ventasPerdidas = [];  // Dinero perdido detectado
    private int $respuestasGenericas = 0;

    public function __construct(ClienteSimulado $cliente, Escenario $escenario, $servicio) {
        $this->cliente = $cliente;
        $this->escenario = $escenario;
        $this->servicio = $servicio;
    }

    /**
     * Ejecutar conversación completa
     * Retorna el cliente con su estado final
     */
    public function ejecutar(): ClienteSimulado {
        $this->log("Iniciando simulación: {$this->escenario->nombre} con {$this->cliente->nombre}");

        // Turno 0: mensaje inicial del cliente
        $mensajeCliente = $this->escenario->mensaje_inicial;

        for ($turno = 1; $turno <= $this->escenario->max_turnos; $turno++) {
            // 1. Cliente envía mensaje
            $this->log("  [T$turno] Cliente: $mensajeCliente");
            $this->cliente->registrarMensaje('enviado', $mensajeCliente);

            // 2. Sistema procesa y responde
            $respuestaBot = $this->servicio->enviarYRecibir(
                $this->cliente->telefono,
                $mensajeCliente
            );

            if ($respuestaBot === null) {
                $this->log("  [T$turno] ERROR: Sistema no respondió");
                $this->cliente->estado = 'error';
                break;
            }

            $this->log("  [T$turno] Bot: " . mb_substr($respuestaBot, 0, 100) . (strlen($respuestaBot) > 100 ? '...' : ''));
            $this->cliente->registrarMensaje('recibido', $respuestaBot);

            // 3. Detectar problemas de calidad en la respuesta
            $this->analizarCalidadRespuesta($respuestaBot, $mensajeCliente, $turno);

            // 4. Actualizar estado del cliente según respuesta del bot
            $this->actualizarEstado($respuestaBot, $turno);

            // 5. Si el cliente ya terminó (aceptó o abandonó)
            if (in_array($this->cliente->estado, ['aceptado', 'abandonado', 'error'])) {
                // Detectar venta perdida si abandonó estando interesado
                if ($this->cliente->estado === 'abandonado') {
                    $this->detectarVentaPerdida($respuestaBot, $turno);
                }
                break;
            }

            // 5. Decidir si el cliente responde
            if (!$this->cliente->debeResponder()) {
                $this->log("  [T$turno] Cliente abandonó (paciencia agotada o no responde)");
                $this->cliente->estado = 'abandonado';
                break;
            }

            // 6. Obtener siguiente mensaje del cliente
            $mensajeCliente = $this->escenario->obtenerRespuesta(
                $turno,
                $respuestaBot,
                $this->cliente->estado
            );

            if ($mensajeCliente === null) {
                $this->log("  [T$turno] Sin respuesta definida — fin de escenario");
                break;
            }
        }

        $this->log("Resultado: {$this->cliente->estado} ({$this->cliente->mensajes_enviados} msgs, " .
            round($this->cliente->duracion(), 2) . "s)");

        return $this->cliente;
    }

    /**
     * Actualizar estado del cliente según lo que dijo el bot
     */
    private function actualizarEstado(string $respuestaBot, int $turno) {
        $resp = mb_strtolower($respuestaBot);

        // Detectar si el bot pidió confirmación → cliente está interesado
        if (preg_match('/confirmar|aceptar|agendar|proceder|agendamos/i', $resp)) {
            if ($this->cliente->nivel_interes === 'alto') {
                $this->cliente->estado = 'aceptado';
                return;
            }
            $this->cliente->estado = 'interesado';
        }

        // Detectar si el bot dio precio
        if (preg_match('/\$[\d,.]+|costo|precio|tarifa/i', $resp)) {
            if ($this->cliente->sensibilidad_precio === 'alta' && $this->cliente->nivel_interes !== 'alto') {
                $this->cliente->paciencia -= 2; // Se asusta con el precio
            }
        }

        // Si lleva muchos turnos sin avanzar
        if ($turno >= 3 && $this->cliente->estado === 'inicio') {
            $this->cliente->estado = 'conversando';
        }
    }

    /**
     * Analizar calidad de la respuesta del bot vs expectativa del cliente
     */
    private function analizarCalidadRespuesta(string $respuestaBot, string $mensajeCliente, int $turno) {
        $resp = mb_strtolower($respuestaBot);
        $msg = mb_strtolower($mensajeCliente);

        // 1. Detectar mismatch de promesa de anuncio
        // Cliente menciona algo que vio en anuncio → bot no lo confirma
        if (preg_match('/anuncio|vi que|decía|dice|publicidad|promo/', $msg)) {
            // ¿El cliente menciona un precio específico?
            if (preg_match('/\$?([\d.,]+)\s*(mil)?/', $msg, $precioMatch)) {
                $precioEsperado = $precioMatch[0];
                // ¿El bot confirma ese precio o da otro?
                if (!preg_match('/'. preg_quote($precioEsperado, '/') . '/', $resp) && preg_match('/\$[\d,.]+/', $resp)) {
                    $this->mismatches[] = [
                        'turno' => $turno,
                        'tipo' => 'precio',
                        'esperado' => $precioEsperado,
                        'mensaje_cliente' => mb_substr($mensajeCliente, 0, 100),
                        'respuesta_bot' => mb_substr($respuestaBot, 0, 100)
                    ];
                    $this->log("  [MISMATCH] Precio: cliente esperaba $precioEsperado");
                }
            }
            // ¿Menciona turnos/disponibilidad?
            if (preg_match('/turno|disponib|cupo|lugar/', $msg) && preg_match('/no\s+(hay|queda|tene)|agotad|esperar/', $resp)) {
                $this->mismatches[] = [
                    'turno' => $turno,
                    'tipo' => 'disponibilidad',
                    'mensaje_cliente' => mb_substr($mensajeCliente, 0, 100),
                    'respuesta_bot' => mb_substr($respuestaBot, 0, 100)
                ];
                $this->log("  [MISMATCH] Disponibilidad: anuncio prometía turnos pero bot dice no hay");
            }
        }

        // 2. Detectar respuesta genérica del bot (señal de fallback)
        if (preg_match('/asesor.*revisar|te responder.*pronto|te contactar.*pronto/', $resp)) {
            $this->respuestasGenericas++;
            if ($this->respuestasGenericas >= 2) {
                $this->ventasPerdidas[] = [
                    'turno' => $turno,
                    'razon' => 'respuesta_generica_repetida',
                    'detalle' => 'Bot dio respuesta genérica ' . $this->respuestasGenericas . ' veces — cliente pierde confianza'
                ];
            }
        }
    }

    /**
     * Detectar si se perdió una venta potencial
     */
    private function detectarVentaPerdida(string $ultimaRespuesta, int $turno) {
        $estadoPrevio = $this->cliente->estado;

        // Cliente estaba interesado o conversando → abandonó = venta perdida
        if (in_array($estadoPrevio, ['interesado', 'conversando'])) {
            $razon = 'abandono_tras_interes';

            // ¿Por qué abandonó?
            if ($this->cliente->paciencia <= 0) {
                $razon = 'paciencia_agotada';
            }
            if ($this->respuestasGenericas >= 2) {
                $razon = 'respuestas_genericas';
            }
            if (!preg_match('/agendar|confirmar|agendamos|proceder/', mb_strtolower($ultimaRespuesta))) {
                $razon = 'falta_cierre_directo';
            }

            $this->ventasPerdidas[] = [
                'turno' => $turno,
                'razon' => $razon,
                'cliente_estaba' => $estadoPrevio,
                'nivel_interes' => $this->cliente->nivel_interes,
                'detalle' => "Cliente {$this->cliente->nombre} abandonó en turno $turno estando '$estadoPrevio'"
            ];
            $this->log("  [VENTA PERDIDA] $razon — cliente estaba '$estadoPrevio'");
        }
    }

    /**
     * Obtener análisis de calidad extendido
     */
    public function getAnalisisCalidad(): array {
        return [
            'mismatches' => $this->mismatches,
            'ventas_perdidas' => $this->ventasPerdidas,
            'respuestas_genericas' => $this->respuestasGenericas,
            'promesa_anuncio_cumplida' => empty($this->mismatches),
            'ad_promise_match_score' => empty($this->mismatches) ? 1.0 : round(1.0 - (count($this->mismatches) * 0.25), 2)
        ];
    }

    private function log(string $msg) {
        $this->log[] = date('H:i:s') . " $msg";
    }

    public function getLog(): array {
        return $this->log;
    }
}
