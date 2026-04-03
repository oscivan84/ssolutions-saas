<?php
/**
 * GeneradorVariantes — Genera variantes del prompt aplicando estrategias.
 *
 * Toma un prompt base + problemas detectados → genera 3-5 variantes,
 * cada una aplicando una o más estrategias relevantes.
 */

require_once __DIR__ . '/Estrategias/EstrategiaBase.php';
require_once __DIR__ . '/Estrategias/EstrategiaUrgencia.php';
require_once __DIR__ . '/Estrategias/EstrategiaCierreDirecto.php';
require_once __DIR__ . '/Estrategias/EstrategiaReducirTexto.php';
require_once __DIR__ . '/Estrategias/EstrategiaPruebaSocial.php';
require_once __DIR__ . '/Estrategias/EstrategiaObjeciones.php';

class GeneradorVariantes {

    private array $estrategias;

    public function __construct() {
        $this->estrategias = [
            new EstrategiaUrgencia(),
            new EstrategiaCierreDirecto(),
            new EstrategiaReducirTexto(),
            new EstrategiaPruebaSocial(),
            new EstrategiaObjeciones(),
        ];
    }

    /**
     * Generar variantes del prompt basadas en problemas detectados
     *
     * @param string $promptBase Prompt actual
     * @param array $problemas Problemas del AnalizadorConversaciones
     * @param int $maxVariantes Máximo de variantes a generar
     * @return array [['nombre' => 'urgencia+cierre', 'prompt' => '...', 'estrategias' => [...]], ...]
     */
    public function generar(string $promptBase, array $problemas, int $maxVariantes = 5): array {
        $variantes = [];

        // 1. Seleccionar estrategias relevantes
        $relevantes = [];
        foreach ($this->estrategias as $est) {
            if ($est->esRelevante($problemas)) {
                $relevantes[] = $est;
            }
        }

        // Si no hay estrategias relevantes, usar todas con menor prioridad
        if (empty($relevantes)) {
            $relevantes = $this->estrategias;
        }

        // 2. Generar variantes individuales (1 estrategia cada una)
        foreach ($relevantes as $est) {
            if (count($variantes) >= $maxVariantes) break;

            $variantes[] = [
                'nombre' => $est->nombre(),
                'prompt' => $est->aplicar($promptBase, $problemas),
                'estrategias' => [$est->nombre()],
                'descripcion' => $est->descripcion()
            ];
        }

        // 3. Generar variantes combinadas (2 estrategias)
        if (count($relevantes) >= 2 && count($variantes) < $maxVariantes) {
            for ($i = 0; $i < count($relevantes) - 1 && count($variantes) < $maxVariantes; $i++) {
                for ($j = $i + 1; $j < count($relevantes) && count($variantes) < $maxVariantes; $j++) {
                    $prompt = $relevantes[$i]->aplicar($promptBase, $problemas);
                    $prompt = $relevantes[$j]->aplicar($prompt, $problemas);

                    $variantes[] = [
                        'nombre' => $relevantes[$i]->nombre() . '+' . $relevantes[$j]->nombre(),
                        'prompt' => $prompt,
                        'estrategias' => [$relevantes[$i]->nombre(), $relevantes[$j]->nombre()],
                        'descripcion' => $relevantes[$i]->descripcion() . ' + ' . $relevantes[$j]->descripcion()
                    ];
                }
            }
        }

        return array_slice($variantes, 0, $maxVariantes);
    }

    /**
     * Obtener todas las estrategias disponibles
     */
    public function getEstrategias(): array {
        return array_map(fn($e) => [
            'nombre' => $e->nombre(),
            'descripcion' => $e->descripcion()
        ], $this->estrategias);
    }
}
