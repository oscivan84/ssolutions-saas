<?php
/**
 * EvaluadorComparativo — Compara resultados entre variantes y selecciona la mejor.
 *
 * Score = (conversion * 0.6) - (abandono * 0.3) - (mensajes_promedio * 0.01)
 */

class EvaluadorComparativo {

    private array $resultadosPorVariante = [];

    /**
     * Registrar resultado de una variante
     */
    public function registrar(string $nombreVariante, array $reporte, string $prompt): void {
        $resultados = $reporte['resultados'] ?? [];
        $metricas = $reporte['metricas'] ?? [];

        $conversion = $resultados['conversion'] ?? 0;
        $abandono = $resultados['abandono'] ?? 0;
        $msgPromedio = $metricas['mensajes_promedio'] ?? 5;
        $interesados = $resultados['interesados_sin_cerrar'] ?? 0;

        $score = ($conversion * 0.6) - ($abandono * 0.3) - (min($msgPromedio, 10) * 0.01);

        $this->resultadosPorVariante[$nombreVariante] = [
            'nombre' => $nombreVariante,
            'prompt' => $prompt,
            'conversion' => $conversion,
            'abandono' => $abandono,
            'interesados' => $interesados,
            'mensajes_promedio' => $msgPromedio,
            'score' => round($score, 4),
            'simulaciones' => $reporte['simulaciones'] ?? 0,
            'errores' => $resultados['errores'] ?? 0,
        ];
    }

    /**
     * Obtener la mejor variante
     */
    public function mejorVariante(): ?array {
        if (empty($this->resultadosPorVariante)) return null;

        $mejor = null;
        foreach ($this->resultadosPorVariante as $v) {
            if ($mejor === null || $v['score'] > $mejor['score']) {
                $mejor = $v;
            }
        }
        return $mejor;
    }

    /**
     * Generar ranking completo
     */
    public function ranking(): array {
        $ranking = array_values($this->resultadosPorVariante);
        usort($ranking, fn($a, $b) => $b['score'] <=> $a['score']);

        foreach ($ranking as $i => &$r) {
            $r['posicion'] = $i + 1;
        }

        return $ranking;
    }

    /**
     * Comparar mejor variante con baseline
     */
    public function compararConBaseline(string $nombreBaseline): array {
        $baseline = $this->resultadosPorVariante[$nombreBaseline] ?? null;
        $mejor = $this->mejorVariante();

        if (!$baseline || !$mejor) return ['mejora' => 0];

        $mejoraConversion = $baseline['conversion'] > 0
            ? round((($mejor['conversion'] - $baseline['conversion']) / $baseline['conversion']) * 100, 1)
            : 0;

        return [
            'baseline' => [
                'nombre' => $baseline['nombre'],
                'conversion' => $baseline['conversion'],
                'score' => $baseline['score']
            ],
            'mejor' => [
                'nombre' => $mejor['nombre'],
                'conversion' => $mejor['conversion'],
                'score' => $mejor['score']
            ],
            'mejora_conversion_pct' => $mejoraConversion,
            'mejora_score' => round($mejor['score'] - $baseline['score'], 4),
            'es_mejora' => $mejor['score'] > $baseline['score'] && $mejor['nombre'] !== $baseline['nombre']
        ];
    }

    public function getResultados(): array {
        return $this->resultadosPorVariante;
    }
}
