<?php
require_once __DIR__ . '/EstrategiaBase.php';

class EstrategiaCierreDirecto extends EstrategiaBase {

    public function nombre(): string { return 'cierre_directo'; }
    public function descripcion(): string { return 'Fuerza preguntas de decision en cada respuesta'; }

    public function esRelevante(array $problemas): bool {
        return in_array('falta_cierre', $problemas)
            || in_array('interesado_sin_cerrar', $problemas)
            || in_array('conversacion_larga', $problemas);
    }

    public function aplicar(string $prompt, array $problemas = []): string {
        $regla = "\n- OBLIGATORIO: Cada respuesta DEBE terminar con una pregunta de cierre directa. "
            . "Ejemplos: 'Agendamos para hoy o manana?', 'Prefieres remoto o en sitio?', 'Confirmas el servicio?'. "
            . "NUNCA terminar con informacion pasiva.";

        // Buscar sección REGLAS e insertar
        if (preg_match('/REGLAS:?\s*\n/i', $prompt)) {
            $prompt = preg_replace(
                '/(REGLAS:?\s*\n)/i',
                "$1$regla\n",
                $prompt
            );
        } else {
            $prompt .= "\n\nREGLA CRITICA:$regla";
        }

        // Reducir max palabras para forzar brevedad + cierre
        $prompt = preg_replace('/maximo\s+\d+\s+palabras/i', 'maximo 60 palabras', $prompt);

        return $prompt;
    }
}
