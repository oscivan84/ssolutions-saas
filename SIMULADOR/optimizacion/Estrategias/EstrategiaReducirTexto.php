<?php
require_once __DIR__ . '/EstrategiaBase.php';

class EstrategiaReducirTexto extends EstrategiaBase {

    public function nombre(): string { return 'reducir_texto'; }
    public function descripcion(): string { return 'Reduce longitud de respuestas para evitar abandono'; }

    public function esRelevante(array $problemas): bool {
        return in_array('respuesta_larga', $problemas)
            || in_array('conversacion_larga', $problemas)
            || in_array('abandono_temprano', $problemas);
    }

    public function aplicar(string $prompt, array $problemas = []): string {
        // Reducir límite de palabras
        $prompt = preg_replace('/maximo\s+\d+\s+palabras/i', 'maximo 40 palabras', $prompt);

        // Agregar regla de brevedad extrema
        $regla = "\n- BREVEDAD EXTREMA: Maximo 2-3 oraciones por respuesta. "
            . "Si puedes decirlo en 1 oracion, hazlo. "
            . "No repitas informacion que ya diste. "
            . "No uses saludos largos despues del primer mensaje.";

        if (preg_match('/REGLAS:?\s*\n/i', $prompt)) {
            $prompt = preg_replace('/(REGLAS:?\s*\n)/i', "$1$regla\n", $prompt);
        } else {
            $prompt .= "\n$regla";
        }

        return $prompt;
    }
}
