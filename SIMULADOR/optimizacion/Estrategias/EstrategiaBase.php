<?php
/**
 * Base para todas las estrategias de optimización de prompts.
 */
abstract class EstrategiaBase {

    abstract public function nombre(): string;
    abstract public function descripcion(): string;

    /**
     * Aplicar la estrategia al prompt.
     * @param string $prompt Prompt original
     * @param array $problemas Problemas detectados por el analizador
     * @return string Prompt modificado
     */
    abstract public function aplicar(string $prompt, array $problemas = []): string;

    /**
     * Verificar si esta estrategia es relevante para los problemas detectados
     */
    abstract public function esRelevante(array $problemas): bool;
}
