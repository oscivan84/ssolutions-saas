<?php
require_once __DIR__ . '/EstrategiaBase.php';

class EstrategiaUrgencia extends EstrategiaBase {

    public function nombre(): string { return 'urgencia'; }
    public function descripcion(): string { return 'Agrega presion de tiempo para acelerar decisiones'; }

    public function esRelevante(array $problemas): bool {
        return in_array('falta_urgencia', $problemas)
            || in_array('abandono_tardio', $problemas)
            || in_array('interesado_sin_cerrar', $problemas);
    }

    public function aplicar(string $prompt, array $problemas = []): string {
        $urgencias = [
            "\n- Mencionar disponibilidad limitada: 'Solo tenemos 2 turnos disponibles hoy'",
            "\n- Crear sentido de urgencia real: 'Este problema puede empeorar si no se atiende esta semana'",
            "\n- Ofrecer beneficio por accion rapida: 'Si agendas hoy, incluimos diagnostico gratis'",
        ];

        // Insertar después de TECNICAS DE CIERRE o al final de REGLAS
        if (stripos($prompt, 'TECNICAS') !== false) {
            $prompt = preg_replace(
                '/(TECNICAS[^\n]*\n)/i',
                "$1\nURGENCIA (usar siempre que sea natural):" . implode('', $urgencias) . "\n",
                $prompt
            );
        } else {
            $prompt .= "\n\nURGENCIA:" . implode('', $urgencias);
        }

        return $prompt;
    }
}
