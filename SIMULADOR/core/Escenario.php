<?php
/**
 * Escenario — Define un flujo de conversación con reglas de comportamiento.
 *
 * Cada escenario tiene:
 * - config del cliente (interés, paciencia, etc.)
 * - mensaje inicial
 * - reglas de respuesta por turno
 * - condiciones de éxito/abandono
 */

class Escenario {

    public string $nombre;
    public string $descripcion;
    public array $config_cliente;
    public string $mensaje_inicial;
    public array $respuestas;          // respuestas por turno o por trigger
    public string $condicion_exito;    // qué estado = éxito
    public int $max_turnos;

    public static function cargar(string $nombre): self {
        $archivo = __DIR__ . "/../escenarios/{$nombre}.php";
        if (!file_exists($archivo)) {
            throw new \Exception("Escenario no encontrado: $nombre");
        }
        $data = require $archivo;

        $esc = new self();
        $esc->nombre = $data['nombre'] ?? $nombre;
        $esc->descripcion = $data['descripcion'] ?? '';
        $esc->config_cliente = $data['config_cliente'] ?? [];
        $esc->mensaje_inicial = $data['mensaje_inicial'] ?? 'Hola';
        $esc->respuestas = $data['respuestas'] ?? [];
        $esc->condicion_exito = $data['condicion_exito'] ?? 'aceptado';
        $esc->max_turnos = $data['max_turnos'] ?? 10;

        return $esc;
    }

    /**
     * Obtener respuesta del cliente para el turno actual
     */
    public function obtenerRespuesta(int $turno, string $respuestaBot, string $estadoCliente): ?string {
        // 1. Buscar respuesta por trigger (palabras clave en respuesta del bot)
        foreach ($this->respuestas as $regla) {
            if (isset($regla['trigger'])) {
                $triggers = is_array($regla['trigger']) ? $regla['trigger'] : [$regla['trigger']];
                foreach ($triggers as $trigger) {
                    if (stripos($respuestaBot, $trigger) !== false) {
                        return $this->elegirRespuesta($regla);
                    }
                }
            }
        }

        // 2. Buscar respuesta por turno
        foreach ($this->respuestas as $regla) {
            if (isset($regla['turno']) && $regla['turno'] === $turno) {
                return $this->elegirRespuesta($regla);
            }
        }

        // 3. Respuesta por defecto según estado
        $defaults = [
            'inicio' => 'ok',
            'conversando' => 'entiendo',
            'interesado' => 'me interesa',
        ];

        return $defaults[$estadoCliente] ?? null;
    }

    private function elegirRespuesta(array $regla): string {
        if (isset($regla['opciones']) && is_array($regla['opciones'])) {
            return $regla['opciones'][array_rand($regla['opciones'])];
        }
        return $regla['respuesta'] ?? 'ok';
    }

    /**
     * Listar escenarios disponibles
     */
    public static function listar(): array {
        $dir = __DIR__ . '/../escenarios/';
        $archivos = glob($dir . '*.php');
        $nombres = [];
        foreach ($archivos as $f) {
            $nombres[] = pathinfo($f, PATHINFO_FILENAME);
        }
        return $nombres;
    }
}
