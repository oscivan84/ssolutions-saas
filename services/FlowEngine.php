<?php
/**
 * FlowEngine — Motor de flujo conversacional configurable.
 *
 * Reemplaza el switch hardcodeado de ConversacionService.
 * Lee el flujo desde JSON (tabla flujos_conversacion) y ejecuta las transiciones.
 *
 * Cada estado del flujo tiene:
 * - mensaje: texto a enviar (con variables {nombre}, {empresa}, {servicio}, etc.)
 * - opciones: botones o lista para WhatsApp
 * - transiciones: mapa intención → siguiente estado
 * - accion: acción a ejecutar al entrar (confirmar_servicio, confirmar_cita, etc.)
 * - es_final: si es estado terminal
 *
 * Uso:
 *   $engine = new FlowEngine($flujoJson, $contexto);
 *   $resultado = $engine->procesar($estadoActual, $intencion);
 *   // $resultado = ['estado_nuevo' => '...', 'mensaje' => '...', 'opciones' => [...], 'accion' => '...']
 */

require_once __DIR__ . '/../modules/ModuleLoader.php';
require_once __DIR__ . '/../config/tenant.php';
require_once __DIR__ . '/FlowValidator.php';

class FlowEngine {

    private array $flujo;
    private array $contexto;
    private ModuleLoader $loader;

    public function __construct(array $flujoJson = null, array $contexto = []) {
        $this->loader = ModuleLoader::getInstance();
        $candidato = $flujoJson ?: $this->loader->getFlujoConversacion();

        // Validar flujo antes de usarlo
        if ($candidato) {
            $validacion = FlowValidator::validar($candidato);
            if (!$validacion['valido']) {
                error_log("FlowEngine: flujo inválido — " . implode(', ', $validacion['errores']));
                $candidato = null; // Usar default
            }
        }

        $this->flujo = $candidato ?: $this->flujoDefault();
        $this->contexto = $contexto;
    }

    /**
     * Procesar una transición en el flujo
     *
     * @param string $estadoActual Estado actual de la conversación
     * @param string $intencion Intención clasificada del mensaje
     * @param string|null $seleccion Selección específica (slug de servicio, botón, etc.)
     * @return array ['estado_nuevo', 'mensaje', 'opciones', 'accion', 'es_final']
     */
    public function procesar(string $estadoActual, string $intencion, ?string $seleccion = null): array {
        $estados = $this->flujo['estados'] ?? [];
        $estado = $estados[$estadoActual] ?? null;

        if (!$estado) {
            // Estado no encontrado → ir a inicio
            return $this->procesarEstado('inicio', $intencion, $seleccion);
        }

        return $this->procesarEstado($estadoActual, $intencion, $seleccion);
    }

    private function procesarEstado(string $estadoKey, string $intencion, ?string $seleccion): array {
        $estados = $this->flujo['estados'] ?? [];
        $estado = $estados[$estadoKey] ?? [];

        // 1. Determinar siguiente estado
        $transiciones = $estado['transiciones'] ?? [];
        $siguienteEstado = $transiciones[$intencion]
            ?? ($seleccion ? ($transiciones['servicio_seleccionado'] ?? null) : null)
            ?? $transiciones['default']
            ?? $estadoKey;

        // Si el siguiente es "respuesta_ia" → no cambia estado, delega a IA
        if ($siguienteEstado === 'respuesta_ia') {
            return [
                'estado_nuevo' => $estadoKey,
                'mensaje' => null, // Señal para usar IA
                'usa_ia' => true,
                'opciones' => [],
                'accion' => null,
                'es_final' => false
            ];
        }

        // 2. Obtener datos del estado destino
        $destino = $estados[$siguienteEstado] ?? [];

        // 3. Construir mensaje
        $mensaje = $destino['mensaje_bienvenida'] ?? $destino['mensaje'] ?? '';
        $mensaje = $this->reemplazarVariables($mensaje, $seleccion);

        // 4. Construir opciones
        $opciones = [];
        if (isset($destino['opciones'])) {
            $opciones = $destino['opciones'];
        }
        if (isset($destino['opciones_from']) && $destino['opciones_from'] === 'servicios_negocio') {
            $opciones = [];
            foreach ($this->loader->getServicios() as $s) {
                $opciones[] = $s['nombre'];
            }
        }
        if (isset($destino['tipo_input']) && $destino['tipo_input'] === 'lista_servicios') {
            $mensaje .= "\n\n" . $this->loader->getServiciosTexto();
        }

        return [
            'estado_nuevo' => $siguienteEstado,
            'mensaje' => $mensaje,
            'opciones' => $opciones,
            'accion' => $destino['accion'] ?? null,
            'es_final' => $destino['es_final'] ?? false,
            'usa_ia' => false
        ];
    }

    /**
     * Obtener estado inicial del flujo
     */
    public function getEstadoInicial(): string {
        return 'inicio';
    }

    /**
     * Verificar si un estado es final
     */
    public function esFinal(string $estado): bool {
        $estados = $this->flujo['estados'] ?? [];
        return ($estados[$estado]['es_final'] ?? false) === true;
    }

    /**
     * Reemplazar variables en el mensaje
     */
    private function reemplazarVariables(string $mensaje, ?string $seleccion = null): string {
        $empresa = Tenant::config('empresa_nombre', 'SSolutions');
        $moneda = Tenant::config('moneda_simbolo', '$');
        $nombre = $this->contexto['nombre_cliente'] ?? $this->contexto['cliente'] ?? 'Cliente';

        $vars = [
            '{nombre}' => $nombre,
            '{empresa}' => $empresa,
            '{moneda}' => $moneda,
        ];

        // Si hay servicio seleccionado
        if ($seleccion) {
            $servicio = $this->loader->getServicio($seleccion);
            if ($servicio) {
                $vars['{servicio}'] = $servicio['nombre'];
                $vars['{precio}'] = number_format($servicio['precio_base'], 0);
                $vars['{duracion}'] = $servicio['duracion_minutos'];
            }
        }

        // Variables de contexto
        if (!empty($this->contexto['codigo_ticket'])) {
            $vars['{ticket}'] = $this->contexto['codigo_ticket'];
        }
        if (!empty($this->contexto['costo_estimado'])) {
            $vars['{costo}'] = $moneda . number_format(floatval($this->contexto['costo_estimado']), 0);
        }

        return str_replace(array_keys($vars), array_values($vars), $mensaje);
    }

    /**
     * Flujo default hardcodeado (fallback si no hay JSON en BD)
     */
    private function flujoDefault(): array {
        return [
            'estados' => [
                'inicio' => [
                    'mensaje_bienvenida' => 'Hola {nombre}! Bienvenido a {empresa}. En que podemos ayudarte?',
                    'transiciones' => [
                        'ACEPTAR_SERVICIO' => 'seleccion_servicio',
                        'CONSULTAR_PRECIO' => 'mostrar_precios',
                        'default' => 'esperando_respuesta'
                    ]
                ],
                'esperando_respuesta' => [
                    'transiciones' => [
                        'ACEPTAR_SERVICIO' => 'seleccion_servicio',
                        'RECHAZAR_SERVICIO' => 'cerrada',
                        'CONSULTAR_PRECIO' => 'mostrar_precios',
                        'default' => 'respuesta_ia'
                    ]
                ],
                'mostrar_precios' => [
                    'mensaje' => 'Nuestras tarifas:',
                    'tipo_input' => 'lista_servicios',
                    'transiciones' => ['ACEPTAR_SERVICIO' => 'cotizacion', 'default' => 'esperando_respuesta']
                ],
                'seleccion_servicio' => [
                    'mensaje' => 'Que servicio necesitas?',
                    'opciones_from' => 'servicios_negocio',
                    'transiciones' => ['servicio_seleccionado' => 'cotizacion']
                ],
                'cotizacion' => [
                    'mensaje' => 'Servicio: {servicio}\nCosto: {moneda}{precio}\n\nConfirmas?',
                    'transiciones' => ['ACEPTAR_SERVICIO' => 'confirmado', 'RECHAZAR_SERVICIO' => 'cerrada']
                ],
                'confirmado' => [
                    'mensaje' => 'Excelente! Servicio confirmado. Te contactaremos pronto.',
                    'accion' => 'confirmar_servicio',
                    'es_final' => true
                ],
                'cerrada' => [
                    'mensaje' => 'Gracias por contactarnos. Escribe cuando necesites.',
                    'es_final' => true
                ]
            ]
        ];
    }
}
