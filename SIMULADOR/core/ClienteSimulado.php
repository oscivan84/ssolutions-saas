<?php
/**
 * ClienteSimulado — Modelo de un cliente fake con comportamiento configurable.
 *
 * Cada cliente tiene personalidad:
 * - nivel_interes: determina probabilidad de avanzar hacia compra
 * - paciencia: mensajes antes de abandonar
 * - sensibilidad_precio: si el precio lo asusta
 * - probabilidad_respuesta: chance de responder (0.0 - 1.0)
 */

class ClienteSimulado {

    public string $nombre;
    public string $telefono;
    public string $email;
    public string $nivel_interes;      // alto, medio, bajo
    public int $paciencia;             // mensajes max antes de abandonar
    public string $sensibilidad_precio; // alta, media, baja
    public float $probabilidad_respuesta; // 0.0 - 1.0
    public string $problema;           // problema del equipo

    // Estado de la conversación
    public int $mensajes_enviados = 0;
    public int $mensajes_recibidos = 0;
    public string $estado = 'inicio';  // inicio, conversando, interesado, aceptado, abandonado
    public array $historial = [];
    public float $inicio_timestamp;

    // Nombres colombianos realistas para generar clientes
    private static $nombres = [
        'Juan Carlos Perez', 'Maria Fernanda Lopez', 'Andres Felipe Garcia',
        'Laura Valentina Torres', 'Diego Alejandro Rodriguez', 'Camila Andrea Martinez',
        'Santiago Herrera', 'Valentina Gomez', 'Sebastian Ramirez', 'Daniela Moreno',
        'Carlos Eduardo Silva', 'Ana Maria Vargas', 'Miguel Angel Castro', 'Paula Andrea Diaz',
        'David Fernando Ruiz', 'Natalia Cardenas', 'Oscar Mauricio Rojas', 'Carolina Jimenez'
    ];

    private static $problemas = [
        'Mi computador está muy lento',
        'La pantalla de mi laptop se puso negra',
        'No me enciende el PC',
        'Necesito limpiar mi computador de virus',
        'Mi disco duro está lleno',
        'El computador se apaga solo',
        'Necesito más memoria RAM',
        'Mi laptop se calienta mucho',
        'No puedo instalar Windows',
        'El teclado no funciona bien',
    ];

    public static function crear(array $config = []): self {
        $cliente = new self();
        $cliente->nombre = $config['nombre'] ?? self::$nombres[array_rand(self::$nombres)];
        $cliente->telefono = $config['telefono'] ?? '5730' . rand(10000000, 99999999);
        $cliente->email = $config['email'] ?? strtolower(str_replace(' ', '.', $cliente->nombre)) . '@test.com';
        $cliente->nivel_interes = $config['nivel_interes'] ?? 'medio';
        $cliente->paciencia = $config['paciencia'] ?? 5;
        $cliente->sensibilidad_precio = $config['sensibilidad_precio'] ?? 'media';
        $cliente->probabilidad_respuesta = $config['probabilidad_respuesta'] ?? 0.8;
        $cliente->problema = $config['problema'] ?? self::$problemas[array_rand(self::$problemas)];
        $cliente->inicio_timestamp = microtime(true);
        return $cliente;
    }

    /**
     * Decidir si el cliente responde o abandona
     */
    public function debeResponder(): bool {
        // Paciencia agotada → abandona
        if ($this->mensajes_enviados >= $this->paciencia) {
            $this->estado = 'abandonado';
            return false;
        }
        // Probabilidad de respuesta
        return (mt_rand(1, 100) / 100) <= $this->probabilidad_respuesta;
    }

    /**
     * Registrar mensaje en historial
     */
    public function registrarMensaje(string $direccion, string $contenido) {
        $this->historial[] = [
            'direccion' => $direccion,
            'contenido' => $contenido,
            'timestamp' => microtime(true),
            'estado' => $this->estado
        ];
        if ($direccion === 'enviado') $this->mensajes_enviados++;
        else $this->mensajes_recibidos++;
    }

    /**
     * Duración total de la conversación en segundos
     */
    public function duracion(): float {
        return microtime(true) - $this->inicio_timestamp;
    }

    /**
     * Resumen del cliente para reporte
     */
    public function resumen(): array {
        return [
            'nombre' => $this->nombre,
            'telefono' => $this->telefono,
            'nivel_interes' => $this->nivel_interes,
            'estado_final' => $this->estado,
            'mensajes_enviados' => $this->mensajes_enviados,
            'mensajes_recibidos' => $this->mensajes_recibidos,
            'total_mensajes' => count($this->historial),
            'duracion_segundos' => round($this->duracion(), 2),
            'problema' => $this->problema
        ];
    }
}
