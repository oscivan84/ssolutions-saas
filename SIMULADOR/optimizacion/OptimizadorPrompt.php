<?php
/**
 * OptimizadorPrompt — Orquestador principal de optimización automática.
 *
 * Flujo:
 * 1. Ejecutar simulación base (prompt actual)
 * 2. Analizar problemas
 * 3. Generar variantes
 * 4. Simular cada variante
 * 5. Comparar resultados
 * 6. Seleccionar mejor
 * 7. Guardar nueva versión
 * 8. Repetir N iteraciones
 *
 * Uso:
 *   $opt = new OptimizadorPrompt();
 *   $resultado = $opt->optimizar('interesado', 3); // 3 iteraciones
 */

require_once __DIR__ . '/AnalizadorConversaciones.php';
require_once __DIR__ . '/GeneradorVariantes.php';
require_once __DIR__ . '/EvaluadorComparativo.php';
require_once __DIR__ . '/VersionadorPrompts.php';
require_once __DIR__ . '/../core/Simulador.php';

class OptimizadorPrompt {

    private AnalizadorConversaciones $analizador;
    private GeneradorVariantes $generador;
    private VersionadorPrompts $versionador;
    private int $simulacionesPorVariante;
    private array $logs = [];
    private array $historialIteraciones = [];

    public function __construct(int $simulacionesPorVariante = 30) {
        $this->analizador = new AnalizadorConversaciones();
        $this->generador = new GeneradorVariantes();
        $this->versionador = new VersionadorPrompts();
        $this->simulacionesPorVariante = $simulacionesPorVariante;
    }

    /**
     * Ejecutar optimización completa con N iteraciones
     *
     * @param string $escenario Nombre del escenario para simular
     * @param int $iteraciones Número de rondas de optimización
     * @param string|null $promptInicial Prompt base (null = cargar de BD)
     * @return array Reporte completo de optimización
     */
    public function optimizar(string $escenario = 'interesado', int $iteraciones = 3, ?string $promptInicial = null): array {
        $this->log("========================================");
        $this->log("OPTIMIZACION AUTOMATICA DE PROMPT");
        $this->log("Escenario: $escenario | Iteraciones: $iteraciones | Sims/variante: {$this->simulacionesPorVariante}");
        $this->log("========================================");

        // Obtener prompt actual
        $promptActual = $promptInicial ?: $this->versionador->getActual('ventas') ?: $this->promptDefault();
        $this->log("Prompt inicial: " . strlen($promptActual) . " chars");

        $scoreInicial = null;
        $promptMejor = $promptActual;
        $estrategiasAplicadas = [];

        for ($iter = 1; $iter <= $iteraciones; $iter++) {
            $this->log("\n--- ITERACION $iter/$iteraciones ---");

            $resultado = $this->ejecutarIteracion($escenario, $promptMejor, $iter);

            if ($iter === 1) {
                $scoreInicial = $resultado['score_baseline'];
            }

            $this->historialIteraciones[] = $resultado;

            // Si encontró mejora, usar el nuevo prompt como base
            if ($resultado['mejora_encontrada']) {
                $promptMejor = $resultado['mejor_prompt'];
                $estrategiasAplicadas = array_merge($estrategiasAplicadas, $resultado['estrategias_ganadoras']);
                $this->log("Mejora encontrada! Score: {$resultado['score_baseline']} → {$resultado['score_mejor']}");
            } else {
                $this->log("Sin mejora en esta iteración. Manteniendo prompt actual.");
            }
        }

        // Guardar mejor prompt en BD
        $scoreFinal = end($this->historialIteraciones)['score_mejor'] ?? $scoreInicial;
        $guardado = null;

        if ($scoreFinal > $scoreInicial) {
            $guardado = $this->versionador->guardar($promptMejor, 'ventas', [
                'conversion' => end($this->historialIteraciones)['conversion_mejor'] ?? 0,
                'abandono' => end($this->historialIteraciones)['abandono_mejor'] ?? 0,
                'score' => $scoreFinal,
                'simulaciones' => $this->simulacionesPorVariante * count($this->historialIteraciones),
            ], array_unique($estrategiasAplicadas));

            $this->log("\nPrompt guardado en BD: v{$guardado['version']}");
        } else {
            $this->log("\nSin mejora global. Prompt original mantenido.");
        }

        // Generar reporte
        $mejoraPct = $scoreInicial != 0 ? round((($scoreFinal - $scoreInicial) / abs($scoreInicial)) * 100, 1) : 0;
        $convInicial = $this->historialIteraciones[0]['conversion_baseline'] ?? 0;
        $convFinal = end($this->historialIteraciones)['conversion_mejor'] ?? $convInicial;
        $mejoraConvPct = $convInicial > 0 ? round((($convFinal - $convInicial) / $convInicial) * 100, 1) : 0;

        $reporte = [
            'timestamp' => date('Y-m-d H:i:s'),
            'escenario' => $escenario,
            'iteraciones' => $iteraciones,
            'simulaciones_por_variante' => $this->simulacionesPorVariante,
            'prompt_inicial_length' => strlen($promptActual),
            'prompt_final_length' => strlen($promptMejor),
            'score_inicial' => $scoreInicial,
            'score_final' => $scoreFinal,
            'mejora_score_pct' => $mejoraPct,
            'conversion_inicial' => $convInicial,
            'conversion_final' => $convFinal,
            'mejora_conversion_pct' => $mejoraConvPct,
            'estrategias_aplicadas' => array_unique($estrategiasAplicadas),
            'version_guardada' => $guardado['version'] ?? null,
            'iteraciones_detalle' => $this->historialIteraciones,
        ];

        // Guardar reporte
        $path = __DIR__ . '/../resultados/optimizacion.json';
        file_put_contents($path, json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->log("\nReporte guardado: $path");

        return $reporte;
    }

    /**
     * Ejecutar una iteración de optimización
     */
    private function ejecutarIteracion(string $escenario, string $promptBase, int $iterNum): array {
        // 1. Simulación baseline
        $this->log("  [1/5] Ejecutando simulación baseline...");
        $reporteBase = $this->simular($escenario, $promptBase);
        $analisis = $this->analizador->analizar($reporteBase);

        $this->log("  Score baseline: {$analisis['score_actual']}");
        $this->log("  Problemas: " . (empty($analisis['problemas']) ? 'ninguno' : implode(', ', $analisis['problemas'])));

        // 2. Generar variantes
        $this->log("  [2/5] Generando variantes...");
        $variantes = $this->generador->generar($promptBase, $analisis['problemas']);
        $this->log("  " . count($variantes) . " variantes generadas");

        // 3. Simular cada variante
        $this->log("  [3/5] Simulando variantes...");
        $evaluador = new EvaluadorComparativo();
        $evaluador->registrar('baseline', $reporteBase, $promptBase);

        foreach ($variantes as $i => $v) {
            $this->log("    Variante " . ($i + 1) . "/" . count($variantes) . ": {$v['nombre']}");
            $reporte = $this->simular($escenario, $v['prompt']);
            $evaluador->registrar($v['nombre'], $reporte, $v['prompt']);
        }

        // 4. Comparar
        $this->log("  [4/5] Comparando resultados...");
        $ranking = $evaluador->ranking();
        $comparacion = $evaluador->compararConBaseline('baseline');
        $mejor = $evaluador->mejorVariante();

        foreach ($ranking as $r) {
            $marker = $r['nombre'] === $mejor['nombre'] ? ' <<<' : '';
            $this->log("    #{$r['posicion']} {$r['nombre']}: score={$r['score']} conv=" . round($r['conversion'] * 100) . "%$marker");
        }

        // 5. Resultado
        $this->log("  [5/5] Resultado: " . ($comparacion['es_mejora'] ? "MEJORA ({$comparacion['mejora_conversion_pct']}%)" : "Sin mejora"));

        return [
            'iteracion' => $iterNum,
            'score_baseline' => $analisis['score_actual'],
            'score_mejor' => $mejor['score'] ?? $analisis['score_actual'],
            'conversion_baseline' => $analisis['metricas']['conversion'],
            'conversion_mejor' => $mejor['conversion'] ?? $analisis['metricas']['conversion'],
            'abandono_mejor' => $mejor['abandono'] ?? $analisis['metricas']['abandono'],
            'mejor_variante' => $mejor['nombre'] ?? 'baseline',
            'mejor_prompt' => $mejor['prompt'] ?? $promptBase,
            'estrategias_ganadoras' => $this->extraerEstrategias($mejor['nombre'] ?? ''),
            'mejora_encontrada' => $comparacion['es_mejora'] ?? false,
            'ranking' => $ranking,
            'problemas_detectados' => $analisis['problemas'],
        ];
    }

    /**
     * Ejecutar simulación con un prompt específico inyectado
     */
    private function simular(string $escenario, string $prompt): array {
        $sim = new Simulador('interno');

        // Inyectar prompt en el servicio fake para que AIService lo use
        $servicio = $sim->getServicio();
        if ($servicio && method_exists($servicio, 'setPromptOverride')) {
            $servicio->setPromptOverride($prompt);
        }

        $sim->ejecutar($escenario, $this->simulacionesPorVariante);
        return $sim->reporte();
    }

    private function extraerEstrategias(string $nombre): array {
        if ($nombre === 'baseline') return [];
        return explode('+', $nombre);
    }

    private function promptDefault(): string {
        return "Eres un asistente de ventas. Respuestas cortas, amigables. "
            . "Objetivo: convertir conversacion en venta. Maximo 80 palabras.";
    }

    private function log(string $msg) {
        $this->logs[] = date('H:i:s') . " $msg";
    }

    public function getLogs(): array {
        return $this->logs;
    }

    public function guardarLogs(): string {
        $path = __DIR__ . '/../logs/optimizacion.log';
        file_put_contents($path, implode("\n", $this->logs) . "\n\n", FILE_APPEND);
        return $path;
    }
}
