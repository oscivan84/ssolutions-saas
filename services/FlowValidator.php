<?php
/**
 * FlowValidator — Valida flujos conversacionales JSON antes de guardarlos.
 *
 * Detecta:
 * - Estados que referencian estados inexistentes
 * - Estados sin transiciones (dead ends)
 * - Estados inalcanzables (no referenciados)
 * - Mensajes vacíos
 * - Falta de estado 'inicio' o 'cerrada'
 * - Loops infinitos (ciclos sin salida)
 *
 * Uso:
 *   $resultado = FlowValidator::validar($flujoJson);
 *   if (!$resultado['valido']) { // mostrar $resultado['errores'] }
 */

class FlowValidator {

    /**
     * Validar flujo completo. Retorna ['valido' => bool, 'errores' => [], 'warnings' => []]
     */
    public static function validar($flujoJson): array {
        $errores = [];
        $warnings = [];

        // 1. Parsear JSON si es string
        if (is_string($flujoJson)) {
            $flujo = json_decode($flujoJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['valido' => false, 'errores' => ['JSON inválido: ' . json_last_error_msg()], 'warnings' => []];
            }
        } else {
            $flujo = $flujoJson;
        }

        if (!is_array($flujo)) {
            return ['valido' => false, 'errores' => ['Flujo debe ser un objeto/array'], 'warnings' => []];
        }

        $estados = $flujo['estados'] ?? null;
        if (!$estados || !is_array($estados)) {
            return ['valido' => false, 'errores' => ['Falta campo "estados" (objeto con definiciones)'], 'warnings' => []];
        }

        $nombresEstados = array_keys($estados);

        // 2. Estado 'inicio' obligatorio
        if (!isset($estados['inicio'])) {
            $errores[] = 'Falta estado "inicio" (obligatorio como punto de entrada)';
        }

        // 3. Al menos un estado final
        $tieneEstadoFinal = false;
        foreach ($estados as $nombre => $def) {
            if (!empty($def['es_final'])) $tieneEstadoFinal = true;
        }
        if (!$tieneEstadoFinal) {
            $errores[] = 'No hay ningún estado con "es_final": true — conversaciones nunca terminan';
        }

        // 4. Validar cada estado
        $estadosReferenciados = ['inicio']; // inicio siempre es alcanzable

        foreach ($estados as $nombre => $def) {
            if (!is_array($def)) {
                $errores[] = "Estado '$nombre': debe ser un objeto, no " . gettype($def);
                continue;
            }

            // 4a. Validar estructura del estado
            $resultado = self::validarEstado($nombre, $def, $nombresEstados);
            $errores = array_merge($errores, $resultado['errores']);
            $warnings = array_merge($warnings, $resultado['warnings']);

            // 4b. Rastrear estados referenciados
            $transiciones = $def['transiciones'] ?? [];
            foreach ($transiciones as $intencion => $destino) {
                if ($destino !== 'respuesta_ia' && !in_array($destino, $estadosReferenciados)) {
                    $estadosReferenciados[] = $destino;
                }
            }
        }

        // 5. Detectar estados inalcanzables
        foreach ($nombresEstados as $nombre) {
            if ($nombre !== 'inicio' && !in_array($nombre, $estadosReferenciados)) {
                $warnings[] = "Estado '$nombre' nunca es referenciado por ninguna transición (inalcanzable)";
            }
        }

        // 6. Detectar loops simples (estado que solo se apunta a sí mismo)
        foreach ($estados as $nombre => $def) {
            $transiciones = $def['transiciones'] ?? [];
            $destinos = array_unique(array_values($transiciones));
            if (count($destinos) === 1 && $destinos[0] === $nombre && empty($def['es_final'])) {
                $warnings[] = "Estado '$nombre' solo se apunta a sí mismo (loop infinito potencial)";
            }
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores,
            'warnings' => $warnings,
            'stats' => [
                'total_estados' => count($nombresEstados),
                'estados_finales' => count(array_filter($estados, fn($d) => !empty($d['es_final']))),
                'estados_alcanzables' => count(array_unique($estadosReferenciados)),
            ]
        ];
    }

    /**
     * Validar un estado individual
     */
    private static function validarEstado(string $nombre, array $def, array $estadosValidos): array {
        $errores = [];
        $warnings = [];

        // Estado final no necesita transiciones
        if (!empty($def['es_final'])) {
            if (empty($def['mensaje']) && empty($def['mensaje_bienvenida'])) {
                $warnings[] = "Estado final '$nombre': sin mensaje de cierre";
            }
            return ['errores' => $errores, 'warnings' => $warnings];
        }

        // Transiciones obligatorias para estados no finales
        $transiciones = $def['transiciones'] ?? [];
        if (empty($transiciones)) {
            $errores[] = "Estado '$nombre': sin transiciones definidas (dead end — no es final pero no lleva a ningún lado)";
        }

        // Validar que cada destino existe
        $estadosEspeciales = ['respuesta_ia']; // No son estados reales
        foreach ($transiciones as $intencion => $destino) {
            if (!in_array($destino, $estadosEspeciales) && !in_array($destino, $estadosValidos)) {
                $errores[] = "Estado '$nombre' → transición '$intencion' apunta a '$destino' que NO EXISTE";
            }
        }

        // Debe tener 'default' o cubrir intenciones principales
        if (!isset($transiciones['default'])) {
            $warnings[] = "Estado '$nombre': sin transición 'default' — mensajes no clasificados no tendrán respuesta";
        }

        // Validar mensaje
        $mensaje = $def['mensaje'] ?? $def['mensaje_bienvenida'] ?? '';
        if (empty($mensaje) && empty($def['tipo_input'])) {
            $warnings[] = "Estado '$nombre': sin mensaje ni tipo_input — el usuario no verá nada";
        }

        // Validar opciones_from
        if (isset($def['opciones_from'])) {
            $validos = ['servicios_negocio'];
            if (!in_array($def['opciones_from'], $validos)) {
                $errores[] = "Estado '$nombre': opciones_from='{$def['opciones_from']}' no es válido (usar: " . implode(', ', $validos) . ")";
            }
        }

        return ['errores' => $errores, 'warnings' => $warnings];
    }

    /**
     * Validar un prompt de IA
     */
    public static function validarPrompt(string $prompt): array {
        $errores = [];
        $warnings = [];

        if (empty(trim($prompt))) {
            $errores[] = 'Prompt vacío';
            return ['valido' => false, 'errores' => $errores, 'warnings' => $warnings];
        }

        $len = mb_strlen($prompt);

        if ($len < 20) {
            $errores[] = "Prompt demasiado corto ($len chars) — mínimo 20";
        }

        if ($len > 5000) {
            $warnings[] = "Prompt muy largo ($len chars) — puede aumentar costos de IA";
        }

        // Verificar que tiene instrucciones clave para ventas
        $keywords = ['objetivo', 'regla', 'respuesta', 'cliente'];
        $found = 0;
        foreach ($keywords as $kw) {
            if (stripos($prompt, $kw) !== false) $found++;
        }
        if ($found < 2) {
            $warnings[] = 'Prompt no parece tener estructura clara (falta: objetivo, reglas, instrucciones)';
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores,
            'warnings' => $warnings,
            'stats' => ['longitud' => $len, 'keywords_encontradas' => $found]
        ];
    }
}
