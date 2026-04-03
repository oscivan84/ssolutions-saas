<?php
/**
 * VersionadorPrompts — Guarda prompts optimizados en BD con versionado.
 * Integra con tabla prompts_ia existente.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/tenant.php';

class VersionadorPrompts {

    /**
     * Guardar nuevo prompt optimizado como nueva versión
     *
     * @param string $prompt Contenido del prompt
     * @param string $tipo Tipo: ventas, diagnostico, etc.
     * @param array $metricas Métricas de la simulación
     * @param array $estrategias Estrategias aplicadas
     * @return array ['success' => bool, 'version' => int, 'idprompt' => int]
     */
    public function guardar(string $prompt, string $tipo = 'ventas', array $metricas = [], array $estrategias = []): array {
        $nid = Tenant::id();

        // Obtener siguiente versión
        $sql = "SELECT COALESCE(MAX(version), 0) + 1 as next_v FROM prompts_ia WHERE negocio_id = ? AND tipo = ? AND nombre = 'optimizado'";
        $result = ejecutarConsulta($sql, 'is', [$nid, $tipo]);
        $nextV = $result ? intval($result->fetch_object()->next_v) : 1;

        // Desactivar versión anterior
        $sql = "UPDATE prompts_ia SET activo = 0 WHERE negocio_id = ? AND tipo = ? AND nombre = 'optimizado' AND activo = 1";
        ejecutarConsulta($sql, 'is', [$nid, $tipo]);

        // Insertar nueva versión
        $negocio = Tenant::negocio();
        $tipoNeg = $negocio->tipo_negocio ?? 'tecnico';

        $metricasJson = json_encode([
            'conversion' => $metricas['conversion'] ?? 0,
            'abandono' => $metricas['abandono'] ?? 0,
            'score' => $metricas['score'] ?? 0,
            'estrategias' => $estrategias,
            'simulaciones' => $metricas['simulaciones'] ?? 0,
            'fecha_optimizacion' => date('Y-m-d H:i:s')
        ]);

        $notas = 'Auto-optimizado. Estrategias: ' . implode(', ', $estrategias) .
            '. Conversión: ' . round(($metricas['conversion'] ?? 0) * 100) . '%';

        $sql = "INSERT INTO prompts_ia (negocio_id, tipo_negocio, tipo, nombre, system_prompt, version, activo, estabilidad, es_default, metricas_json, notas)
                VALUES (?, ?, ?, 'optimizado', ?, ?, 1, 'experimental', 0, ?, ?)";
        $result = ejecutarConsulta($sql, 'isssisss', [
            $nid, $tipoNeg, $tipo, $prompt, $nextV, $metricasJson, $notas
        ]);

        return [
            'success' => (bool)$result,
            'version' => $nextV,
            'idprompt' => $result ? ultimoId() : 0
        ];
    }

    /**
     * Obtener prompt activo actual
     */
    public function getActual(string $tipo = 'ventas'): ?string {
        $nid = Tenant::id();

        // Primero buscar optimizado activo
        $sql = "SELECT system_prompt FROM prompts_ia WHERE negocio_id = ? AND tipo = ? AND activo = 1 ORDER BY version DESC LIMIT 1";
        $result = ejecutarConsulta($sql, 'is', [$nid, $tipo]);
        if ($result && $row = $result->fetch_object()) {
            return $row->system_prompt;
        }

        return null;
    }

    /**
     * Obtener historial de versiones con métricas
     */
    public function getHistorial(string $tipo = 'ventas'): array {
        $sql = "SELECT idprompt, version, activo, estabilidad, metricas_json, notas, fecha_creacion
                FROM prompts_ia WHERE negocio_id = ? AND tipo = ? ORDER BY version DESC";
        $result = ejecutarConsulta($sql, 'is', [Tenant::id(), $tipo]);

        $historial = [];
        if ($result) {
            while ($row = $result->fetch_object()) {
                $row->metricas_json = json_decode($row->metricas_json, true);
                $historial[] = $row;
            }
        }
        return $historial;
    }

    /**
     * Rollback a una versión anterior
     */
    public function rollback(int $idprompt): bool {
        $nid = Tenant::id();
        ejecutarConsulta("UPDATE prompts_ia SET activo = 0 WHERE negocio_id = ? AND activo = 1", 'i', [$nid]);
        $result = ejecutarConsulta("UPDATE prompts_ia SET activo = 1, estabilidad = 'estable' WHERE idprompt = ? AND negocio_id = ?", 'ii', [$idprompt, $nid]);
        return (bool)$result;
    }
}
